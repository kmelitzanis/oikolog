<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Money now lives in accounts the user defines (a salary account, a savings
 * account, cash — whatever they keep money in). Every movement is a ledger
 * row, so a balance is never a field that can drift out of sync with history:
 * it is the opening balance plus the signed sum of its transactions.
 *
 * Income receipts deposit, bill payments withdraw, and transfers do both as a
 * pair sharing a `transfer_group`. The old payment → income link is replaced by
 * payment → account.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->text('description')->nullable();
            // Free-form: the user names their own accounts, the app ships none.
            $table->string('icon', 40)->default('account_balance');
            $table->string('color_hex', 7)->default('#10b981');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->string('currency_code', 3)->default('EUR');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_shared')->default(false);
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index(['family_id', 'is_active']);
        });

        Schema::create('account_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            // deposit | withdrawal | transfer_in | transfer_out | adjustment
            $table->string('type', 20);
            // Always positive; `direction` carries the sign so a row is readable
            // on its own and sums stay obvious.
            $table->decimal('amount', 14, 2);
            $table->smallInteger('direction'); // 1 in, -1 out
            $table->string('currency_code', 3)->default('EUR');
            $table->timestamp('occurred_at');
            $table->string('description', 160)->nullable();
            // Provenance: at most one of these is set.
            $table->foreignUlid('income_id')->nullable()->constrained('incomes')->nullOnDelete();
            $table->string('payment_id', 26)->nullable();
            $table->ulid('transfer_group')->nullable();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->index(['account_id', 'occurred_at']);
            $table->index('transfer_group');
        });

        // Where an income source lands when it is received.
        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignUlid('account_id')->nullable()->after('currency_code')
                ->constrained('accounts')->nullOnDelete();
        });

        // Which account a bill payment came out of.
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUlid('account_id')->nullable()->after('paid_by')
                ->constrained('accounts')->nullOnDelete();
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->foreignUlid('default_account_id')->nullable()->after('provider_id')
                ->constrained('accounts')->nullOnDelete();
        });

        // The payment → income link is superseded by payment → account. These
        // columns were added by several migrations over time and not all of
        // them carry a foreign key, so drop whatever constraint is actually
        // there before dropping the column.
        $this->dropColumnWithForeignKey('payments', 'income_id');
        $this->dropColumnWithForeignKey('bills', 'default_income_id');
    }

    private function dropColumnWithForeignKey(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        // Only MySQL both needs the constraint dropped first and can be asked
        // what it is called; SQLite rebuilds the table on drop and is fine.
        $constraints = DB::connection()->getDriverName() === 'mysql'
            ? DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
                   AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$table, $column],
            )
            : [];

        Schema::table($table, function (Blueprint $blueprint) use ($constraints, $column) {
            if (DB::connection()->getDriverName() === 'mysql') {
                foreach ($constraints as $constraint) {
                    $blueprint->dropForeign($constraint->CONSTRAINT_NAME);
                }
            } else {
                // SQLite rebuilds the table and refuses to leave a dangling
                // foreign key behind, so name the column's own constraint.
                $blueprint->dropForeign([$column]);
            }
            $blueprint->dropColumn($column);
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->foreignUlid('default_income_id')->nullable()->constrained('incomes')->nullOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUlid('income_id')->nullable()->constrained('incomes')->nullOnDelete();
        });
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_account_id');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('accounts');
    }
};
