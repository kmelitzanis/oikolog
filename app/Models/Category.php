<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'icon', 'color_hex', 'family_id', 'is_system', 'vehicle_expense'];

    /**
     * The category that stands for a kind of vehicle cost (insurance,
     * road_tax, kteo, tolls), so a recurring one recorded on a car becomes a
     * bill there and a bill there shows on the car.
     */
    public static function forVehicleExpense(string $key): ?self
    {
        return static::where('vehicle_expense', $key)->first();
    }

    public function isForVehicles(): bool
    {
        return $this->vehicle_expense !== null;
    }

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class);
    }
}
