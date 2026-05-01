<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── 1. Create the pivot table ─────────────────────────────────────────
        if (!Schema::hasTable('category_provider')) {
            Schema::create('category_provider', function (Blueprint $table) {
                $table->string('category_id', 26);
                $table->string('provider_id', 26);
                $table->primary(['category_id', 'provider_id']);
                $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
                $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            });
        }

        // ── 2. Copy existing single-category data into the pivot ──────────────
        if (Schema::hasColumn('providers', 'category_id')) {
            DB::table('providers')
                ->whereNotNull('category_id')
                ->get(['id', 'category_id'])
                ->each(function ($row) {
                    DB::table('category_provider')->insertOrIgnore([
                        'category_id' => $row->category_id,
                        'provider_id' => $row->id,
                    ]);
                });

        // ── 3. Drop category_id from providers ────────────────────────────
            // Drop all foreign keys on that column first (name may vary by driver).
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('providers', function (Blueprint $table) {
                    // SQLite doesn't support dropping foreign keys by name,
                    // so we only do this for MySQL and PostgreSQL
                    if (DB::getDriverName() === 'mysql') {
                        $fks = collect(
                            DB::select("SELECT CONSTRAINT_NAME
                                        FROM information_schema.KEY_COLUMN_USAGE
                                        WHERE TABLE_SCHEMA = DATABASE()
                                          AND TABLE_NAME = 'providers'
                                          AND COLUMN_NAME = 'category_id'
                                          AND REFERENCED_TABLE_NAME IS NOT NULL")
                        )->pluck('CONSTRAINT_NAME');

                        foreach ($fks as $fk) {
                            $table->dropForeign($fk);
                        }
                    } else {
                        // For PostgreSQL, try the convention name
                        try {
                            $table->dropForeign('providers_category_id_foreign');
                        } catch (\Exception $e) {
                            // Ignore errors
                        }
                    }
                });
            }

            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('providers', function (Blueprint $table) {
                    $table->dropColumn('category_id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore category_id column
        if (!Schema::hasColumn('providers', 'category_id')) {
            Schema::table('providers', function (Blueprint $table) {
                $table->string('category_id', 26)->nullable()->after('name');
                $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
                $table->index('category_id');
            });

            // Restore first category from pivot for each provider
            DB::table('category_provider')->get()->each(function ($row) {
                DB::table('providers')
                    ->where('id', $row->provider_id)
                    ->whereNull('category_id')
                    ->update(['category_id' => $row->category_id]);
            });
        }

        Schema::dropIfExists('category_provider');
    }
};
