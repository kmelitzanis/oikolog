<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->text('description')->nullable();

            $table->string('category_id', 26);
            $table->string('created_by', 26);
            $table->string('assigned_to', 26)->nullable();
            $table->string('family_id', 26)->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 3)->default('EUR');

            // once|daily|weekly|biweekly|monthly|quarterly|yearly
            $table->string('frequency', 20)->default('monthly');
            $table->unsignedTinyInteger('frequency_interval')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_due_date');
            $table->date('last_paid_date')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_shared')->default(false);
            $table->boolean('notify_enabled')->default(true);
            $table->unsignedTinyInteger('notify_days_before')->default(3);

            $table->string('url')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();

            $table->index(['created_by', 'is_active']);
            $table->index(['family_id', 'is_shared']);
            $table->index('next_due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
