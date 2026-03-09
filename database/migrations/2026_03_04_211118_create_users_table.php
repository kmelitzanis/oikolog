<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('avatar_url')->nullable();
            $table->string('currency_code', 3)->default('EUR');
            $table->string('timezone')->default('Europe/Athens');
            $table->boolean('notifications_enabled')->default(true);
            $table->string('family_id', 26)->nullable();
            $table->string('family_role', 20)->nullable();
            $table->rememberToken();
            $table->timestamps();

            // The foreign key to `families` is created in a dedicated migration
            // that runs after the families table is created to avoid FK ordering issues.
            // $table->foreign('family_id')->references('id')->on('families')->nullOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Sessions are created in their own migration (2026_03_08_225233_create_sessions_table.php)
        // so we remove the duplicate sessions creation here to avoid conflicts.
        // Schema::create('sessions', function (Blueprint $table) {
        //     $table->string('id')->primary();
        //     $table->string('user_id', 26)->nullable()->index();
        //     $table->string('ip_address', 45)->nullable();
        //     $table->text('user_agent')->nullable();
        //     $table->longText('payload');
        //     $table->integer('last_activity')->index();
        // });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
