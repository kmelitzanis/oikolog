<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 60);
            $table->string('icon', 60)->default('receipt');
            $table->string('color_hex', 9)->default('#6366F1');
            $table->string('family_id', 26)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            $table->index(['family_id', 'is_system']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
