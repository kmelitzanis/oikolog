<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One workshop visit, with the receipt's lines kept as written. */
class VehicleService extends Model
{
    use HasUlids;

    protected $fillable = [
        'vehicle_id', 'performed_at', 'odometer_km', 'shop', 'total', 'lines', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
            'total'        => 'decimal:2',
            'lines'        => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The receipt's own total, falling back to the sum of its lines.
     *
     * Workshops round, discount, and add VAT lines, so a stored total that
     * disagrees with the lines is the truth — the lines are a breakdown, not
     * the source.
     */
    public function effectiveTotal(): float
    {
        if ((float) $this->total > 0) {
            return (float) $this->total;
        }

        return collect($this->lines ?? [])->sum(fn($l) => (float) ($l['cost'] ?? 0));
    }
}
