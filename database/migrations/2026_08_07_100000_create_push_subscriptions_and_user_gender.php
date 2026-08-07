<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per browser/device that has granted notification permission.
        // The endpoint is the push service's opaque URL and is the natural key:
        // re-subscribing the same browser must update, not duplicate.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            // MySQL can't index an unbounded TEXT column, and endpoints run long.
            $table->string('endpoint_hash', 64)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding', 20)->default('aesgcm');
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            // Greek needs the speaker's gender to build "Ο/Η <name> πλήρωσε …".
            // Nullable: unset means fall back to the neutral "Ο/Η" form.
            $table->string('gender', 10)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
