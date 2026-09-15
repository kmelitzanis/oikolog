<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money spent on a vehicle, in the four buckets the summary reports on. */
class VehicleExpense extends Model
{
    use HasUlids;

    public const CATEGORIES = ['fuel', 'service', 'insurance', 'tax', 'other'];

    protected $fillable = [
        'vehicle_id', 'category', 'amount', 'spent_at', 'odometer_km',
        'litres', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'spent_at' => 'date',
            'amount'   => 'decimal:2',
            'litres'   => 'decimal:2',
        ];
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
