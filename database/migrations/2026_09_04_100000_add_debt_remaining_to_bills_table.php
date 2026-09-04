<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // What is still owed in total, across every remaining instalment —
            // a loan's outstanding principal, a card's balance. Optional: null
            // means the bill just recurs forever, which is most of them.
            //
            // Not to be confused with `remaining_balance`, which is what is left
            // on the *current* cycle after a partial payment. This one spans
            // cycles and is what drives the bill to its end.
            $table->decimal('debt_remaining', 12, 2)->nullable()->after('remaining_balance');

            // The figure it started from, so the bill can show how far along it
            // is. Set once, never decremented.
            $table->decimal('debt_initial', 12, 2)->nullable()->after('debt_remaining');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['debt_remaining', 'debt_initial']);
        });
    }
};
