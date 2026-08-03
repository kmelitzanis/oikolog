<?php

use App\Support\Units;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recipes are written in parts — "Για τη βάση", "Για τη σαντιγί", "Για τη σάλτσα" —
 * and both the ingredients and the method are grouped under those headings.
 *
 * `section` is a plain nullable label rather than a separate table: a heading has
 * no attributes of its own, it only groups, and a NULL means "ungrouped", which is
 * exactly what every existing row already is. `sort_order` is added alongside so
 * grouping cannot silently reorder a method — with ingredients and steps returned
 * grouped, insertion order alone is no longer enough to keep steps in sequence.
 *
 * Also canonicalises the free-text units already in the database (see Units): they
 * were stored as whatever a recipe site happened to write, so "γρ.", "gr" and "g"
 * were three different units and none of them could be translated.
 */
return new class extends Migration
{
    /** Tables holding a free-text `unit` that should now be canonical. */
    private const UNIT_TABLES = ['recipe_ingredients', 'shopping_list_items', 'products'];

    public function up(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            if (! Schema::hasColumn('recipe_ingredients', 'section')) {
                $table->string('section', 120)->nullable()->after('recipe_id');
            }
            if (! Schema::hasColumn('recipe_ingredients', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('section');
            }
        });

        // Instructions move from a flat text blob to ordered, optionally-titled
        // steps. The old `instructions` column stays for now so nothing is lost
        // if this needs reverting; the model reads the table when it has rows.
        if (! Schema::hasTable('recipe_steps')) {
            Schema::create('recipe_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
                $table->string('section', 120)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->text('text');
                $table->timestamps();

                $table->index(['recipe_id', 'sort_order']);
            });
        }

        $this->backfillSteps();
        $this->canonicaliseUnits();
    }

    /** Existing single-blob instructions become one ungrouped run of steps. */
    private function backfillSteps(): void
    {
        if (! Schema::hasColumn('recipes', 'instructions')) {
            return;
        }

        DB::table('recipes')
            ->whereNotNull('instructions')
            ->where('instructions', '!=', '')
            ->orderBy('id')
            ->chunk(100, function ($recipes) {
                foreach ($recipes as $recipe) {
                    // Don't double-import if this migration is re-run.
                    if (DB::table('recipe_steps')->where('recipe_id', $recipe->id)->exists()) {
                        continue;
                    }

                    $lines = preg_split('/\r\n|\r|\n/', (string) $recipe->instructions) ?: [];
                    $order = 0;
                    $rows = [];

                    foreach ($lines as $line) {
                        $text = trim($line);
                        if ($text === '') {
                            continue;
                        }

                        $rows[] = [
                            'recipe_id'  => $recipe->id,
                            'section'    => null,
                            'sort_order' => $order++,
                            'text'       => $text,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if ($rows) {
                        DB::table('recipe_steps')->insert($rows);
                    }
                }
            });
    }

    /** Fold the free-text units already stored into the canonical vocabulary. */
    private function canonicaliseUnits(): void
    {
        foreach (self::UNIT_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'unit')) {
                continue;
            }

            $distinct = DB::table($table)->distinct()->pluck('unit')->filter()->all();

            foreach ($distinct as $raw) {
                $canonical = Units::canonical($raw);

                // Leave anything unrecognised alone — better a stray label than
                // silently relabelling something as "pieces".
                if ($canonical === null || $canonical === $raw) {
                    continue;
                }

                DB::table($table)->where('unit', $raw)->update(['unit' => $canonical]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_steps');

        Schema::table('recipe_ingredients', function (Blueprint $table) {
            foreach (['section', 'sort_order'] as $column) {
                if (Schema::hasColumn('recipe_ingredients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
