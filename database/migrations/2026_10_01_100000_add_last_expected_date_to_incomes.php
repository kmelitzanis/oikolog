<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The date the last receipt was *expected* on — the `next_date` it settled.
 * Kept so the app can say how early or late the money actually came in, and
 * so an early receipt (salary on the 29th for the 1st) counts for the month
 * it belongs to rather than the month it happened to land in.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->date('last_expected_date')->nullable()->after('last_received_date');
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropColumn('last_expected_date');
        });
    }
};
