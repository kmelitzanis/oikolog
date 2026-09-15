<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Push payloads were being encrypted with `aesgcm`, an abandoned draft of the
 * encryption spec. Every current browser implements RFC 8291's `aes128gcm`
 * instead, and Safari implements nothing else — so a Safari subscription was
 * stored, accepted, and then sent notifications it could never decrypt.
 *
 * Existing rows are rewritten rather than left to expire: they were written
 * from a wrong default, not from anything the browser actually asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->string('content_encoding', 20)->default('aes128gcm')->change();
        });

        DB::table('push_subscriptions')
            ->where('content_encoding', 'aesgcm')
            ->update(['content_encoding' => 'aes128gcm']);
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->string('content_encoding', 20)->default('aesgcm')->change();
        });
    }
};
