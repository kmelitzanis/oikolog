<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The default Sanctum migration creates `personal_access_tokens.tokenable_id`
 * as a BIGINT via `$table->morphs('tokenable')`. This app's users use ULID
 * primary keys (see the users table + User's HasUlids trait), so storing a
 * token keyed by a ULID fails with:
 *
 *   SQLSTATE[22007]: Invalid datetime format: 1292 Truncated incorrect
 *   INTEGER value: '01k...'
 *
 * This migration rebuilds the table with `ulidMorphs('tokenable')` so the
 * column is CHAR(26) and matches the user key type. The table holds no
 * meaningful data yet (API token auth has never succeeded), so a drop/recreate
 * is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('personal_access_tokens');

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->ulidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Revert to the framework default (numeric tokenable_id).
        Schema::dropIfExists('personal_access_tokens');

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }
};
