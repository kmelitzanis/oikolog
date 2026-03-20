<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'name', 'description', 'source', 'amount', 'currency_code',
        'frequency', 'frequency_interval', 'start_date', 'end_date',
        'next_date', 'last_received_date', 'is_active', 'is_shared',
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

    // ── Scopes ─────────────────────────────────────────────────────────────────
    public function scopeForUser($query, $user)
    {
        if (!$user) return $query->whereRaw('1=0');
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
        $date = Carbon::parse($this->next_date);
        $freq = $this->frequency ?? 'monthly';
        $interval = (int)($this->frequency_interval ?? 1);
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
     * Collect all occurrence dates between $from and $to using recursion.
     *
     * @return Carbon[]
     */
    public function occurrencesBetween(Carbon $from, Carbon $to): array
    {
        if (!$this->start_date) return [];
        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = $this->end_date ? Carbon::parse($this->end_date)->endOfDay() : null;
        if ($end && $end->lt($from)) return [];
        if ($start->gt($to)) return [];
        $current = $start->copy();
        while ($current->lt($from)) {
            $next = $this->advanceDate($current);
            if (!$next || $next->lte($current)) return [];
            $current = $next;
            if ($end && $current->gt($end)) return [];
        }
        $occurrences = [];
        $collect = function (Carbon $dt) use (&$collect, $to, $end, &$occurrences) {
            if ($dt->gt($to)) return;
            if ($end && $dt->gt($end)) return;
            $occurrences[] = $dt->copy();
            $next = $this->advanceDate($dt);
            if (!$next || $next->lte($dt)) return;
            $collect($next);
        };
        $collect($current);
        return $occurrences;
    }

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
}
