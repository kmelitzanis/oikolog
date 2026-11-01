<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Expense;
use App\Services\Ledger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Everyday purchases, a month at a time.
 *
 * Bills are for what recurs or falls due; the supermarket run, a jumper or a
 * birthday present is spent once and that is the end of it. Keeping them
 * here, rather than as bare withdrawals on an account, lets the month say
 * what the money went on.
 */
class ExpenseController extends Controller
{
    public function __construct(private Ledger $ledger) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $month = $this->month($request->query('month'));

        $expenses = Expense::forUser($user)->inMonth($month)
            ->with(['account:id,name', 'creator:id,name'])
            ->orderByDesc('spent_at')
            ->get();

        $byCategory = $expenses->groupBy('category')
            ->map(fn ($group) => ['amount' => round((float) $group->sum('amount'), 2), 'count' => $group->count()])
            ->sortByDesc('amount');

        return view('expenses.index', [
            'month'      => $month,
            'expenses'   => $expenses,
            'days'       => $expenses->groupBy(fn (Expense $e) => $e->spent_at->toDateString()),
            'total'      => round((float) $expenses->sum('amount'), 2),
            'byCategory' => $byCategory,
            'isCurrent'  => $month->isSameMonth(now()),
        ]);
    }

    public function create(Request $request)
    {
        return view('expenses.form', ['expense' => null, 'accounts' => $this->accounts($request)]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        [$data, $account] = $this->validated($request);

        $expense = DB::transaction(function () use ($data, $account, $user) {
            $expense = Expense::create([
                ...$data,
                'currency_code' => $account?->currency_code ?? $user->currency_code ?? 'EUR',
                'account_id'    => $account?->id,
                'created_by'    => $user->id,
            ]);

            if ($account) {
                $this->ledger->spend($account, $expense, $user->id);
            }

            return $expense;
        });

        return redirect()->route('expenses.index', ['month' => $expense->spent_at->format('Y-m')])
            ->with('success', __('messages.expense_recorded'));
    }

    public function edit(Request $request, Expense $expense)
    {
        $this->authorizeExpense($request, $expense);

        return view('expenses.form', ['expense' => $expense, 'accounts' => $this->accounts($request)]);
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizeExpense($request, $expense);
        [$data, $account] = $this->validated($request);

        DB::transaction(function () use ($expense, $data, $account, $request) {
            $expense->update([
                ...$data,
                'currency_code' => $account?->currency_code ?? $expense->currency_code,
                'account_id'    => $account?->id,
            ]);

            // The withdrawal is derived from the purchase, so it is written
            // again from it: a new amount, date or account all land in one go.
            $expense->transaction()->delete();
            if ($account) {
                $this->ledger->spend($account, $expense, $request->user()->id);
            }
        });

        return redirect()->route('expenses.index', ['month' => $expense->spent_at->format('Y-m')])
            ->with('success', __('messages.expense_updated'));
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->authorizeExpense($request, $expense);

        // Takes its withdrawal with it; see Expense::booted().
        DB::transaction(fn () => $expense->delete());

        return back()->with('success', __('messages.expense_deleted'));
    }

    /** @return array{0: array, 1: ?Account} */
    private function validated(Request $request): array
    {
        $user = $request->user();

        // Once the household keeps accounts, a purchase has to come out of
        // one, as a bill payment does, or the balances stop matching reality.
        $hasAccounts = Account::forUser($user)->active()->exists();

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:160'],
            'category'   => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'amount'     => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'spent_at'   => ['required', 'date', 'before_or_equal:today'],
            'account_id' => [$hasAccounts ? 'required' : 'nullable', 'string', 'max:26'],
            'notes'      => ['nullable', 'string', 'max:1000'],
            'is_shared'  => ['nullable', 'boolean'],
        ]);

        $account = null;
        if (! empty($data['account_id'])) {
            $account = Account::forUser($user)->find($data['account_id']);
            if (! $account) {
                throw ValidationException::withMessages(['account_id' => __('messages.invalid_selection')]);
            }
        }
        unset($data['account_id']);

        // A date from the form is a day; keep the time it was entered so the
        // ledger orders same-day movements as they happened.
        $data['spent_at'] = Carbon::parse($data['spent_at'])->setTimeFrom(now());

        // Sharing needs someone to share with.
        $data['is_shared'] = (bool) ($data['is_shared'] ?? false) && $user->family_id;
        $data['family_id'] = $data['is_shared'] ? $user->family_id : null;

        return [$data, $account];
    }

    private function accounts(Request $request)
    {
        return Account::forUser($request->user())->active()->orderBy('name')->get();
    }

    /** "2026-10" from the query string, falling back to this month. */
    private function month(?string $value): Carbon
    {
        if ($value && preg_match('/^\d{4}-\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('!Y-m', $value);
            } catch (\Throwable $e) {
                // Fall through to this month.
            }
        }

        return now()->startOfMonth();
    }

    /** Whoever can see a purchase can change it, as with bills. */
    private function authorizeExpense(Request $request, Expense $expense): void
    {
        abort_unless($expense->isVisibleTo($request->user()), 403);
    }
}
