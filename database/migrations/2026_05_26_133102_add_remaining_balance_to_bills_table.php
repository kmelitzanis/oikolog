<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Tracks outstanding partial balance for the current billing cycle.
            // null = no partial payment made (full amount is owed or fully settled).
            $table->decimal('remaining_balance', 12, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('remaining_balance');
        });
    }
};
