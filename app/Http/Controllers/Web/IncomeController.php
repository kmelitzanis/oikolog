<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Income;
use App\Models\User;
use App\Services\Ledger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Income::forUser($user)->orderByDesc('next_date');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('frequency')) {
            $query->where('frequency', $request->frequency);
        }
        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                default => null,
            };
        }

        $incomes = $query->paginate(50);

        // Summary stats
        $allActive = Income::forUser($user)->active()->get();
        $monthlyIncome = round($allActive->sum(fn($i) => $i->monthlyEquivalent()), 2);

        // "Received this month" — there is no income-payments table, so this is
        // derived from `last_received_date`: a source counts once its most
        // recent receipt falls inside the current month.
        $receivedSources = $allActive->filter(
            fn($i) => $i->last_received_date && $i->last_received_date->isSameMonth(now())
        );
        $received = round($receivedSources->sum('amount'), 2);

        $stats = [
            'monthly_income' => $monthlyIncome,
            'yearly_income' => round($monthlyIncome * 12, 2),
            'total_sources' => $allActive->count(),
            'recurring' => $allActive->filter(fn($i) => $i->frequency !== 'once')->count(),
            'received_this_month' => $received,
            'received_count' => $receivedSources->count(),
            'received_pct' => $monthlyIncome > 0
                ? min(100, (int) round($received / $monthlyIncome * 100))
                : 0,
        ];

        // Accounts share this page: money coming in and where it sits are one
        // subject, and "λογαριασμοί" already means bills in the Greek menu.
        ['rows' => $accountRows, 'stats' => $accountStats] = Account::summaryFor($user);

        return view('income.index', compact('incomes', 'stats', 'accountRows', 'accountStats'));
    }

    public function create(Request $request)
    {
        $accounts = Account::forUser($request->user())->active()->orderBy('name')->get();

        return view('income.form', compact('accounts'));
    }

    public function store(Request $request, Ledger $ledger)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'frequency' => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'frequency_interval' => ['nullable', 'integer', 'min:1', 'max:99'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        // Income sharing follows the account it is paid into.
        $shared = $this->accountIsShared($request, $data['account_id'] ?? null);

        $income = Income::create([
            ...$data,
            'currency_code' => $request->user()->currency_code,
            'is_shared' => $shared,
            'created_by' => $request->user()->id,
            'family_id' => $shared ? $request->user()->family_id : null,
            'next_date' => $data['start_date'],
            'frequency_interval' => $data['frequency_interval'] ?? 1,
        ]);

        // A one-off income is a record of money that already arrived, not a
        // schedule waiting to be confirmed — so it lands in the account right
        // away. Recurring sources still get confirmed once per period, because
        // there the entry describes what is *expected*.
        $account = $income->frequency === 'once'
            ? $this->receiveOneOff($income, $request->user(), $ledger)
            : null;

        return redirect()->route('income.index')->with('success', $account
            ? __('messages.income_deposited', ['account' => $account->name])
            : __('messages.income_added'));
    }

    /**
     * Settle a one-off income immediately: mark it received and, when it has a
     * destination account, deposit it. Returns the account credited, if any.
     *
     * A start date in the future is left alone — that is a windfall someone is
     * expecting, and crediting it now would make the balance describe money
     * that has not arrived.
     */
    private function receiveOneOff(Income $income, User $user, Ledger $ledger): ?Account
    {
        $receivedAt = $income->start_date
            ? Carbon::parse($income->start_date)->setTimeFrom(now())
            : now();

        if ($receivedAt->isAfter(now()->endOfDay())) {
            return null;
        }

        $income->update(['last_received_date' => $receivedAt->toDateString()]);

        if (! $income->account_id) {
            return null;
        }

        $account = Account::forUser($user)->find($income->account_id);

        if ($account) {
            $ledger->deposit(
                $account,
                (float) $income->amount,
                $receivedAt,
                $user->id,
                $income->name,
                $income,
            );
        }

        return $account;
    }

    public function show(Income $income)
    {
        $this->authorizeAccess($income);

        $income->load('account');

        $deposits = $income->deposits()->with('account:id,name,icon,color_hex')
            ->orderByDesc('occurred_at')->limit(24)->get();

        $receivedTotal = round((float) $income->deposits()->sum('amount'), 2);

        return view('income.show', compact('income', 'deposits', 'receivedTotal'));
    }

    public function edit(Income $income)
    {
        $this->authorizeAccess($income);
        $accounts = Account::forUser(request()->user())->active()->orderBy('name')->get();

        return view('income.form', compact('income', 'accounts'));
    }

    public function update(Request $request, Income $income)
    {
        $this->authorizeAccess($income);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'frequency' => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'frequency_interval' => ['nullable', 'integer', 'min:1', 'max:99'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_active' => ['nullable'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['is_active'] = (bool)($data['is_active'] ?? true);
        $data['is_shared'] = $this->accountIsShared($request, $data['account_id'] ?? null);
        $data['family_id'] = $data['is_shared'] ? $request->user()->family_id : null;
        $data['frequency_interval'] = $data['frequency_interval'] ?? 1;

        $income->update($data);

        return redirect()->route('income.show', $income)->with('success', 'Income updated.');
    }

    public function destroy(Income $income)
    {
        $this->authorizeAccess($income);
        $income->delete();

        return redirect()->route('income.index')->with('success', 'Income deleted.');
    }

    /**
     * Mark the income as received: advance the schedule and, when the source
     * has a destination account, deposit the money there. The amount and date
     * can be overridden — a salary rarely lands on exactly the planned day or
     * to the cent.
     */
    public function markReceived(Request $request, Income $income, Ledger $ledger)
    {
        $this->authorizeAccess($income);

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'received_at' => ['nullable', 'date', 'before_or_equal:today'],
            'account_id' => ['nullable', 'exists:accounts,id'],
        ]);

        $receivedAt = isset($data['received_at'])
            ? \Carbon\Carbon::parse($data['received_at'])->setTimeFrom(now())
            : now();
        $amount = (float) ($data['amount'] ?? $income->amount);

        $account = null;
        if (!empty($data['account_id'])) {
            $account = Account::forUser($request->user())->find($data['account_id']);
        } elseif ($income->account_id) {
            $account = Account::forUser($request->user())->find($income->account_id);
        }

        $nextDate = $income->calculateNextDate();
        $income->update([
            'last_received_date' => $receivedAt->toDateString(),
            'next_date' => $nextDate ? $nextDate->toDateString() : $income->next_date,
        ]);

        if ($account) {
            $ledger->deposit($account, $amount, $receivedAt, $request->user()->id, $income->name, $income);
        }

        return back()->with('success', $account
            ? __('messages.income_deposited', ['account' => $account->name])
            : __('messages.income_received_no_account'));
    }

    /** Income visibility mirrors the account it is paid into. */
    private function accountIsShared(Request $request, $accountId): bool
    {
        if (! $accountId || ! $request->user()->family_id) {
            return false;
        }

        return (bool) Account::forUser($request->user())->whereKey($accountId)->value('is_shared');
    }

    private function authorizeAccess(Income $income): void
    {
        $user = Auth::user();
        $ok = $income->created_by === $user->id
            || ($income->is_shared && $income->family_id === $user->family_id);
        abort_unless($ok, 403, 'Access denied.');
    }
}

