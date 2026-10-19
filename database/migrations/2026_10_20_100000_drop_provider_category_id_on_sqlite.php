<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finish moving providers to the category_provider pivot on SQLite.
 *
 * The pivot migration dropped providers.category_id on MySQL/PostgreSQL but
 * skipped SQLite, leaving a required column that nothing fills in any more —
 * so creating a provider (e.g. in DatabaseSeeder) failed on a local SQLite
 * install. Laravel 12 rebuilds the SQLite table for these changes, so the
 * column can now be dropped there too. Other drivers already lost the column
 * and are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite' || !Schema::hasColumn('providers', 'category_id')) {
            return;
        }

        // Rows from before the pivot existed were copied over by the pivot
        // migration; copy again in case any were added since.
        DB::table('providers')
            ->whereNotNull('category_id')
            ->get(['id', 'category_id'])
            ->each(fn ($row) => DB::table('category_provider')->insertOrIgnore([
                'category_id' => $row->category_id,
                'provider_id' => $row->id,
            ]));

        Schema::table('providers', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });
    }

    public function down(): void
    {
        // Nothing to restore: the pivot migration's down() re-adds the column.
    }
};
