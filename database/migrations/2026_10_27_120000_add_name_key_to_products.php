<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products were matched by name with LOWER(name) = ?. MySQL's collation made
 * that case- and accent-insensitive, but SQLite lowercases ASCII only, so
 * "Φακές" never matched "φακές" there and every Greek item got a duplicate
 * product. The comparison now runs on a key computed in PHP, the same on
 * every database, and indexed.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_key')->nullable()->index();
        });

        DB::table('products')->orderBy('id')->select(['id', 'name'])->each(function ($row) {
            DB::table('products')->where('id', $row->id)->update(['name_key' => Product::nameKey((string) $row->name)]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name_key']);
            $table->dropColumn('name_key');
        });
    }
};
