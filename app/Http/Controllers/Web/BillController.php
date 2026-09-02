<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SendPaymentPushNotification;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Provider;
use App\Services\Ledger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = Bill::with(['category', 'provider', 'payments' => function ($q) {
            $q->latest('paid_at')->with('paidBy');
        }])
            ->forUser($user)
            ->orderBy('next_due_date');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('frequency')) {
            $query->where('frequency', $request->frequency);
        }
        // The page opens on this month: the full list is rarely what someone
        // came for. "all" is now an explicit value rather than an absent one,
        // so the pills can tell "no filter chosen yet" from "show me all".
        $status = (string) $request->input('status', 'this_month');

        match ($status) {
            'active'     => $query->where('is_active', true),
            'overdue'    => $query->where('is_active', true)->whereDate('next_due_date', '<', now()),
            'this_month' => $query->where('is_active', true)
                ->whereBetween('next_due_date', [now()->startOfMonth(), now()->endOfMonth()]),
            'shared'     => $query->where('is_shared', true),
            'inactive'   => $query->where('is_active', false),
            default      => null,
        };

        $bills = $query->paginate(50);

        // Counts for the filter pills — computed off the unfiltered set so the
        // pills keep showing the full picture while a filter is applied.
        $all = Bill::forUser($user)->get(['is_active', 'is_shared', 'next_due_date']);
        $billCounts = [
            'all'        => $all->count(),
            'overdue'    => $all->filter(fn($b) => $b->is_active && $b->next_due_date && $b->next_due_date->isPast())->count(),
            'this_month' => $all->filter(fn($b) => $b->is_active && $b->next_due_date && $b->next_due_date->isSameMonth(now()))->count(),
            'shared'     => $all->where('is_shared', true)->count(),
        ];

        return view('bills.index', compact('bills', 'billCounts', 'status'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $providers = Provider::with('categories')->orderBy('name')->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'logo_url' => $p->logo_url,
                'category_ids' => $p->categories->pluck('id')->all(),
            ]);
        $accounts = Account::forUser(request()->user())->active()->orderBy('name')->get();

        return view('bills.form', compact('categories', 'providers', 'accounts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:120'],
            'description'        => ['nullable', 'string'],
            'category_id'        => ['required', 'exists:categories,id'],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'default_account_id' => ['nullable', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'cost_varies' => ['nullable', 'boolean'],
            'frequency'          => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['nullable', 'date', 'after:start_date'],
            'is_shared'          => ['nullable'],
            'notify_enabled'     => ['nullable'],
            'notify_days_before' => ['nullable', 'integer', 'min:1', 'max:30'],
            'url'                => ['nullable', 'url'],
            'notes'              => ['nullable', 'string'],
        ]);

        $bill = Bill::create([
            ...$data,
            'currency_code' => $request->user()->currency_code,
            'cost_varies' => (bool)($data['cost_varies'] ?? false),
            'is_shared'      => (bool) ($data['is_shared'] ?? false),
            'notify_enabled' => (bool) ($data['notify_enabled'] ?? false),
            'created_by'     => $request->user()->id,
            'family_id'      => ($data['is_shared'] ?? false) ? $request->user()->family_id : null,
            'next_due_date'  => $data['start_date'],
        ]);

        // Handle uploaded receipt images (optional, via Spatie medialibrary)
        if ($request->hasFile('receipts') && class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class) && method_exists($bill, 'addMedia')) {
            foreach ($request->file('receipts') as $file) {
                try {
                    $bill->addMedia($file->getRealPath())->usingFileName(uniqid() . '.' . $file->getClientOriginalExtension())->toMediaCollection('receipts');
                } catch (\Exception $e) {
                    // ignore individual failures
                }
            }
        }

        return redirect()->route('bills.show', $bill)->with('success', 'Bill created.');
    }

    public function show(Bill $bill)
    {
        $this->authorizeView($bill);
        $bill->load(['category', 'provider', 'payments.paidBy', 'payments.account']);
        $payments = $bill->payments()->with(['paidBy', 'account'])->orderByDesc('paid_at')->get();

        return view('bills.show', compact('bill', 'payments'));
    }

    // Calendar view
    public function calendar()
    {
        return view('calendar.index');
    }

    // Events for FullCalendar — returns bills + incomes
    public function events(Request $request)
    {
        $user = $request->user();

        // Parse dates - handle both RFC 3339 with timezone and ISO 8601 formats
        $startStr = $request->get('start');
        $endStr = $request->get('end');

        // Remove space before timezone offset if present (URL encoding issue)
        if ($startStr && strpos($startStr, ' ') !== false) {
            $startStr = str_replace(' ', '+', $startStr);
        }
        if ($endStr && strpos($endStr, ' ') !== false) {
            $endStr = str_replace(' ', '+', $endStr);
        }

        $start = $startStr ? \Carbon\Carbon::parse($startStr) : now()->startOfMonth();
        $end = $endStr ? \Carbon\Carbon::parse($endStr) : now()->endOfMonth();

        // ── Bills ─────────────────────────────────────────────────────────────
        $bills = Bill::forUser($user)->whereNotNull('next_due_date')
            ->with(['category', 'provider', 'payments' => function ($q) use ($start, $end) {
                $q->whereBetween('paid_at', [$start->startOfDay(), $end->endOfDay()]);
            }])->get();

        $billEvents = collect();

        foreach ($bills as $b) {
            // Get all occurrences between start and end
            $occurrences = $b->occurrencesBetween($start, $end);

            foreach ($occurrences as $date) {
                // Check if this occurrence was paid
                $isPaid = $b->payments->some(fn($p) => $p->paid_at->toDateString() === $date->toDateString());

                $isOverdue = $date->isPast() && !$isPaid && $b->is_active;
                $isSoon = !$isOverdue && !$isPaid && $date->diffInDays(now(), false) <= 7 && $date->isFuture() && $b->is_active;

                // Determine color
                if ($isPaid) {
                    $color = '#10b981'; // Green for paid
                } elseif ($isOverdue) {
                    $color = '#ef4444'; // Red for overdue
                } elseif ($isSoon) {
                    $color = '#f97316'; // Orange for upcoming soon
                } else {
                    $color = $b->category?->color_hex ?? '#6366f1';
                }

                $billEvents->push([
                    'id' => 'bill-' . $b->id . '-' . $date->timestamp,
                    'title' => '• ' . $b->name,
                    'start' => $date->toDateString(),
                    'allDay' => true,
                    'url' => route('bills.show', $b),
                    'color' => $color,
                    'extendedProps' => [
                        'type' => 'bill',
                        'amount' => $b->currency_code . ' ' . number_format($b->amount, 2),
                        'overdue' => $isOverdue,
                        'paid' => $isPaid,
                        'soon' => $isSoon,
                        'provider' => $b->provider?->name ?? '',
                    ],
                ]);
            }
        }

        // ── Incomes ───────────────────────────────────────────────────────────
        $incomes = \App\Models\Income::forUser($user)->active()->whereNotNull('next_date')->get();

        $incomeEvents = $incomes->map(function ($i) {
            return [
                'id' => 'income-' . $i->id,
                'title' => '• ' . $i->name,
                'start' => $i->next_date?->toDateString(),
                'allDay' => true,
                'url' => route('income.show', $i),
                'color' => '#10b981',
                'extendedProps' => [
                    'type' => 'income',
                    'amount' => $i->currency_code . ' ' . number_format($i->amount, 2),
                ],
            ];
        });

        return response()->json($billEvents->concat($incomeEvents)->values());
    }

    public function edit(Bill $bill)
    {
        $this->authorizeEdit($bill);
        $categories = Category::orderBy('name')->get();
        $providers = Provider::with('categories')->orderBy('name')->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'logo_url' => $p->logo_url,
                'category_ids' => $p->categories->pluck('id')->all(),
            ]);
        $accounts = Account::forUser(request()->user())->active()->orderBy('name')->get();

        return view('bills.form', compact('bill', 'categories', 'providers', 'accounts'));
    }

    public function update(Request $request, Bill $bill)
    {
        $this->authorizeEdit($bill);

        $data = $request->validate([
            'name'               => ['required', 'string', 'max:120'],
            'description'        => ['nullable', 'string'],
            'category_id'        => ['required', 'exists:categories,id'],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'default_account_id' => ['nullable', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'cost_varies' => ['nullable', 'boolean'],
            'frequency'          => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['nullable', 'date'],
            'is_shared'          => ['nullable'],
            'notify_enabled'     => ['nullable'],
            'notify_days_before' => ['nullable', 'integer', 'min:1', 'max:30'],
            'url'                => ['nullable', 'url'],
            'notes'              => ['nullable', 'string'],
        ]);

        $data['cost_varies'] = (bool)($data['cost_varies'] ?? false);
        $data['is_shared']      = (bool) ($data['is_shared'] ?? false);
        $data['notify_enabled'] = (bool) ($data['notify_enabled'] ?? false);

        if (isset($data['is_shared'])) {
            $data['family_id'] = $data['is_shared'] ? $request->user()->family_id : null;
        }

        // Detect whether the recurrence schedule itself changed so we can snap
        // next_due_date back onto the new cadence (otherwise it keeps drifting
        // on the old frequency and appears to recur too often).
        $scheduleChanged = $bill->frequency !== $data['frequency']
            || optional($bill->start_date)->toDateString() !== \Carbon\Carbon::parse($data['start_date'])->toDateString();

        $bill->update($data);

        if ($scheduleChanged) {
            $bill->realignNextDueDate();
            $bill->save();
        }

        // Handle uploaded receipt images on update
        if ($request->hasFile('receipts') && class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class) && method_exists($bill, 'addMedia')) {
            foreach ($request->file('receipts') as $file) {
                try {
                    $bill->addMedia($file->getRealPath())->usingFileName(uniqid() . '.' . $file->getClientOriginalExtension())->toMediaCollection('receipts');
                } catch (\Exception $e) {
                    // ignore
                }
            }
        }

        return redirect()->route('bills.show', $bill)->with('success', 'Bill updated.');
    }

    public function destroy(Bill $bill)
    {
        $this->authorizeEdit($bill);
        $bill->delete();

        return redirect()->route('bills.index')->with('success', 'Bill deleted.');
    }

    /**
     * Record what the current cycle costs, without paying it.
     *
     * A bill whose cost varies shows "varies" until the invoice arrives; this
     * is what turns that into a number, so the dashboard and the list can say
     * what is actually owed days before anyone pays it. Sending an empty value
     * clears it back to unknown.
     */
    public function updateCurrentAmount(Request $request, Bill $bill)
    {
        $this->authorizeEdit($bill);

        abort_unless($bill->cost_varies, 422, 'This bill has a fixed amount.');

        $data = $request->validate([
            'current_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $amount = $data['current_amount'] === null || $data['current_amount'] === ''
            ? null
            : round((float) $data['current_amount'], 2);

        $bill->update(['current_amount' => $amount]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'current_amount'   => $amount,
                'formatted'        => $amount === null
                    ? null
                    : $bill->currency_code . ' ' . number_format($amount, 2),
                'monthly_formatted' => number_format($bill->fresh()->monthlyEquivalent(), 2),
            ]);
        }

        return back()->with('success', __('messages.amount_updated'));
    }

    public function markPaid(Request $request, Bill $bill, Ledger $ledger)
    {
        $this->authorizeView($bill);

        $paidByUserId = $request->input('paid_by_user_id', $request->user()->id);

        // The money has to come out of somewhere. Once the user keeps accounts,
        // picking one is required — otherwise balances quietly stop matching
        // reality. Users with no accounts yet can still record payments.
        $hasAccounts = Account::forUser($request->user())->active()->exists();
        $request->validate([
            'account_id' => [$hasAccounts ? 'required' : 'nullable', 'exists:accounts,id'],
        ]);
        $account = $request->filled('account_id')
            ? Account::forUser($request->user())->find($request->input('account_id'))
            : null;
        $paymentMode = $request->input('payment_mode', 'full'); // 'partial' or 'full'
        $isPartial = $paymentMode === 'partial';

        // Payments are often recorded days after the fact. The date defaults to
        // today but can be backdated, which matters because `paid_at` decides
        // when the account balance actually moved. Future dates are
        // rejected — a bill isn't paid before it's paid.
        $request->validate([
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $paidAt = $request->filled('paid_at')
            ? Carbon::parse($request->input('paid_at'))->setTimeFrom(now())
            : now();

        // Determine the total amount for this billing cycle
        if ($bill->cost_varies && $request->filled('custom_amount')) {
            $periodAmount = (float)$request->input('custom_amount');
        } else {
            // periodAmount() prefers this cycle's recorded figure over the
            // template amount, which for a varying bill is only an estimate.
            $periodAmount = $bill->periodAmount();
        }

        // Calculate how much is being paid now and what the new remaining balance will be
        if ($isPartial) {
            $partialAmount = (float)$request->input('partial_amount', 0);
            $currentRemaining = $bill->remaining_balance !== null
                ? (float)$bill->remaining_balance
                : $periodAmount;

            $newRemaining = round($currentRemaining - $partialAmount, 2);

            // If partial payment covers the full remaining amount, treat as full
            if ($newRemaining <= 0) {
                $isPartial = false;
                $payAmount = $currentRemaining;
                $newRemaining = null;
            } else {
                $payAmount = $partialAmount;
            }
        } else {
            // Full payment: pay whatever is still remaining
            $payAmount = $bill->remaining_balance !== null
                ? (float)$bill->remaining_balance
                : $periodAmount;
            $newRemaining = null;
        }

        $payment = DB::transaction(function () use ($bill, $request, $paidByUserId, $account, $payAmount, $isPartial, $newRemaining, $paidAt, $ledger) {
            $payment = Payment::create([
                'bill_id'       => $bill->id,
                'paid_by' => $paidByUserId,
                'account_id' => $account?->id,
                'amount' => $payAmount,
                'is_partial' => $isPartial,
                'currency_code' => $bill->currency_code,
                'paid_at'       => $paidAt,
                'notes' => $request->input('notes'),
            ]);

            if ($account) {
                $ledger->withdraw(
                    $account,
                    $payAmount,
                    $paidAt,
                    $paidByUserId,
                    $bill->name,
                    $payment,
                );
            }

            if ($isPartial) {
                // Partial: update remaining balance, do NOT advance the due date
                $bill->update([
                    'remaining_balance' => $newRemaining,
                    'last_paid_date' => $paidAt->toDateString(),
                ]);
            } else {
                // Full: clear remaining balance and advance to next period
                $nextDue = $bill->calculateNextDueDate();
                $bill->update([
                    'remaining_balance' => null,
                    // A new cycle starts with its cost unknown again.
                    'current_amount' => null,
                    'last_paid_date' => $paidAt->toDateString(),
                    'next_due_date' => $nextDue?->toDateString() ?? $bill->next_due_date,
                ]);
            }

            return $payment;
        });

        // Tell the rest of the household. After the response so the person who
        // just paid isn't kept waiting on the push services.
        SendPaymentPushNotification::dispatch($payment->id)->afterResponse();

        if ($request->wantsJson() || $request->ajax()) {
            $bill->refresh();
            return response()->json([
                'status' => $isPartial ? 'partial' : 'paid',
                'remaining_balance' => $bill->remaining_balance,
                'last_paid_date' => $bill->last_paid_date?->toDateString(),
                'next_due_date' => $bill->next_due_date?->toDateString(),
                'message' => $isPartial ? 'Μερική πληρωμή καταγράφηκε.' : 'Πληρωμή καταγράφηκε.',
            ]);
        }

        // `undo_route` drives the toast's Undo action.
        return back()
            ->with('success', __($isPartial ? 'messages.partial_payment_recorded' : 'messages.payment_recorded'))
            ->with('undo_route', route('bills.unpay', $bill));
    }

    public function undoLastPayment(Bill $bill)
    {
        $this->authorizeView($bill);

        $lastPayment = $bill->payments()->latest('paid_at')->first();

        if (!$lastPayment) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['status' => 'none', 'message' => 'No payment to undo.'], 422);
            }
            return back()->with('error', 'No payment found to undo.');
        }

        $this->removePayment($bill, $lastPayment);

        $bill->refresh();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'status' => 'undone',
                'remaining_balance' => $bill->remaining_balance,
                'last_paid_date' => $bill->last_paid_date?->toDateString(),
                'next_due_date' => $bill->next_due_date?->toDateString(),
                'message' => __('messages.payment_undone'),
            ]);
        }

        return back()->with('success', __('messages.payment_undone'));
    }

    /**
     * Delete one specific payment from a bill's history.
     *
     * The row-level Undo deliberately only reaches the latest payment. Correcting
     * an older entry is a separate, explicit act, so it gets its own entry point
     * from the payment history on the bill page.
     */
    public function destroyPayment(Bill $bill, Payment $payment)
    {
        $this->authorizeView($bill);

        // Route-model binding resolves {payment} globally, so confirm it really
        // belongs to this bill before deleting anything.
        abort_unless($payment->bill_id === $bill->id, 404);

        $this->removePayment($bill, $payment);

        return back()->with('success', __('messages.payment_deleted'));
    }

    /**
     * Delete a payment and put the bill's derived state back where it belongs.
     *
     * Only the *latest* payment owns the current cycle, so only it may move
     * `next_due_date` or restore a partial balance. Deleting an older entry is a
     * history correction: it must not drag the schedule backwards, so it updates
     * `last_paid_date` and nothing else.
     */
    private function removePayment(Bill $bill, Payment $payment): void
    {
        DB::transaction(function () use ($bill, $payment) {
            $latest    = $bill->payments()->latest('paid_at')->first();
            $wasLatest = $latest && $latest->getKey() === $payment->getKey();

            $paidAt       = $payment->paid_at;
            $wasPartial   = $payment->is_partial;
            $undoneAmount = (float) $payment->amount;

            $payment->delete();

            // Whatever remains is the new "last paid" — null when none is left.
            $prevPayment = $bill->payments()->latest('paid_at')->first();

            if (! $wasLatest) {
                $bill->update(['last_paid_date' => $prevPayment?->paid_at?->toDateString()]);

                return;
            }

            if ($wasPartial) {
                // Give the money back to the outstanding balance. Once it covers
                // the full amount again there is no partial state left to track.
                $currentRemaining  = $bill->remaining_balance !== null ? (float) $bill->remaining_balance : 0;
                $restoredRemaining = round($currentRemaining + $undoneAmount, 2);

                if ($restoredRemaining >= (float) $bill->amount) {
                    $restoredRemaining = null;
                }

                $bill->update([
                    'remaining_balance' => $restoredRemaining,
                    'last_paid_date'    => $prevPayment?->paid_at?->toDateString(),
                ]);

                return;
            }

            // A full payment had advanced the schedule; undoing it rolls the due
            // date back to the cycle that payment settled.
            $bill->update([
                'remaining_balance' => null,
                'last_paid_date'    => $prevPayment?->paid_at?->toDateString(),
                'next_due_date'     => $paidAt?->toDateString(),
            ]);
        });
    }

    private function authorizeView(Bill $bill): void
    {
        $user = request()->user();
        $ok   = $user instanceof \App\Models\User
             && ($bill->created_by === $user->id
             || ($bill->is_shared && $bill->family_id === $user->family_id));
        abort_unless($ok, 403, 'Access denied.');
    }

    /**
     * Editing a bill is open to whoever can see it.
     *
     * This used to require `isFamilyAdmin()`, which meant a plain family member
     * could open a shared bill and then be refused when editing it, deleting it
     * or setting this period's amount. Sharing a bill with the household is the
     * decision to let the household manage it; the app has no separate notion of
     * read-only members outside the admin section.
     */
    private function authorizeEdit(Bill $bill): void
    {
        $this->authorizeView($bill);
    }
}

