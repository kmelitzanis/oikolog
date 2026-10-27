<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Provider;
use App\Services\BillPayments;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        // Only the latest payment is shown per row; loading every bill's whole
        // history for it grew with every month the app was used.
        $query = Bill::with(['category', 'provider', 'latestPayment.account'])
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
            // Decided by status(), like the sidebar badge: a paid one-off keeps
            // its past due date and must not read as overdue.
            'overdue'    => $query->whereIn('id', $overdueIds = Bill::overdueIdsFor($user)),
            // Due this month (paid or not), overdue from before, or paid
            // this month. Decided per bill — see Bill::belongsToMonth().
            'this_month' => $query->whereIn('id', $thisMonthIds = Bill::thisMonthIdsFor($user)),
            'shared'     => $query->where('is_shared', true),
            'inactive'   => $query->where('is_active', false),
            default      => null,
        };

        $bills = $query->paginate(50);

        // Group the page by urgency, keeping the due-date order inside each
        // group. Sorting here rather than in SQL because `status()` is derived
        // from partial payments and today's date, neither of which is a column.
        $bills->setCollection(
            $bills->getCollection()
                ->sortBy([
                    fn(Bill $a, Bill $b) => $a->statusRank() <=> $b->statusRank(),
                    fn(Bill $a, Bill $b) => ($a->next_due_date?->timestamp ?? PHP_INT_MAX)
                        <=> ($b->next_due_date?->timestamp ?? PHP_INT_MAX),
                ])
                ->values()
        );

        // Counts for the filter pills — computed off the unfiltered set so the
        // pills keep showing the full picture while a filter is applied.
        $all = Bill::forUser($user)->get(['is_active', 'is_shared', 'next_due_date']);
        $billCounts = [
            'all'        => $all->count(),
            'overdue'    => count($overdueIds ?? Bill::overdueIdsFor($user)),
            'this_month' => count($thisMonthIds ?? Bill::thisMonthIdsFor($user)),
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
        // Files are handled on their own below, not mass-assigned.
        $data = Arr::except($request->validate($this->billRules($request)), ['receipts']);

        $bill = Bill::create([
            ...$data,
            'currency_code' => $request->user()->currency_code,
            'cost_varies' => (bool)($data['cost_varies'] ?? false),
            'debt_remaining' => $data['debt_remaining'] ?? null,
            // Recorded once so the bill can show how far along it is.
            'debt_initial'   => $data['debt_remaining'] ?? null,
            // Sharing needs someone to share with.
            'is_shared'      => $shared = (bool) ($data['is_shared'] ?? false) && $request->user()->family_id,
            'notify_enabled' => (bool) ($data['notify_enabled'] ?? false),
            'created_by'     => $request->user()->id,
            'family_id'      => $shared ? $request->user()->family_id : null,
            'next_due_date'  => $data['start_date'],
        ]);

        $this->storeReceipts($request, $bill);

        return redirect()->route('bills.show', $bill)->with('success', __('messages.bill_created'));
    }

    public function show(Bill $bill)
    {
        $this->authorizeView($bill);
        $bill->load(['category', 'provider']);
        $payments = $bill->payments()->with(['paidBy', 'account'])->orderByDesc('paid_at')->get();
        // One query for both the history and the pay modal's last amount.
        $bill->setRelation('payments', $payments);

        return view('bills.show', compact('bill', 'payments'));
    }

    // Calendar view
    public function calendar()
    {
        return view('calendar.index');
    }

    // Events for the calendar — returns bills + incomes
    public function events(Request $request)
    {
        $user = $request->user();
        [$start, $end] = $this->eventWindow($request);
        $today = Carbon::today();

        // ── Bills ─────────────────────────────────────────────────────────────
        $bills = Bill::forUser($user)->whereNotNull('next_due_date')
            ->with(['category', 'provider', 'payments' => function ($q) use ($start, $end) {
                $q->whereBetween('paid_at', [$start, $end]);
            }])->get();

        $billEvents = collect();

        foreach ($bills as $b) {
            $nextDue = $b->next_due_date?->copy()->startOfDay();

            foreach ($b->occurrencesBetween($start, $end) as $date) {
                // A cycle is settled once the schedule has moved past it (a full
                // payment advances next_due_date), a one-off once it is paid,
                // or when a payment landed on the day itself. Matching only the
                // payment day, as before, missed every bill paid a day early.
                $isPaid = ($b->last_paid_date && $nextDue && $date->lt($nextDue))
                    || ($b->isOneOff() && $b->isCurrentCyclePaid())
                    || $b->payments->contains(fn ($p) => $p->paid_at?->isSameDay($date));

                // A retired bill (a paid-off loan) has no future to show.
                if (! $b->is_active && ! $isPaid) {
                    continue;
                }

                // Due today is not overdue yet — `isPast()` said it was from
                // one second past midnight.
                $isOverdue = ! $isPaid && $date->lt($today);
                // diffInDays() against now() was negative for every future
                // date, so the whole future read as "due soon".
                $isSoon = ! $isPaid && ! $isOverdue && $date->lte($today->copy()->addDays(7));

                $color = match (true) {
                    $isPaid    => '#10b981',
                    $isOverdue => '#ef4444',
                    $isSoon    => '#f97316',
                    // The same blue as the list's "upcoming" group and the
                    // legend; a category colour here read as a status (a red
                    // "Rent" dot looked overdue).
                    default    => '#60a5fa',
                };

                $billEvents->push([
                    'id' => 'bill-' . $b->id . '-' . $date->timestamp,
                    'title' => '• ' . $b->name,
                    'start' => $date->toDateString(),
                    'allDay' => true,
                    'url' => route('bills.show', $b),
                    'color' => $color,
                    'extendedProps' => [
                        'type' => 'bill',
                        'amount' => $b->currency_code . ' ' . number_format($b->periodAmount(), 2),
                        'overdue' => $isOverdue,
                        'paid' => $isPaid,
                        'soon' => $isSoon,
                        'provider' => $b->provider?->name ?? '',
                    ],
                ]);
            }
        }

        // ── Incomes ───────────────────────────────────────────────────────────
        $incomes = \App\Models\Income::forUser($user)->active()
            ->whereNotNull('next_date')
            ->whereBetween('next_date', [$start->toDateString(), $end->toDateString()])
            ->get();

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

    /**
     * The date range a calendar request asks for, parsed defensively.
     *
     * Bad input falls back to this month instead of a 500, and the window is
     * capped: a calendar page never shows more than six weeks, and an
     * unbounded range made the server enumerate every occurrence in it.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function eventWindow(Request $request): array
    {
        $parse = function ($value, Carbon $fallback): Carbon {
            if (! is_string($value) || $value === '') {
                return $fallback;
            }

            try {
                // A "+" in a timezone offset arrives as a space when unencoded.
                return Carbon::parse(str_replace(' ', '+', $value));
            } catch (\Throwable $e) {
                return $fallback;
            }
        };

        $start = $parse($request->query('start'), now()->startOfMonth())->startOfDay();
        $end = $parse($request->query('end'), $start->copy()->endOfMonth())->endOfDay();

        if ($end->lt($start) || $start->diffInDays($end) > 62) {
            $end = $start->copy()->addDays(62)->endOfDay();
        }

        return [$start, $end];
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

        // Files are handled on their own below, not mass-assigned.
        $data = Arr::except($request->validate($this->billRules($request)), ['receipts']);

        $data['cost_varies'] = (bool)($data['cost_varies'] ?? false);
        $data['is_shared']      = (bool) ($data['is_shared'] ?? false) && $request->user()->family_id;
        $data['notify_enabled'] = (bool) ($data['notify_enabled'] ?? false);

        // The balance goes through setDebtRemaining(), which keeps what was
        // already paid off. Untouched, it is left alone so that saving the
        // form does not recompute anything.
        $debt = isset($data['debt_remaining']) ? round((float) $data['debt_remaining'], 2) : null;
        unset($data['debt_remaining']);

        $data['family_id'] = $data['is_shared'] ? $request->user()->family_id : null;

        // Detect whether the recurrence schedule itself changed so we can snap
        // next_due_date back onto the new cadence (otherwise it keeps drifting
        // on the old frequency and appears to recur too often).
        $scheduleChanged = $bill->frequency !== $data['frequency']
            || optional($bill->start_date)->toDateString() !== \Carbon\Carbon::parse($data['start_date'])->toDateString();

        $bill->fill($data);
        if ($debt !== ($bill->debt_remaining === null ? null : (float) $bill->debt_remaining)) {
            $bill->setDebtRemaining($debt);
        }
        if ($scheduleChanged) {
            $bill->realignNextDueDate();
        }
        $bill->save();

        $this->storeReceipts($request, $bill);

        return redirect()->route('bills.show', $bill)->with('success', __('messages.bill_updated'));
    }

    /**
     * Change what is still owed on a loan or a card, from the bill page:
     * the limit went up, there was a new drawdown, the statement says
     * otherwise. Payments made so far keep counting as paid.
     */
    public function updateDebt(Request $request, Bill $bill)
    {
        $this->authorizeEdit($bill);

        $data = $request->validate([
            'debt_remaining' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $bill->setDebtRemaining((float) $data['debt_remaining']);
        $bill->save();

        return back()->with('success', __('messages.debt_updated'));
    }

    public function destroy(Bill $bill)
    {
        $this->authorizeEdit($bill);

        // Account movements survive — see Bill::booted().
        DB::transaction(fn () => $bill->delete());

        return redirect()->route('bills.index')->with('success', __('messages.bill_deleted'));
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

    public function markPaid(Request $request, Bill $bill, BillPayments $payments)
    {
        $this->authorizeView($bill);

        $data = $request->validate($payments->rules($request->user()));

        [, $isPartial] = $payments->pay($bill, $request->user(), $data);

        if ($request->wantsJson() || $request->ajax()) {
            $bill->refresh();
            return response()->json([
                'status' => $isPartial ? 'partial' : 'paid',
                'remaining_balance' => $bill->remaining_balance,
                'last_paid_date' => $bill->last_paid_date?->toDateString(),
                'next_due_date' => $bill->next_due_date?->toDateString(),
                'message' => __($isPartial ? 'messages.partial_payment_recorded' : 'messages.payment_recorded'),
            ]);
        }

        $response = back()
            ->with('success', __($isPartial ? 'messages.partial_payment_recorded' : 'messages.payment_recorded'));

        // `undo_route` drives the toast's Undo action. Undo is offered only on
        // the bill's own page — from the list it was too easy to hit by mistake.
        if (strtok(url()->previous(), '?') === route('bills.show', $bill)) {
            $response->with('undo_route', route('bills.unpay', $bill));
        }

        return $response;
    }

    public function undoLastPayment(Bill $bill, BillPayments $payments)
    {
        $this->authorizeView($bill);

        $lastPayment = $bill->payments()->latest('paid_at')->first();

        if (!$lastPayment) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['status' => 'none', 'message' => 'No payment to undo.'], 422);
            }
            return back()->with('error', __('messages.no_payment_to_undo'));
        }

        $payments->remove($bill, $lastPayment);

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
    public function destroyPayment(Bill $bill, Payment $payment, BillPayments $payments)
    {
        $this->authorizeView($bill);

        // Route-model binding resolves {payment} globally, so confirm it really
        // belongs to this bill before deleting anything.
        abort_unless($payment->bill_id === $bill->id, 404);

        $payments->remove($bill, $payment);

        return back()->with('success', __('messages.payment_deleted'));
    }

    /** Open a receipt — only for someone who can see the bill. */
    public function showReceipt(Bill $bill, int $receipt)
    {
        $this->authorizeView($bill);
        $media = $this->findReceipt($bill, $receipt);

        $headers = [
            'Content-Type'           => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=3600',
        ];

        return response()->file($media->getPath(), $headers);
    }

    public function destroyReceipt(Bill $bill, int $receipt)
    {
        $this->authorizeEdit($bill);
        $this->findReceipt($bill, $receipt)->delete();

        return back()->with('success', __('messages.receipt_deleted'));
    }

    /** The receipt with this id, if it really belongs to this bill. */
    private function findReceipt(Bill $bill, int $id): \Spatie\MediaLibrary\MediaCollections\Models\Media
    {
        $media = $bill->getMedia('receipts')->firstWhere('id', $id);
        abort_unless($media, 404);

        return $media;
    }

    /**
     * Attach uploaded receipts. They used to be thrown away without a word:
     * the bill had never been set up to hold media.
     *
     * The stored name is random and the extension comes from the file's
     * content, never from what the browser called it.
     */
    private function storeReceipts(Request $request, Bill $bill): void
    {
        foreach ((array) $request->file('receipts', []) as $file) {
            $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $bill->addMedia($file)
                ->usingName(Str::limit($original !== '' ? $original : __('messages.receipts'), 100, ''))
                ->usingFileName(Str::random(40) . '.' . ($file->guessExtension() ?: 'bin'))
                ->toMediaCollection('receipts');
        }
    }

    /** Validation shared by create and edit. */
    private function billRules(Request $request): array
    {
        return [
            'name'               => ['required', 'string', 'max:120'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'category_id'        => ['required', 'exists:categories,id'],
            'provider_id'        => ['nullable', 'exists:providers,id'],
            // Only an account this user can see — the id comes from the form.
            'default_account_id' => ['nullable', 'string', function (string $attribute, $value, \Closure $fail) use ($request) {
                if (! Account::forUser($request->user())->whereKey($value)->exists()) {
                    $fail(__('messages.invalid_selection'));
                }
            }],
            'amount'             => ['required', 'numeric', 'min:0', 'max:99999999'],
            'cost_varies'        => ['nullable', 'boolean'],
            // Optional: the total still owed on a loan or a card.
            'debt_remaining'     => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'frequency'          => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['nullable', 'date', 'after:start_date'],
            'is_shared'          => ['nullable'],
            'notify_enabled'     => ['nullable'],
            'notify_days_before' => ['nullable', 'integer', 'min:1', 'max:30'],
            // Rendered as a link, so nothing but the web.
            'url'                => ['nullable', 'url:http,https', 'max:2048'],
            'notes'              => ['nullable', 'string', 'max:5000'],
            'receipts'           => ['nullable', 'array', 'max:10'],
            'receipts.*'         => ['file', 'mimes:jpg,jpeg,png,webp,gif,pdf', 'max:10240'],
        ];
    }

    private function authorizeView(Bill $bill): void
    {
        abort_unless($bill->isVisibleTo(request()->user()), 403, 'Access denied.');
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

