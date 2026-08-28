<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'name', 'description', 'category_id', 'provider_id', 'assigned_to', 'amount', 'current_amount', 'cost_varies',
        'remaining_balance', 'currency_code', 'default_account_id',
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

    // Provide guarded media collection registration (no-op when medialibrary not installed)
    public function registerMediaCollections(): void
    {
        if (!method_exists($this, 'addMediaCollection')) return;
        $this->addMediaCollection('receipts')->useDisk(config('medialibrary.disk_name', config('filesystems.default', 'public')));
    }

    public function registerMediaConversions($media = null): void
    {
        if (!method_exists($this, 'addMediaConversion')) return;
        $this->addMediaConversion('thumb')
            ->fit('crop', 600, 400)
            ->performOnCollections('receipts');
    }

    // Scopes
    public function scopeForUser($query, ?User $user)
    {
        if (! $user) return $query->whereRaw('1=0');

        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
              ->orWhere(function ($q2) use ($user) {
                  $q2->where('is_shared', true)
                     ->where('family_id', $user->family_id);
              });
        });
    }

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
        if (! $user) {
            return 0;
        }

        return static::forUser($user)->active()->overdue()
            ->whereNotNull('next_due_date')
            ->get(['id', 'is_active', 'frequency', 'next_due_date', 'last_paid_date', 'remaining_balance'])
            ->filter(fn (self $bill) => $bill->status() === 'overdue')
            ->count();
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

        $date = Carbon::parse($this->next_due_date);
        $freq = $this->frequency ?? 'monthly';
        $interval = (int) ($this->frequency_interval ?? 1);

        return match ($freq) {
            'once' => null,
            'daily' => $date->addDays(1 * $interval),
            'weekly' => $date->addWeeks(1 * $interval),
            'biweekly' => $date->addWeeks(2 * $interval),
            'monthly' => $date->addMonths(1 * $interval),
            'quarterly' => $date->addMonths(3 * $interval),
            'yearly' => $date->addYears(1 * $interval),
            default => $date->addMonths(1 * $interval),
        };
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
        if (!$this->start_date) return [];

        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = $this->end_date ? Carbon::parse($this->end_date)->endOfDay() : null;

        // If the bill ends before our from or starts after to, nothing to return
        if ($end && $end->lt($from)) return [];
        if ($start->gt($to)) return [];

        // Determine the first occurrence on/after $from
        $current = $start->copy();

        // If start before 'from', advance until >= from
        while ($current->lt($from)) {
            $next = $this->advanceDate($current);
            if (!$next) return [];
            // Avoid infinite loops
            if ($next->eq($current)) break;
            $current = $next;
            // stop if we passed end
            if ($end && $current->gt($end)) return [];
        }

        $occurrences = [];

        // Recursive closure to collect occurrences
        $collect = function (Carbon $dt) use (&$collect, $to, $end, &$occurrences) {
            if ($dt->gt($to)) return;
            if ($end && $dt->gt($end)) return;
            $occurrences[] = $dt->copy();
            $next = $this->advanceDate($dt);
            if (!$next) return;
            // Avoid infinite loops
            if ($next->lte($dt)) return;
            $collect($next);
        };

        $collect($current);

        return $occurrences;
    }

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
        $interval = (int)($this->frequency_interval ?? 1);

        return match ($freq) {
            'once' => null,
            'daily' => $date->copy()->addDays(1 * $interval),
            'weekly' => $date->copy()->addWeeks(1 * $interval),
            'biweekly' => $date->copy()->addWeeks(2 * $interval),
            'monthly' => $date->copy()->addMonths(1 * $interval),
            'quarterly' => $date->copy()->addMonths(3 * $interval),
            'yearly' => $date->copy()->addYears(1 * $interval),
            default => $date->copy()->addMonths(1 * $interval),
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
    public function isCurrentCyclePaid(): bool
    {
        if (! $this->last_paid_date || $this->hasPartialPayment()) {
            return false;
        }

        if ($this->frequency === 'once') {
            return true;
        }

        return $this->next_due_date && Carbon::parse($this->next_due_date)->gt(Carbon::today());
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

    // Helper to get receipt urls (if medialibrary installed)
    public function receiptUrls(): array
    {
        if (method_exists($this, 'getMedia') && $this->hasMedia('receipts')) {
            return $this->getMedia('receipts')->map(fn($m) => $m->getUrl())->toArray();
        }
        return [];
    }
}
