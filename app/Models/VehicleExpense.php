<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money spent on a vehicle, in the buckets the summary reports on. A `fuel`
 * row is also a fill-up in the fuel log; see App\Support\FuelLog.
 */
class VehicleExpense extends Model
{
    use HasUlids;

    public const CATEGORIES = ['fuel', 'service', 'insurance', 'road_tax', 'kteo', 'tolls', 'other'];

    /** Costs that come round on a schedule, and so can be kept as a bill. */
    public const RECURRING = ['insurance', 'road_tax', 'kteo'];

    /** Costs paid to someone worth naming: an insurer, the tax office, a motorway. */
    public const WITH_PROVIDER = ['insurance', 'road_tax', 'kteo', 'tolls'];

    /** How often a recurring vehicle cost can come round. */
    public const FREQUENCIES = ['monthly', 'quarterly', 'semiannual', 'yearly'];

    public const ICONS = [
        'fuel' => 'local_gas_station', 'service' => 'build', 'insurance' => 'shield', 'road_tax' => 'receipt_long',
        'kteo' => 'fact_check', 'tolls' => 'toll', 'other' => 'more_horiz',
    ];

    public const FUEL_TYPES = ['petrol', 'diesel', 'lpg', 'cng'];

    protected $fillable = [
        'vehicle_id', 'category', 'amount', 'spent_at', 'odometer_km',
        'litres', 'note', 'created_by', 'is_full_tank', 'fuel_type', 'station', 'provider_id',
    ];

    protected function casts(): array
    {
        return [
            'spent_at' => 'date',
            'amount'   => 'decimal:2',
            'litres'   => 'decimal:2',
            'is_full_tank' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function isFuel(): bool
    {
        return $this->category === 'fuel';
    }

    /** What a litre cost at this fill-up, when the litres were recorded. */
    public function pricePerLitre(): ?float
    {
        return (float) $this->litres > 0 ? round((float) $this->amount / (float) $this->litres, 3) : null;
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function scopeSince($query, $date)
    {
        return $query->where('spent_at', '>=', $date);
    }
}
