<?php

namespace App\Models;

use App\Models\Concerns\SharedWithFamily;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Income extends Model
{
    use HasUlids, HasFactory, SharedWithFamily;

    protected $fillable = [
        'name', 'description', 'source', 'amount', 'currency_code',
        'frequency', 'frequency_interval', 'start_date', 'end_date', 'account_id',
        'next_date', 'last_received_date', 'last_expected_date', 'is_active', 'is_shared',
        'notes', 'created_by', 'family_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_date' => 'date',
            'last_received_date' => 'date',
            'last_expected_date' => 'date',
            'is_active' => 'boolean',
            'is_shared' => 'boolean',
            'frequency_interval' => 'integer',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────────
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /** Where this income lands when it is received. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** The deposits this source has produced. */
    public function deposits(): HasMany
    {
        return $this->hasMany(AccountTransaction::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────
    // forUser() comes from SharedWithFamily.

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    public function monthlyEquivalent(): float
    {
        $amount = (float)$this->amount;
        $freq = $this->frequency ?? 'monthly';
        $interval = (int)($this->frequency_interval ?? 1);
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

    public function calculateNextDate(): ?Carbon
    {
        if (!$this->next_date) return null;

        return $this->advanceDate(Carbon::parse($this->next_date));
    }

    /** @return Carbon[] */
    public function occurrencesBetween(Carbon $from, Carbon $to): array
    {
        return Bill::scheduleBetween(
            $this->start_date ? Carbon::parse($this->start_date) : null,
            $this->end_date ? Carbon::parse($this->end_date) : null,
            $from,
            $to,
            fn (Carbon $date) => $this->advanceDate($date),
        );
    }

    /** Same calendar arithmetic as bills — see Bill::addMonthsOnDay(). */
    private function advanceDate(Carbon $date): ?Carbon
    {
        $freq = $this->frequency ?? 'monthly';
        $interval = max(1, (int) ($this->frequency_interval ?? 1));
        $day = $this->start_date ? Carbon::parse($this->start_date)->day : $date->day;

        return match ($freq) {
            'once' => null,
            'daily' => $date->copy()->addDays(1 * $interval),
            'weekly' => $date->copy()->addWeeks(1 * $interval),
            'biweekly' => $date->copy()->addWeeks(2 * $interval),
            'quarterly' => Bill::addMonthsOnDay($date, 3 * $interval, $day),
            'yearly' => Bill::addMonthsOnDay($date, 12 * $interval, $day),
            default => Bill::addMonthsOnDay($date, 1 * $interval, $day),
        };
    }

    public function frequencyLabel(): string
    {
        return match ($this->frequency) {
            'once' => 'One-time',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'biweekly' => 'Bi-weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'yearly' => 'Yearly',
            default => ucfirst($this->frequency),
        };
    }

    public function daysUntilNext(): ?int
    {
        if (!$this->next_date) return null;
        return (int)now()->startOfDay()->diffInDays(Carbon::parse($this->next_date)->startOfDay(), false);
    }

    // ── Receipt cycle ──────────────────────────────────────────────────────────

    /** How many days before the expected date a receipt may be recorded. */
    public const EARLY_WINDOW_DAYS = 7;

    private function recursMonthlyOrLonger(): bool
    {
        return in_array($this->frequency ?? 'monthly', ['monthly', 'quarterly', 'yearly'], true);
    }

    /**
     * Whether this cycle's money has come in.
     *
     * Monthly-or-longer sources are thought of per month: the 1st resets them
     * to "expected", and recording the receipt moves `next_date` past the
     * month — that is what marks it received, however early it landed.
     * Shorter cycles keep the plain rule: received while the next date is
     * still ahead.
     */
    public function isReceivedThisCycle(): bool
    {
        if (! $this->last_received_date || ! $this->next_date || $this->frequency === 'once') {
            return false;
        }

        $next = Carbon::parse($this->next_date);

        return $this->recursMonthlyOrLonger()
            ? $next->gt(Carbon::today()->endOfMonth())
            : $next->gt(Carbon::today());
    }

    /**
     * Whether the receipt can be recorded now: the expected date has passed
     * or is at most EARLY_WINDOW_DAYS away. This is what stops a second tap
     * from depositing the same salary twice and skipping a month.
     */
    public function canReceiveNow(): bool
    {
        if (! $this->is_active || $this->frequency === 'once' || ! $this->next_date) {
            return false;
        }

        return $this->daysUntilNext() <= self::EARLY_WINDOW_DAYS;
    }

    /** Days the expected money is overdue, or 0. */
    public function daysLate(): int
    {
        $days = $this->daysUntilNext();

        return $days !== null && $days < 0 && ! $this->isReceivedThisCycle() ? -$days : 0;
    }

    /**
     * How the last receipt compared with its expected date, in days:
     * positive = late, negative = early, 0 = on time. Null when unknown.
     */
    public function lastReceiptDelay(): ?int
    {
        if (! $this->last_received_date || ! $this->last_expected_date) {
            return null;
        }

        return (int) $this->last_expected_date->copy()->startOfDay()
            ->diffInDays($this->last_received_date->copy()->startOfDay(), false);
    }

    /**
     * Whether the receipt for the month containing $at has been recorded —
     * judged by the date it was *expected*, so a salary for the 1st that
     * landed on the 29th counts for the month it belongs to.
     */
    public function receivedForMonth(?Carbon $at = null): bool
    {
        $at = $at ?? Carbon::now();
        $date = $this->last_expected_date ?? $this->last_received_date;

        return $date !== null && $date->isSameMonth($at);
    }
}
