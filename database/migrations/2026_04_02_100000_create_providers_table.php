<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('category_id', 26);
            $table->string('website')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->index('category_id');
        });

        if (!Schema::hasColumn('bills', 'provider_id')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->string('provider_id', 26)->nullable()->after('category_id');
                $table->foreign('provider_id')->references('id')->on('providers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            if (Schema::hasColumn('bills', 'provider_id')) {
                $table->dropForeign(['provider_id']);
                $table->dropColumn('provider_id');
            }
        });
        Schema::dropIfExists('providers');
    }
};

