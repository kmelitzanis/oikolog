<?php

namespace App\Models;

use App\Models\Concerns\SharedWithFamily;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Bill extends Model implements HasMedia
{
    use HasUlids, HasFactory, SharedWithFamily, InteractsWithMedia;

    /** What a receipt may be: a photo of it or the PDF the provider sent. */
    public const RECEIPT_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];

    protected $fillable = [
        'name', 'description', 'category_id', 'provider_id', 'assigned_to', 'amount', 'current_amount', 'cost_varies',
        'remaining_balance', 'debt_remaining', 'debt_initial', 'currency_code', 'default_account_id',
        'frequency', 'frequency_interval', 'start_date', 'end_date', 'next_due_date',
        'last_paid_date', 'is_active', 'is_shared', 'notify_enabled', 'notify_days_before',
        'url', 'notes', 'created_by', 'family_id', 'created_at', 'updated_at', 'created_by'
    ];

    protected function casts(): array
    {
        return [
            'amount'             => 'decimal:2',
            'current_amount'    => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'debt_remaining'    => 'decimal:2',
            'debt_initial'      => 'decimal:2',
            'cost_varies' => 'boolean',
            'start_date'         => 'date',
            'end_date'           => 'date',
            'next_due_date'      => 'date',
            'last_paid_date'     => 'date',
            'is_active'          => 'boolean',
            'is_shared'          => 'boolean',
            'notify_enabled'     => 'boolean',
            'notify_days_before' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The money a payment took out of an account really left it. Deleting
        // a bill cascades its payments, and through them their ledger rows,
        // which silently put that money back into the balance — so the
        // movements are detached from the payments and kept.
        static::deleting(function (self $bill) {
            AccountTransaction::whereIn('payment_id', $bill->payments()->select('id'))
                ->update(['payment_id' => null]);
        });
    }

    // Relations
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function defaultIncome(): BelongsTo
    {
        return $this->belongsTo(Income::class, 'default_account_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Amounts parsed out of provider mail, awaiting review. */
    public function amountSuggestions(): HasMany
    {
        return $this->hasMany(BillAmountSuggestion::class);
    }

    /**
     * Receipts live on the private disk: they are financial documents, and
     * the public disk would hand them to anyone holding the URL. They are
     * served through BillController::showReceipt(), behind the bill's own
     * access check.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipts')
            ->useDisk('local')
            ->acceptsMimeTypes(self::RECEIPT_MIME_TYPES);
    }

    // Scopes
    // forUser() comes from SharedWithFamily.

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Bills whose due date has passed. Date-only, so it says nothing about
     * whether the bill was paid — use it to narrow rows, then decide with
     * status(). See `overdueCountFor()`.
     */
    public function scopeOverdue($query)
    {
        return $query->whereDate('next_due_date', '<', Carbon::today());
    }

    /**
     * How many of the user's bills are genuinely overdue — money owed, past due.
     *
     * Sidebar badges and the like need this on every page, so the query narrows
     * to past-due active bills first (usually a handful) and only then asks
     * status(), which is the only thing that accounts for payments.
     */
    public static function overdueCountFor(?User $user): int
    {
        return count(static::overdueIdsFor($user));
    }

    /** @return array<int, string> */
    public static function overdueIdsFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return static::forUser($user)->active()->overdue()
            ->whereNotNull('next_due_date')
            ->get(['id', 'is_active', 'frequency', 'next_due_date', 'last_paid_date', 'remaining_balance'])
            ->filter(fn (self $bill) => $bill->status() === 'overdue')
            ->pluck('id')
            ->all();
    }

    public function scopeDueWithin($query, int $days)
    {
        return $query->whereBetween('next_due_date', [Carbon::today(), Carbon::today()->addDays($days)]);
    }

    // Helpers
    public function monthlyEquivalent(): float
    {
        $amount = $this->periodAmount();
        $freq = $this->frequency ?? 'monthly';
        $interval = (int) ($this->frequency_interval ?? 1);

        return match ($freq) {
            'once' => $amount,
            'daily' => $amount * 30 * $interval,
            'weekly' => $amount * 4.345 * $interval,
            'biweekly' => $amount * 2.1725 * $interval,
            'monthly' => $amount * $interval,
            'quarterly' => ($amount * $interval) / 3,
            'yearly' => ($amount * $interval) / 12,
            default => $amount,
        };
    }

    public function isOverdue(): bool
    {
        return $this->next_due_date && Carbon::parse($this->next_due_date)->lt(Carbon::today());
    }

    public function daysUntilDue(): ?int
    {
        if (! $this->next_due_date) return null;
        return Carbon::today()->diffInDays(Carbon::parse($this->next_due_date), false);
    }

    public function calculateNextDueDate(): ?Carbon
    {
        if (! $this->next_due_date) return null;

        return $this->advanceDate(Carbon::parse($this->next_due_date));
    }

    /**
     * Return an array of occurrence dates (Carbon) for this bill between $from and $to inclusive.
     * This works for both expenses and incomes (no sign change here).
     * It follows the bill recurrence (frequency + frequency_interval) and honors start_date and end_date.
     * Implemented using recursion to step occurrences.
     *
     * @param \Carbon\Carbon $from
     * @param \Carbon\Carbon $to
     * @return array<int, \Carbon\Carbon>
     */
    public function occurrencesBetween(Carbon $from, Carbon $to): array
    {
        return self::scheduleBetween(
            $this->start_date ? Carbon::parse($this->start_date) : null,
            $this->end_date ? Carbon::parse($this->end_date) : null,
            $from,
            $to,
            fn (Carbon $date) => $this->advanceDate($date),
        );
    }

    /**
     * Walk a schedule from its start and collect the dates inside [$from, $to].
     *
     * A plain loop with a hard cap: this used to recurse once per occurrence,
     * so a daily schedule over a long window could exhaust the stack, and a
     * window of any size was accepted.
     *
     * @param  callable(Carbon): ?Carbon  $advance
     * @return array<int, Carbon>
     */
    public static function scheduleBetween(?Carbon $start, ?Carbon $end, Carbon $from, Carbon $to, callable $advance): array
    {
        if (! $start) return [];

        $current = $start->copy()->startOfDay();
        $end = $end?->copy()->endOfDay();

        if (($end && $end->lt($from)) || $current->gt($to)) return [];

        $occurrences = [];

        for ($steps = 0; $steps < self::MAX_SCHEDULE_STEPS; $steps++) {
            if ($current->gt($to) || ($end && $current->gt($end))) {
                break;
            }

            if ($current->gte($from)) {
                $occurrences[] = $current->copy();
            }

            $next = $advance($current);
            if (! $next || $next->lte($current)) {
                break;
            }
            $current = $next;
        }

        return $occurrences;
    }

    /** Enough for a daily schedule started decades ago; a guard, not a feature. */
    private const MAX_SCHEDULE_STEPS = 50_000;

    /**
     * Snap next_due_date back onto the recurrence schedule that is anchored at
     * start_date. Call this whenever the schedule itself changes (frequency,
     * frequency_interval or start_date) so the stored due date can't drift onto
     * an old cadence — e.g. a bill switched from monthly to quarterly would
     * otherwise keep its monthly next_due_date and appear to recur every month.
     *
     * The new next_due_date becomes the first scheduled occurrence that has not
     * yet been covered by a payment (or start_date itself when unpaid).
     */
    public function realignNextDueDate(): void
    {
        if (! $this->start_date) return;

        $occurrence = Carbon::parse($this->start_date)->startOfDay();

        // Skip periods already covered by the most recent payment.
        $paidThrough = $this->last_paid_date
            ? Carbon::parse($this->last_paid_date)->startOfDay()
            : null;

        if ($paidThrough && $paidThrough->gte($occurrence)) {
            while ($occurrence->lte($paidThrough)) {
                $next = $this->advanceDate($occurrence);
                if (! $next || $next->lte($occurrence)) break;
                $occurrence = $next;
            }
        }

        $this->next_due_date = $occurrence->toDateString();
    }

    /**
     * Advance a Carbon date according to this bill's frequency + interval.
     * Returns a new Carbon instance or null for non-recurring (once).
     */
    private function advanceDate(Carbon $date): ?Carbon
    {
        $freq = $this->frequency ?? 'monthly';
        $interval = max(1, (int) ($this->frequency_interval ?? 1));

        return match ($freq) {
            'once' => null,
            'daily' => $date->copy()->addDays(1 * $interval),
            'weekly' => $date->copy()->addWeeks(1 * $interval),
            'biweekly' => $date->copy()->addWeeks(2 * $interval),
            'quarterly' => self::addMonthsOnDay($date, 3 * $interval, $this->anchorDay($date)),
            'yearly' => self::addMonthsOnDay($date, 12 * $interval, $this->anchorDay($date)),
            default => self::addMonthsOnDay($date, 1 * $interval, $this->anchorDay($date)),
        };
    }

    /** The day of the month the schedule was set up on. */
    private function anchorDay(Carbon $fallback): int
    {
        return $this->start_date ? Carbon::parse($this->start_date)->day : $fallback->day;
    }

    /**
     * Move $months months on, landing on $day — or the month's last day when
     * it is shorter.
     *
     * Carbon's addMonths() overflows: 31 January plus a month is 3 March, and
     * from then on the bill fell due on the 3rd for good. Clamping to the
     * month, and re-reading the day from the schedule's start each time, keeps
     * a bill set up for the 31st on the last day of every month.
     */
    public static function addMonthsOnDay(Carbon $date, int $months, int $day): Carbon
    {
        $target = $date->copy()->startOfMonth()->addMonthsNoOverflow($months);

        return $target->day(min($day, $target->daysInMonth));
    }

    /** The inverse of advanceDate(): the due date one cycle earlier. */
    private function retreatDate(Carbon $date): ?Carbon
    {
        $interval = (int)($this->frequency_interval ?? 1);

        return match ($this->frequency ?? 'monthly') {
            'once' => null,
            'daily' => $date->copy()->subDays($interval),
            'weekly' => $date->copy()->subWeeks($interval),
            'biweekly' => $date->copy()->subWeeks(2 * $interval),
            'quarterly' => $date->copy()->subMonthsNoOverflow(3 * $interval),
            'yearly' => $date->copy()->subYearsNoOverflow($interval),
            default => $date->copy()->subMonthsNoOverflow($interval),
        };
    }

    /** Returns true if there is an outstanding partial balance for the current cycle. */
    public function hasPartialPayment(): bool
    {
        return $this->remaining_balance !== null;
    }

    /**
     * The bill's single status vocabulary — the one place that decides whether a
     * bill reads as paid, partial, overdue, soon, upcoming or inactive.
     *
     * This used to be recomputed inline in bills/index, bills/show and the
     * calendar `events()` endpoint, and the copies had drifted: the list tinted
     * a row green off `last_paid_date` (true forever once any payment existed)
     * while the Pay button keyed off the *current cycle*, so a recurring bill
     * could render green and still offer "Mark as paid". Callers must use this.
     *
     * Returns one of: paid · partial · overdue · soon · upcoming · inactive.
     */
    /**
     * The order the statuses are worth a person's attention in.
     *
     * Money owed and late comes first, settled work last. Used to group the
     * list, so the ranking lives next to `status()` rather than being restated
     * by every caller that wants to sort by urgency.
     */
    public const STATUS_ORDER = ['overdue', 'partial', 'soon', 'upcoming', 'paid', 'inactive'];

    /** Position of this bill's status in STATUS_ORDER; unknown states sort last. */
    public function statusRank(): int
    {
        $i = array_search($this->status(), self::STATUS_ORDER, true);

        return $i === false ? count(self::STATUS_ORDER) : $i;
    }

    public function status(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        // A partial balance outranks the date entirely: money is still owed on
        // this cycle, and that is the more useful thing to say about the bill.
        if ($this->hasPartialPayment()) {
            return 'partial';
        }

        if ($this->isCurrentCyclePaid()) {
            return 'paid';
        }

        $days = $this->daysUntilDue();

        return match (true) {
            $days === null => 'upcoming',
            $days < 0      => 'overdue',
            $days <= 7     => 'soon',
            default        => 'upcoming',
        };
    }

    /**
     * Whether the *current* billing cycle is settled.
     *
     * For one-off bills any payment settles them for good. For recurring bills a
     * payment advances `next_due_date`, so the cycle counts as paid only while
     * that date is still ahead of us — once it comes due again the bill is owed
     * anew, however recently it was last paid.
     */
    /**
     * Whether this bill happens exactly once rather than on a schedule.
     *
     * A one-off has no rhythm to average out, so anything phrased "per month"
     * — the monthly equivalent above all — is meaningless for it and should be
     * left off rather than printed as a figure that reads like a subscription.
     */
    public function isOneOff(): bool
    {
        return $this->frequency === 'once';
    }

    public function isCurrentCyclePaid(): bool
    {
        if (! $this->last_paid_date || $this->hasPartialPayment()) {
            return false;
        }

        if ($this->frequency === 'once') {
            return true;
        }

        if (! $this->next_due_date) {
            return false;
        }

        $next = Carbon::parse($this->next_due_date);

        // Bills that come round once a month or less are thought of per
        // month: on the 1st, everything due this month is owed again, even
        // if its due date is still weeks away. Paying it (early or not)
        // pushes the due date past the month, and only then is it paid.
        if ($this->recursMonthlyOrLonger()) {
            return $next->gt(Carbon::today()->endOfMonth());
        }

        // Shorter cycles would never read as paid under that rule, so they
        // keep the plain one: paid while the next due date is still ahead.
        return $next->gt(Carbon::today());
    }

    private function recursMonthlyOrLonger(): bool
    {
        return in_array($this->frequency ?? 'monthly', ['monthly', 'quarterly', 'yearly'], true);
    }

    /**
     * Whether the bill belongs on the "this month" list for the month
     * containing $at: still owed by the month's end (due in it, or overdue
     * from before), due in it but already paid — including paid early, at
     * the end of the previous month — or paid during it.
     *
     * Payments are read from the loaded `payments` relation when present,
     * so callers listing many bills should eager-load them for the month.
     */
    public function belongsToMonth(?Carbon $at = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $at    = $at ?? Carbon::now();
        $start = $at->copy()->startOfMonth();
        $end   = $at->copy()->endOfMonth();

        if ($this->next_due_date && Carbon::parse($this->next_due_date)->lte($end)) {
            return true;
        }

        // The due date has moved past this month. If the cycle before it fell
        // inside the month, that is this month's bill, paid already (perhaps
        // early, on the 31st) — it still belongs here.
        if ($this->next_due_date && ($previous = $this->retreatDate(Carbon::parse($this->next_due_date)))
            && $previous->between($start, $end)) {
            return true;
        }

        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();

        return $payments->contains(fn ($p) => $p->paid_at && $p->paid_at->between($start, $end));
    }

    /** IDs of the user's bills that belong on this month's list. */
    public static function thisMonthIdsFor(?User $user): array
    {
        $start = Carbon::now()->startOfMonth();
        $end   = Carbon::now()->endOfMonth();

        return static::forUser($user)->active()
            ->with(['payments' => fn ($q) => $q->whereBetween('paid_at', [$start, $end])])
            ->get()
            ->filter(fn (self $bill) => $bill->belongsToMonth())
            ->pluck('id')
            ->all();
    }

    /**
     * Whether this bill is working towards a total — a loan or a card balance
     * rather than a subscription that simply recurs.
     */
    public function tracksDebt(): bool
    {
        return $this->debt_remaining !== null;
    }

    /** Whether the debt is cleared, so the schedule has run its course. */
    public function isPaidOff(): bool
    {
        return $this->tracksDebt() && (float) $this->debt_remaining <= 0.0;
    }

    /**
     * What to actually take for this cycle.
     *
     * Normally the period's amount, but the final instalment of a loan is
     * whatever is left — asking for the usual 200 against a 43.17 balance would
     * overpay the debt and leave it negative.
     */
    public function amountDueNow(): float
    {
        $due = $this->getEffectiveRemainingBalance();

        if (! $this->tracksDebt()) {
            return $due;
        }

        return min($due, max(0.0, (float) $this->debt_remaining));
    }

    /** How much of the original debt has been cleared, as a percentage. */
    public function debtProgress(): ?int
    {
        if (! $this->tracksDebt() || ! $this->debt_initial || (float) $this->debt_initial <= 0) {
            return null;
        }

        $paid = (float) $this->debt_initial - (float) $this->debt_remaining;

        return (int) round(min(100, max(0, $paid / (float) $this->debt_initial * 100)));
    }

    /** Whether the current cycle is fully settled. */
    public function isSettled(): bool
    {
        return $this->status() === 'paid';
    }

    /**
     * Whether the bill is asking for money right now — overdue, part-paid, or
     * due within the week. Prefer this over `isOverdue()` for any "needs
     * action" list: `isOverdue()` only compares dates and reads true for a
     * bill that has already been paid.
     */
    public function needsAttention(): bool
    {
        return in_array($this->status(), ['overdue', 'partial', 'soon'], true);
    }

    /**
     * What this billing cycle costs.
     *
     * For most bills that is simply `amount`. For one whose cost varies it is
     * whatever the provider billed this period, once someone has entered it —
     * `amount` is only ever an estimate there.
     */
    public function periodAmount(): float
    {
        return (float) ($this->current_amount ?? $this->amount);
    }

    /** Whether this cycle's real cost is known yet. */
    public function hasCurrentAmount(): bool
    {
        return $this->current_amount !== null;
    }

    /**
     * Whether the amount shown for this bill is still a guess — a varying bill
     * nobody has entered this period's invoice for.
     */
    public function amountIsUnknown(): bool
    {
        return $this->cost_varies && ! $this->hasCurrentAmount();
    }

    /** Returns the amount still owed for the current billing cycle. */
    public function getEffectiveRemainingBalance(): float
    {
        return $this->remaining_balance !== null
            ? (float)$this->remaining_balance
            : $this->periodAmount();
    }

    /**
     * What the pay modal needs to open on this bill, as one array that every
     * page passes through Js::from(). Three views used to assemble it by
     * hand and had drifted: the dashboard ignored partial balances and this
     * cycle's figure, and amounts were formatted with a thousands separator,
     * so "1,250.00" reached the modal's maths as 1.
     */
    public function payModalPayload(): array
    {
        $lastPayment = $this->relationLoaded('payments')
            ? $this->payments->sortByDesc('paid_at')->first()
            : $this->payments()->latest('paid_at')->first();

        $amount = $this->tracksDebt()
            ? min($this->periodAmount(), max(0.0, (float) $this->debt_remaining))
            : $this->periodAmount();

        $known = $this->hasCurrentAmount() ? (float) $this->current_amount : ($lastPayment ? (float) $lastPayment->amount : null);

        return [
            'billName'         => $this->name,
            'amount'           => number_format($amount, 2, '.', ''),
            'currency'         => $this->currency_code,
            'payRoute'         => route('bills.pay', $this),
            'costVaries'       => (bool) $this->cost_varies,
            'defaultAccountId' => $this->default_account_id ?? '',
            'lastPaidAmount'   => $this->cost_varies && $known !== null ? number_format($known, 2, '.', '') : '',
            'remainingBalance' => $this->hasPartialPayment()
                ? number_format($this->getEffectiveRemainingBalance(), 2, '.', '')
                : null,
        ];
    }

    /**
     * The bill's receipts, ready for a view: where to open each one, what to
     * call it, and whether it can be shown as a picture.
     *
     * @return array<int, array{id: int, url: string, name: string, is_image: bool}>
     */
    public function receiptItems(): array
    {
        return $this->getMedia('receipts')->map(fn ($media) => [
            'id'       => $media->id,
            'url'      => route('bills.receipts.show', [$this, $media->id]),
            'name'     => $media->name,
            'is_image' => str_starts_with((string) $media->mime_type, 'image/'),
        ])->all();
    }
}
