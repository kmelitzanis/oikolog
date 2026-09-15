<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a vehicle will need doing, due on a date, a mileage, or both.
 */
class VehicleReminder extends Model
{
    use HasUlids;

    protected $fillable = [
        'vehicle_id', 'label', 'due_date', 'due_km',
        'interval_months', 'interval_km', 'note', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date'     => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('completed_at');
    }

    /** Days until the date falls due; null when this one is mileage-only. */
    public function daysUntilDue(): ?int
    {
        return $this->due_date ? Carbon::today()->diffInDays($this->due_date, false) : null;
    }

    /** Kilometres left on the odometer; null when this one is date-only. */
    public function kmRemaining(): ?int
    {
        return $this->due_km === null
            ? null
            : $this->due_km - (int) ($this->vehicle?->odometer_km ?? 0);
    }

    /**
     * How close this is, as the app's own status vocabulary.
     *
     * A reminder with both a date and a mileage is due on whichever arrives
     * first, so the worse of the two readings wins — an MOT still a year away
     * on a car that is 200 km from its service is not an "ok" reminder.
     */
    public function tone(): string
    {
        $days = $this->daysUntilDue();
        $km   = $this->kmRemaining();

        $byDate = match (true) {
            $days === null => null,
            $days < 0      => 'overdue',
            $days <= 30    => 'warn',
            default        => 'ok',
        };

        $byKm = match (true) {
            $km === null => null,
            $km < 0      => 'overdue',
            $km <= 1000  => 'warn',
            default      => 'ok',
        };

        $rank = ['overdue' => 0, 'warn' => 1, 'ok' => 2];
        $candidates = array_filter([$byDate, $byKm]);

        if (! $candidates) {
            return 'ok';
        }

        usort($candidates, fn($a, $b) => $rank[$a] <=> $rank[$b]);

        return $candidates[0];
    }
}
