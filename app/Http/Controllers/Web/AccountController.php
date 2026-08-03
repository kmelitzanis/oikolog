<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Services\Ledger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function __construct(private Ledger $ledger) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $accounts = Account::forUser($user)->orderByDesc('is_active')->orderBy('name')->get();

        $from = now()->startOfMonth();
        $to   = now()->endOfMonth();

        $rows = $accounts->map(fn(Account $a) => [
            'account'   => $a,
            'balance'   => $a->balance(),
            'movements' => $a->movementsBetween($from, $to),
        ]);

        $active = $rows->filter(fn($r) => $r['account']->is_active);
        $stats = [
            'total'    => round($active->sum('balance'), 2),
            'in'       => round($active->sum(fn($r) => $r['movements']['in']), 2),
            'out'      => round($active->sum(fn($r) => $r['movements']['out']), 2),
            'count'    => $active->count(),
        ];

        return view('accounts.index', compact('rows', 'stats'));
    }

    public function create()
    {
        return view('accounts.form');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Account::create([
            ...$data,
            'currency_code' => $request->user()->currency_code,
            'created_by' => $request->user()->id,
            'family_id' => $data['is_shared'] ? $request->user()->family_id : null,
        ]);

        return redirect()->route('accounts.index')->with('success', __('messages.account_created'));
    }

    public function show(Request $request, Account $account)
    {
        $this->authorizeAccess($account);

        $transactions = $account->transactions()
            ->with(['income:id,name', 'payment.bill:id,name', 'creator:id,name'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->paginate(50);

        // Transfer targets: any other account the user can reach.
        $targets = Account::forUser($request->user())->active()
            ->where('id', '!=', $account->id)->orderBy('name')->get();

        $movements = $account->movementsBetween(now()->startOfMonth(), now()->endOfMonth());

        return view('accounts.show', [
            'account' => $account,
            'balance' => $account->balance(),
            'transactions' => $transactions,
            'targets' => $targets,
            'movements' => $movements,
        ]);
    }

    public function edit(Account $account)
    {
        $this->authorizeAccess($account);

        return view('accounts.form', compact('account'));
    }

    public function update(Request $request, Account $account)
    {
        $this->authorizeAccess($account);

        $data = $this->validated($request);
        $data['is_active'] = (bool) $request->input('is_active', true);
        $data['family_id'] = $data['is_shared'] ? $request->user()->family_id : null;

        $account->update($data);

        return redirect()->route('accounts.show', $account)->with('success', __('messages.account_updated'));
    }

    public function destroy(Account $account)
    {
        $this->authorizeAccess($account);

        // Deleting would cascade the ledger away and silently rewrite history;
        // an account that has been used is deactivated instead.
        if ($account->transactions()->exists()) {
            $account->update(['is_active' => false]);

            return redirect()->route('accounts.index')->with('success', __('messages.account_archived'));
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('success', __('messages.account_deleted'));
    }

    /** Move money to another account. */
    public function transfer(Request $request, Account $account)
    {
        $this->authorizeAccess($account);

        $data = $request->validate([
            'to_account_id' => ['required', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:160'],
        ]);

        $target = Account::forUser($request->user())->findOrFail($data['to_account_id']);
        abort_if($target->id === $account->id, 422, 'Cannot transfer to the same account.');

        $this->ledger->transfer(
            $account,
            $target,
            (float) $data['amount'],
            $this->occurredAt($data['occurred_at'] ?? null),
            $request->user()->id,
            $data['description'] ?? null,
        );

        return back()->with('success', __('messages.transfer_recorded'));
    }

    /** A manual movement — cash in hand, an interest credit, a correction. */
    public function storeTransaction(Request $request, Account $account)
    {
        $this->authorizeAccess($account);

        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:160'],
        ]);

        $when = $this->occurredAt($data['occurred_at'] ?? null);
        $amount = (float) $data['amount'];

        $data['direction'] === 'in'
            ? $this->ledger->deposit($account, $amount, $when, $request->user()->id, $data['description'] ?? null)
            : $this->ledger->withdraw($account, $amount, $when, $request->user()->id, $data['description'] ?? null);

        return back()->with('success', __('messages.movement_recorded'));
    }

    public function destroyTransaction(Account $account, AccountTransaction $transaction)
    {
        $this->authorizeAccess($account);
        abort_unless($transaction->account_id === $account->id, 404);

        // A bill payment's movement belongs to the payment; deleting it here
        // would leave the two disagreeing. Undo the payment instead.
        abort_if($transaction->payment_id !== null, 422, __('messages.movement_belongs_to_payment'));

        $transaction->transfer_group
            ? $this->ledger->reverseTransfer($transaction)
            : $transaction->delete();

        return back()->with('success', __('messages.movement_deleted'));
    }

    private function occurredAt(?string $input): Carbon
    {
        return $input ? Carbon::parse($input)->setTimeFrom(now()) : now();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:40'],
            'color_hex' => ['nullable', 'string', 'max:7'],
            'opening_balance' => ['nullable', 'numeric'],
            'is_shared' => ['nullable'],
            'is_active' => ['nullable'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['is_shared'] = (bool) ($data['is_shared'] ?? false);
        $data['opening_balance'] = (float) ($data['opening_balance'] ?? 0);
        $data['icon'] = $data['icon'] ?: 'account_balance';
        $data['color_hex'] = $data['color_hex'] ?: '#10b981';

        return $data;
    }

    private function authorizeAccess(Account $account): void
    {
        $user = Auth::user();
        $ok = $account->created_by === $user->id
            || ($account->is_shared && $account->family_id === $user->family_id);

        abort_unless($ok, 403, 'Access denied.');
    }
}
