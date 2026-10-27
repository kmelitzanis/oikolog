<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The meal planner is gone. Recipes stay; the week grid that pointed at them
 * does not, so its table goes with it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('meal_plans');
    }

    /** The table as it last stood, empty: the plans themselves are not recoverable. */
    public function down(): void
    {
        if (Schema::hasTable('meal_plans')) {
            return;
        }

        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 26)->index();
            $table->date('date')->index();
            $table->string('meal_type', 20);
            $table->unsignedBigInteger('recipe_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('servings')->default(2);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->foreign('recipe_id')->references('id')->on('recipes')->nullOnDelete();
            $table->index(['user_id', 'date']);
        });
    }
};
