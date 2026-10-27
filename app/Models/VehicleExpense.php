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

    public const CATEGORIES = ['fuel', 'service', 'insurance', 'tax', 'other'];

    public const FUEL_TYPES = ['petrol', 'diesel', 'lpg', 'cng'];

    protected $fillable = [
        'vehicle_id', 'category', 'amount', 'spent_at', 'odometer_km',
        'litres', 'note', 'created_by', 'is_full_tank', 'fuel_type', 'station',
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
