<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The account the pay modal opens on. A bill's own
            // `default_account_id` still wins when it has one — this is the
            // fallback for everything else, which used to be "whichever account
            // happened to sort first".
            $table->foreignUlid('default_account_id')->nullable()->after('currency_code')
                ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_account_id']);
            $table->dropColumn('default_account_id');
        });
    }
};
