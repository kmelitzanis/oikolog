<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fuel log. A fill-up stays a `fuel` expense, so it keeps counting in the
 * vehicle's totals and cost per km, and gains what working out consumption
 * needs: whether the tank was filled to the top, which is what lets the
 * distance between two full fills be divided by the fuel that went in. The
 * fuel type and the station are for the person reading the log.
 *
 * Existing fuel rows are taken as full fills, which is how people mostly
 * record them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->boolean('is_full_tank')->default(true);
            $table->string('fuel_type', 20)->nullable();
            $table->string('station', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->dropColumn(['is_full_tank', 'fuel_type', 'station']);
        });
    }
};
