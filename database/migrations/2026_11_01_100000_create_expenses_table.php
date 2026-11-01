<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everyday purchases: the supermarket, clothes, a gift. Money that leaves
 * without a bill behind it, which used to end up as anonymous withdrawals in
 * an account's ledger. A purchase paid from an account writes its own
 * withdrawal there, linked back by `expense_id` the way a bill payment's is.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 160);
            $table->string('category', 30)->default('other');
            $table->decimal('amount', 10, 2);
            $table->string('currency_code', 3);
            $table->dateTime('spent_at');
            $table->foreignUlid('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->boolean('is_shared')->default(false);
            $table->foreignUlid('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['created_by', 'spent_at']);
            $table->index(['family_id', 'is_shared']);
        });

        // Plain and indexed rather than a foreign key: SQLite cannot add one
        // to an existing table. Expense::booted() removes the row with its
        // purchase.
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->string('expense_id', 26)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropIndex(['expense_id']);
            $table->dropColumn('expense_id');
        });

        Schema::dropIfExists('expenses');
    }
};
