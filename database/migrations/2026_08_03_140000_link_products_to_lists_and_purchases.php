<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products become one shared catalogue that builds itself.
 *
 * A shopping list item points at a product instead of copying its details, so
 * a product has a stable page and edits made there are visible everywhere. The
 * item keeps its own name so free-text entries ("whatever fruit is on offer")
 * still work without inventing a product for them.
 *
 * Checking an item off records a purchase, which is what gives a product its
 * history — and what makes it undeletable by the pruning job.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Everything Open Food Facts gives us that was being thrown away.
            $table->text('ingredients_text')->nullable()->after('description');
            $table->json('allergens')->nullable()->after('ingredients_text');
            // NOVA: how processed the food is (1 unprocessed … 4 ultra-processed).
            // Stored but deliberately not shown next to items — Nutri-Score is
            // the badge; this is detail for the product page.
            $table->unsignedTinyInteger('nova_group')->nullable()->after('eco_score');
            $table->string('net_quantity', 60)->nullable()->after('default_quantity');
            $table->string('serving_size', 60)->nullable()->after('net_quantity');
            // 'open_food_facts' | 'manual' — decides what the pruning job may
            // touch and whether a refresh from barcode makes sense.
            $table->string('source', 30)->default('manual')->after('nova_group');
            $table->timestamp('last_synced_at')->nullable()->after('source');
            // True once a human edited it: API refreshes and pruning leave it be.
            $table->boolean('is_edited')->default(false)->after('last_synced_at');
        });

        // The catalogue is shared, so a barcode identifies exactly one product.
        // Existing rows may collide, so de-duplicate before the index lands.
        $this->dropDuplicateBarcodes();

        Schema::table('products', function (Blueprint $table) {
            $table->unique('barcode');
            $table->index('nutri_score');
        });

        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('shopping_list_id')
                ->constrained('products')->nullOnDelete();
        });

        Schema::create('product_purchases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Where it was bought from; both are optional so a purchase survives
            // the list being cleared or deleted.
            $table->foreignUlid('shopping_list_id')->nullable()->constrained('shopping_lists')->nullOnDelete();
            $table->foreignUlid('shopping_list_item_id')->nullable()->constrained('shopping_list_items')->nullOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 50)->default('piece');
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamp('purchased_at');
            $table->foreignUlid('purchased_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'purchased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchases');

        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropIndex(['nutri_score']);
            $table->dropColumn([
                'ingredients_text', 'allergens', 'nova_group', 'net_quantity',
                'serving_size', 'source', 'last_synced_at', 'is_edited',
            ]);
        });
    }

    /** Keep the oldest row per barcode so the unique index can be created. */
    private function dropDuplicateBarcodes(): void
    {
        $duplicates = DB::table('products')
            ->select('barcode')
            ->whereNotNull('barcode')
            ->groupBy('barcode')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('barcode');

        foreach ($duplicates as $barcode) {
            $keep = DB::table('products')->where('barcode', $barcode)->orderBy('id')->value('id');
            DB::table('products')->where('barcode', $barcode)->where('id', '!=', $keep)->delete();
        }
    }
};
