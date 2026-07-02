<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Base recipe columns ────────────────────────────────────────────
        // Older installs created `recipes` with only id + timestamps (the
        // create/add_fields migrations never added these), so add them if the
        // table is missing them. Guarded individually to stay portable.
        Schema::table('recipes', function (Blueprint $table) {
            if (! Schema::hasColumn('recipes', 'user_id')) {
                $table->char('user_id', 26)->nullable()->index();
            }
            if (! Schema::hasColumn('recipes', 'name')) {
                $table->string('name')->default('');
            }
            if (! Schema::hasColumn('recipes', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'servings')) {
                $table->unsignedSmallInteger('servings')->default(2);
            }
        });

        // ── New recipe columns ─────────────────────────────────────────────
        // No ->after() so ordering never couples to a possibly-missing column.
        Schema::table('recipes', function (Blueprint $table) {
            if (! Schema::hasColumn('recipes', 'prep_minutes')) {
                $table->unsignedSmallInteger('prep_minutes')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'cook_minutes')) {
                $table->unsignedSmallInteger('cook_minutes')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'difficulty')) {
                $table->string('difficulty', 10)->nullable(); // easy | medium | hard
            }
            if (! Schema::hasColumn('recipes', 'instructions')) {
                $table->longText('instructions')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'emoji')) {
                $table->string('emoji', 16)->nullable();
            }
            if (! Schema::hasColumn('recipes', 'is_favorite')) {
                $table->boolean('is_favorite')->default(false);
            }
        });

        // ── Meal plans table ───────────────────────────────────────────────
        if (! Schema::hasTable('meal_plans')) {
            Schema::create('meal_plans', function (Blueprint $table) {
                $table->id();
                $table->char('user_id', 26)->index();
                $table->date('date')->index();
                $table->string('meal_type', 20); // breakfast | lunch | dinner | snack
                $table->unsignedBigInteger('recipe_id')->nullable()->index();
                $table->string('title')->nullable();    // custom (non-recipe) meal
                $table->unsignedSmallInteger('servings')->default(2);
                $table->string('notes', 500)->nullable();
                $table->timestamps();

                $table->foreign('recipe_id')->references('id')->on('recipes')->nullOnDelete();
                $table->index(['user_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plans');

        Schema::table('recipes', function (Blueprint $table) {
            foreach (['prep_minutes', 'cook_minutes', 'difficulty', 'instructions', 'emoji', 'is_favorite'] as $col) {
                if (Schema::hasColumn('recipes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
