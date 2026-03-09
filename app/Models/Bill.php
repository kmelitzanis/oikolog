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
        'name', 'description', 'category_id', 'assigned_to', 'amount', 'currency_code',
        'frequency', 'frequency_interval', 'start_date', 'end_date', 'next_due_date',
        'last_paid_date', 'is_active', 'is_shared', 'notify_enabled', 'notify_days_before',
        'url', 'notes', 'created_by', 'family_id', 'created_at', 'updated_at', 'created_by'
    ];

    protected function casts(): array
    {
        return [
            'amount'             => 'decimal:2',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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

    public function scopeOverdue($query)
    {
        return $query->whereDate('next_due_date', '<', Carbon::today());
    }

    public function scopeDueWithin($query, int $days)
    {
        return $query->whereBetween('next_due_date', [Carbon::today(), Carbon::today()->addDays($days)]);
    }

    // Helpers
    public function monthlyEquivalent(): float
    {
        $amount = (float) $this->amount;
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

    // Helper to get receipt urls (if medialibrary installed)
    public function receiptUrls(): array
    {
        if (method_exists($this, 'getMedia') && $this->hasMedia('receipts')) {
            return $this->getMedia('receipts')->map(fn($m) => $m->getUrl())->toArray();
        }
        return [];
    }
}
