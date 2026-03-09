<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasTable('families') && Schema::hasColumn('users', 'family_id')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('family_id')->references('id')->on('families')->nullOnDelete();
                });
            } catch (\Exception $e) {
                // If FK already exists or DB doesn't support, ignore to keep migrations idempotent.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'family_id')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropForeign(['family_id']);
                });
            } catch (\Exception $e) {
                // ignore
            }
        }
    }
};
