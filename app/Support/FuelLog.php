<?php

namespace App\Support;

use App\Models\VehicleExpense;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Consumption and cost from a vehicle's fill-ups.
 *
 * Consumption uses the full-to-full method, the one fuel apps use because it
 * is the only one that does not need to know what was in the tank: between
 * two fill-ups that both brimmed the tank, the fuel burned is exactly what
 * went in at the second one plus any partial top-ups in between, and the
 * distance is the odometer difference. A fill-up without an odometer reading
 * or litres breaks the chain, since the next stretch can no longer be
 * measured; it still counts as money spent.
 */
class FuelLog
{
    /** @var Collection<int, VehicleExpense> fuel rows, oldest first */
    private Collection $fills;

    /** @var array<string, float> L/100km per fill-up that closes a measured stretch, by expense id */
    private array $perFill = [];

    private float $measuredKm = 0;
    private float $measuredLitres = 0;
    private float $measuredCost = 0;

    /** @param  iterable<VehicleExpense>  $expenses  any of the vehicle's expenses; non-fuel ones are ignored */
    public function __construct(iterable $expenses)
    {
        $this->fills = collect($expenses)
            ->where('category', 'fuel')
            ->sortBy([
                fn ($a, $b) => $a->spent_at <=> $b->spent_at,
                fn ($a, $b) => ($a->odometer_km ?? 0) <=> ($b->odometer_km ?? 0),
            ])
            ->values();

        $this->measure();
    }

    private function measure(): void
    {
        $lastFull = null;      // odometer of the last full fill the chain runs from
        $litres = 0.0;         // fuel added since it
        $cost = 0.0;

        foreach ($this->fills as $fill) {
            $hasReading = $fill->odometer_km !== null && (float) $fill->litres > 0;

            if (! $hasReading) {
                $lastFull = null;
                $litres = 0.0;
                $cost = 0.0;

                continue;
            }

            $litres += (float) $fill->litres;
            $cost += (float) $fill->amount;

            if (! $fill->is_full_tank) {
                continue;
            }

            $km = $lastFull === null ? 0 : (int) $fill->odometer_km - $lastFull;

            if ($km > 0) {
                $this->perFill[$fill->getKey()] = round($litres / $km * 100, 1);
                $this->measuredKm += $km;
                $this->measuredLitres += $litres;
                $this->measuredCost += $cost;
            }

            $lastFull = (int) $fill->odometer_km;
            $litres = 0.0;
            $cost = 0.0;
        }
    }

    public function hasFills(): bool
    {
        return $this->fills->isNotEmpty();
    }

    /** L/100km for the stretch this fill-up closed, when it closed a measured one. */
    public function consumptionFor(VehicleExpense $fill): ?float
    {
        return $this->perFill[$fill->getKey()] ?? null;
    }

    /** Average L/100km over every measured stretch, weighted by distance. */
    public function averageConsumption(): ?float
    {
        return $this->measuredKm > 0 ? round($this->measuredLitres / $this->measuredKm * 100, 1) : null;
    }

    /** What fuel costs per kilometre, over the same measured stretches. */
    public function costPerKm(): ?float
    {
        return $this->measuredKm > 0 ? round($this->measuredCost / $this->measuredKm, 3) : null;
    }

    /** Average price paid per litre, over fill-ups that record litres. */
    public function averagePricePerLitre(): ?float
    {
        $withLitres = $this->fills->filter(fn ($fill) => (float) $fill->litres > 0);
        $litres = $withLitres->sum(fn ($fill) => (float) $fill->litres);

        return $litres > 0 ? round($withLitres->sum(fn ($fill) => (float) $fill->amount) / $litres, 3) : null;
    }

    /** Fuel spend and litres since a date. */
    public function since(Carbon $date): array
    {
        $fills = $this->fills->filter(fn ($fill) => $fill->spent_at && $fill->spent_at->gte($date));

        return [
            'amount' => round($fills->sum(fn ($fill) => (float) $fill->amount), 2),
            'litres' => round($fills->sum(fn ($fill) => (float) $fill->litres), 2),
            'count'  => $fills->count(),
        ];
    }

    /** @return Collection<int, VehicleExpense> newest first */
    public function latest(int $count): Collection
    {
        return $this->fills->reverse()->take($count)->values();
    }
}
