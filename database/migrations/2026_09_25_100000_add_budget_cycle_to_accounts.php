<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An account can now be a spending "envelope" instead of a running balance:
 * it gets a fixed amount per cycle (from payday to payday), spending comes out
 * of that, and every cycle starts again from the full amount. Nothing carries
 * over on its own — leftover money only moves when the user transfers it.
 *
 * Existing accounts stay `standard`; the user picks which ones to switch.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('kind', 20)->default('standard')->after('description'); // standard | budget
            $table->decimal('cycle_amount', 14, 2)->nullable()->after('kind');
            $table->unsignedTinyInteger('cycle_day')->nullable()->after('cycle_amount'); // 1–31, payday
            // End of the last cycle whose leftover the user has dealt with
            // (moved or left), so the prompt does not come back for it.
            $table->timestamp('cycle_settled_until')->nullable()->after('cycle_day');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['kind', 'cycle_amount', 'cycle_day', 'cycle_settled_until']);
        });
    }
};
