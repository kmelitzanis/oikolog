<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // What this cycle actually costs, once the provider says so.
            //
            // `amount` stays what it was: a fixed price, or an estimate for a
            // bill whose cost varies. This column is the known figure for the
            // period now due — set when the invoice arrives, cleared when the
            // bill is paid off and the schedule rolls to the next period.
            $table->decimal('current_amount', 12, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('current_amount');
        });
    }
};
