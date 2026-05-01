<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('shopping_list_id');
            $table->string('name', 255);
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit', 50)->default('piece');
            $table->string('barcode', 50)->nullable();
            $table->json('nutrition')->nullable();
            $table->boolean('checked')->default(false);
            $table->timestamps();
            $table->foreign('shopping_list_id')->references('id')->on('shopping_lists')->cascadeOnDelete();
            $table->index('shopping_list_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
    }
};
