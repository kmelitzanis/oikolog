<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A car, bike or anything else the household runs.
 *
 * Unlike a bill, a vehicle's costs are not scheduled: they are recorded after
 * the fact, and the useful figures — cost per kilometre, cost per year — are
 * derived from what was actually spent over the distance actually covered.
 * Nothing here is stored twice; every total on the page is summed from rows.
 */
class Vehicle extends Model
{
    use HasUlids;

    public const TYPES = ['car', 'motorcycle', 'other'];

    protected $fillable = [
        'name', 'type', 'brand', 'model', 'plate', 'year', 'variant',
        'odometer_km', 'odometer_read_at', 'purchase_date', 'purchase_km',
        'insurer', 'insurance_due', 'photo_path', 'notes',
        'is_active', 'is_shared', 'created_by', 'family_id',
    ];

    protected function casts(): array
    {
        return [
            'odometer_read_at' => 'date',
            'purchase_date'    => 'date',
            'insurance_due'    => 'date',
            'is_active'        => 'boolean',
            'is_shared'        => 'boolean',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(VehicleReminder::class)->orderBy('due_date');
    }

    public function services(): HasMany
    {
        return $this->hasMany(VehicleService::class)->orderByDesc('performed_at');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(VehicleExpense::class)->orderByDesc('spent_at');
    }

    /** Same sharing rule as bills and accounts: mine, or my family's shared ones. */
    public function scopeForUser($query, $user)
    {
        if (! $user) {
            return $query->whereRaw('1=0');
        }

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

    // ── Presentation ─────────────────────────────────────────────────────

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /** Material icon for the vehicle type; an unknown type still gets one. */
    public function icon(): string
    {
        return match ($this->type) {
            'motorcycle' => 'two_wheeler',
            'other'      => 'commute',
            default      => 'directions_car',
        };
    }

    /** "Toyota Yaris" from the parts, falling back to the name the user gave. */
    public function fullModel(): string
    {
        $parts = array_filter([$this->brand, $this->model]);

        return $parts ? implode(' ', $parts) : $this->name;
    }

    // ── Derived figures ──────────────────────────────────────────────────

    /**
     * Distance covered since the vehicle was bought.
     *
     * Falls back to the whole odometer when no purchase reading was recorded,
     * which is right for a car bought new and merely optimistic for one bought
     * used — better than refusing to show a cost per km at all.
     */
    public function kmCovered(): int
    {
        return max(0, (int) $this->odometer_km - (int) ($this->purchase_km ?? 0));
    }

    /** Years of ownership, floored at a month so a new car doesn't divide by zero. */
    public function yearsOwned(): float
    {
        $from = $this->purchase_date ?? $this->created_at;

        if (! $from) {
            return 1.0;
        }

        return max(1 / 12, Carbon::parse($from)->floatDiffInYears(Carbon::today()));
    }

    /** Average kilometres a year — the figure the card quotes under the odometer. */
    public function kmPerYear(): int
    {
        return (int) round($this->kmCovered() / $this->yearsOwned());
    }

    /**
     * Everything spent on this vehicle: loose expenses plus workshop receipts.
     *
     * Services are counted from their own table rather than expected to be
     * duplicated as expense rows, so recording a service never has to be done
     * twice to keep the totals honest.
     */
    public function totalSpent(?Carbon $since = null): float
    {
        $expenses = $this->expenses()
            ->when($since, fn($q) => $q->where('spent_at', '>=', $since))
            ->sum('amount');

        $services = $this->services()
            ->when($since, fn($q) => $q->where('performed_at', '>=', $since))
            ->get()
            ->sum(fn(VehicleService $s) => $s->effectiveTotal());

        return round((float) $expenses + (float) $services, 2);
    }

    /** Spend over the last twelve months — what "per year" means on the card. */
    public function yearCost(): float
    {
        return $this->totalSpent(Carbon::today()->subYear());
    }

    public function monthCost(): float
    {
        return round($this->yearCost() / 12, 2);
    }

    /**
     * Cost per kilometre over the whole life of the vehicle.
     *
     * Null rather than zero when nothing has been driven yet: "0,00 €/km" reads
     * as a measured result, and this one has not been measured.
     */
    public function costPerKm(): ?float
    {
        $km = $this->kmCovered();

        return $km > 0 ? round($this->totalSpent() / $km, 3) : null;
    }

    /** Spend split into the buckets the summary card lists, biggest first. */
    public function expenseBreakdown(?Carbon $since = null): array
    {
        $rows = $this->expenses()
            ->when($since, fn($q) => $q->where('spent_at', '>=', $since))
            ->get()
            ->groupBy('category')
            ->map(fn($group) => [
                'amount' => round((float) $group->sum('amount'), 2),
                'count'  => $group->count(),
            ])
            ->all();

        $services = $this->services()
            ->when($since, fn($q) => $q->where('performed_at', '>=', $since))
            ->get();

        if ($services->isNotEmpty()) {
            $rows['service'] = [
                'amount' => round(
                    ($rows['service']['amount'] ?? 0)
                        + $services->sum(fn(VehicleService $s) => $s->effectiveTotal()),
                    2
                ),
                'count' => ($rows['service']['count'] ?? 0) + $services->count(),
            ];
        }

        uasort($rows, fn($a, $b) => $b['amount'] <=> $a['amount']);

        return $rows;
    }

    // ── Attention ────────────────────────────────────────────────────────

    /** The open reminder that needs doing first, or null when all is clear. */
    public function nextReminder(): ?VehicleReminder
    {
        $open = $this->reminders()->open()->get()->each->setRelation('vehicle', $this);

        if ($open->isEmpty()) {
            return null;
        }

        $rank = ['overdue' => 0, 'warn' => 1, 'ok' => 2];

        return $open->sortBy([
            fn($a, $b) => $rank[$a->tone()] <=> $rank[$b->tone()],
            fn($a, $b) => ($a->due_date?->timestamp ?? PHP_INT_MAX) <=> ($b->due_date?->timestamp ?? PHP_INT_MAX),
        ])->first();
    }

    /** Whether anything on this vehicle is overdue or nearly so. */
    public function needsAttention(): bool
    {
        return in_array($this->nextReminder()?->tone(), ['overdue', 'warn'], true);
    }
}
