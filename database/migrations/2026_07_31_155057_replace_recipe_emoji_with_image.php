<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recipes are identified by a photo now, not a picked emoji.
 *
 * `image_path` holds a path on the `public` disk rather than a URL, so the app can
 * change disk or APP_URL without rewriting rows. `source_url` records where an
 * imported recipe came from — it is both an attribution link and the thing that
 * tells the UI a recipe was imported rather than typed by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            if (! Schema::hasColumn('recipes', 'image_path')) {
                $table->string('image_path')->nullable()->after('description');
            }
            if (! Schema::hasColumn('recipes', 'source_url')) {
                $table->string('source_url', 2048)->nullable()->after('image_path');
            }
        });

        Schema::table('recipes', function (Blueprint $table) {
            if (Schema::hasColumn('recipes', 'emoji')) {
                $table->dropColumn('emoji');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            if (! Schema::hasColumn('recipes', 'emoji')) {
                $table->string('emoji', 16)->nullable();
            }
            foreach (['image_path', 'source_url'] as $column) {
                if (Schema::hasColumn('recipes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
