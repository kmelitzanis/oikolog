<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->text('description')->nullable()->after('name');
            $table->string('brand', 100)->nullable()->after('description');
            $table->string('barcode', 50)->nullable()->after('brand');
            $table->string('category', 100)->nullable()->after('barcode');
            $table->string('unit', 50)->default('piece')->after('category');
            $table->decimal('default_quantity', 10, 2)->default(1)->after('unit');
            $table->string('image_path')->nullable()->after('default_quantity');
            $table->string('image_url', 500)->nullable()->after('image_path');
            $table->json('nutrition')->nullable()->after('image_url');
            $table->string('nutri_score', 5)->nullable()->after('nutrition');
            $table->string('eco_score', 5)->nullable()->after('nutri_score');
            // null = global admin product; set = user-created personal product
            $table->string('user_id')->nullable()->after('eco_score');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'name', 'description', 'brand', 'barcode', 'category',
                'unit', 'default_quantity', 'image_path', 'image_url',
                'nutrition', 'nutri_score', 'eco_score', 'user_id',
            ]);
        });
    }
};
