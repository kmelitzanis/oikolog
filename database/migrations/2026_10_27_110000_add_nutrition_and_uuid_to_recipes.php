<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * What a recipe gives an outside app: nutrition per serving, and an id that is
 * not the auto-increment key. SparkyFitness reads recipes through the
 * Mealie-compatible API and keeps the id it was given; a sequential number
 * there would let anyone holding one photo URL walk through everybody's.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique();
            // Per serving: calories in kcal, sodium in mg, the rest in grams.
            $table->json('nutrition')->nullable();
        });

        DB::table('recipes')->whereNull('uuid')->orderBy('id')->each(function ($row) {
            DB::table('recipes')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'nutrition']);
        });
    }
};
