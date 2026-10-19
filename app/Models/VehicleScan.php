<?php

namespace App\Models;

use App\Support\Obd\DtcDictionary;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One OBD-II diagnostic snapshot: trouble codes plus live readings. */
class VehicleScan extends Model
{
    use HasUlids;

    public const SOURCES = ['bluetooth', 'manual'];
    public const STATUSES = ['stored', 'pending', 'permanent'];

    /**
     * Readings the scanner collects, with the unit shown next to them.
     * Anything else sent by a client is dropped on the way in.
     */
    public const READINGS = [
        'battery_v'      => 'V',
        'module_v'       => 'V',
        'coolant_c'      => '°C',
        'intake_c'       => '°C',
        'rpm'            => 'rpm',
        'speed_kmh'      => 'km/h',
        'load_pct'       => '%',
        'fuel_level_pct' => '%',
        'stft_pct'       => '%',
        'ltft_pct'       => '%',
        'km_since_clear' => 'km',
        'km_with_mil'    => 'km',
    ];

    protected $fillable = [
        'vehicle_id', 'scanned_at', 'source', 'odometer_km', 'vin', 'mil_on',
        'codes', 'readings', 'adapter', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'mil_on'     => 'boolean',
            'codes'      => 'array',
            'readings'   => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** The codes with their descriptions, most serious first. */
    public function describedCodes(): array
    {
        $order = ['permanent' => 0, 'stored' => 1, 'pending' => 2];

        return collect($this->codes ?? [])
            ->map(fn($c) => $c + DtcDictionary::describe($c['code']))
            ->sortBy(fn($c) => $order[$c['status']] ?? 9)
            ->values()
            ->all();
    }

    public function hasCodes(): bool
    {
        return ! empty($this->codes);
    }
}
