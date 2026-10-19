<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OBD-II diagnostic scans of a vehicle.
 *
 * A scan is a snapshot: the trouble codes the car reported at that moment and
 * a handful of live readings. Both are JSON because what a car answers varies
 * by make and year — a field missing from one scan is normal, not an error —
 * and the app only ever reads a scan back whole, never queries inside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_scans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->timestamp('scanned_at');
            // 'bluetooth' (read from an adapter in the browser) | 'manual'
            $table->string('source', 20)->default('manual');
            $table->unsignedInteger('odometer_km')->nullable();
            $table->string('vin', 17)->nullable();
            // Check-engine light as reported by mode 01 PID 01; null = unknown.
            $table->boolean('mil_on')->nullable();
            // [{code: "P0420", status: "stored"|"pending"|"permanent"}]
            $table->json('codes')->nullable();
            // {"battery_v": 12.6, "coolant_c": 88, "rpm": 780, ...}
            $table->json('readings')->nullable();
            $table->string('adapter', 120)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_scans');
    }
};
