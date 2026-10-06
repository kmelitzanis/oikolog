<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bills carry ULIDs, but the media table was created with an integer
 * `model_id`, so receipts could never be attached to a bill — the upload
 * was silently thrown away. A string column holds either kind of key.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        Schema::table('media', function (Blueprint $table) {
            $table->string('model_id', 36)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        Schema::table('media', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->change();
        });
    }
};
