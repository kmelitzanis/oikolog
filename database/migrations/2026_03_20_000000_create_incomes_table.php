<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('source', 80)->nullable();         // e.g. Salary, Freelance, Rental
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 3)->default('EUR');
            $table->string('frequency', 20)->default('monthly'); // once,weekly,biweekly,monthly,quarterly,yearly
            $table->unsignedTinyInteger('frequency_interval')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_date')->nullable();
            $table->date('last_received_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_shared')->default(false);
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index(['family_id', 'is_active']);
            $table->index('next_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};

