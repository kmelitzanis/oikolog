<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use App\Services\NutritionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Products are one shared catalogue that builds itself from the shopping
 * lists: a barcode creates an entry, a ticked line records a purchase, and the
 * product keeps its page and its history afterwards.
 */
class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeList(User $user): ShoppingList
    {
        return ShoppingList::create(['name' => 'Weekly shop', 'user_id' => $user->id]);
    }

    private function fakeOpenFoodFacts(array $overrides = []): void
    {
        Http::fake([
            'world.openfoodfacts.org/*' => Http::response([
                'product' => array_merge([
                    'code' => '5201234567890',
                    'product_name' => 'Semi-skimmed milk',
                    'brands' => 'Delta',
                    'image_url' => 'https://example.test/milk.jpg',
                    'nutriscore_grade' => 'b',
                    'ecoscore_grade' => 'c',
                    'nova_group' => 1,
                    'quantity' => '1 L',
                    'ingredients_text' => 'Milk',
                    'allergens_tags' => ['en:milk'],
                    'categories_tags' => ['en:dairies'],
                    'nutriments' => [
                        'energy_kcal_100g' => 46,
                        'proteins_100g' => 3.2,
                        'carbohydrates_100g' => 4.7,
                        'fat_100g' => 1.5,
                        'salt_100g' => 0.1,
                    ],
                ], $overrides),
            ], 200),
        ]);
    }

    public function test_a_scanned_barcode_creates_the_product_and_links_the_item(): void
    {
        $this->fakeOpenFoodFacts();
        $user = User::factory()->create();
        $list = $this->makeList($user);

        $this->actingAs($user)
            ->postJson("/api/shopping-lists/{$list->id}/items", [
                'name' => 'Milk',
                'barcode' => '5201234567890',
            ])
            ->assertCreated();

        $product = Product::firstWhere('barcode', '5201234567890');
        $this->assertNotNull($product);
        $this->assertSame('Semi-skimmed milk', $product->name);
        $this->assertSame('b', $product->nutri_score);
        $this->assertSame(1, $product->nova_group);
        $this->assertSame(['milk'], $product->allergens);
        $this->assertSame(Product::SOURCE_API, $product->source);
        $this->assertSame(46.0, $product->nutritionFacts()['calories']);

        $this->assertSame($product->id, $list->items()->first()->product_id);
    }

    /**
     * Open Food Facts is crowd-sourced: grades come back as "unknown", serving
     * sizes as "?", and text can be longer than the columns. None of that may
     * stop the item being added.
     */
    public function test_messy_api_values_do_not_break_the_add(): void
    {
        $this->fakeOpenFoodFacts([
            'ecoscore_grade' => 'unknown',
            'nutriscore_grade' => 'not-applicable',
            'nova_group' => 0,
            'serving_size' => '?',
            'brands' => str_repeat('Very long brand name, ', 20),
            'quantity' => str_repeat('x', 200),
        ]);
        $user = User::factory()->create();
        $list = $this->makeList($user);

        $this->actingAs($user)
            ->postJson("/api/shopping-lists/{$list->id}/items", ['name' => 'Milk', 'barcode' => '5201234567890'])
            ->assertCreated();

        $product = Product::firstWhere('barcode', '5201234567890');
        $this->assertNotNull($product);
        $this->assertNull($product->eco_score);
        $this->assertNull($product->nutri_score);
        $this->assertNull($product->nova_group);
        $this->assertNull($product->serving_size);
        $this->assertLessThanOrEqual(100, mb_strlen($product->brand));
        $this->assertLessThanOrEqual(60, mb_strlen($product->net_quantity));
        $this->assertSame($product->id, $list->items()->first()->product_id);
    }

    public function test_the_same_barcode_is_not_stored_twice(): void
    {
        $this->fakeOpenFoodFacts();
        $user = User::factory()->create();
        $list = $this->makeList($user);

        foreach (['Milk', 'More milk'] as $name) {
            $this->actingAs($user)->postJson("/api/shopping-lists/{$list->id}/items", [
                'name' => $name,
                'barcode' => '5201234567890',
            ])->assertCreated();
        }

        $this->assertSame(1, Product::where('barcode', '5201234567890')->count());
        $this->assertSame(2, $list->items()->whereNotNull('product_id')->count());
    }

    public function test_an_unknown_barcode_falls_back_to_a_product_from_the_name(): void
    {
        Http::fake(['world.openfoodfacts.org/*' => Http::response([], 404)]);
        $user = User::factory()->create();
        $list = $this->makeList($user);

        $this->actingAs($user)
            ->postJson("/api/shopping-lists/{$list->id}/items", ['name' => 'Mystery', 'barcode' => '000'])
            ->assertCreated();

        $product = Product::firstWhere('name', 'Mystery');
        $this->assertNotNull($product);
        $this->assertSame(Product::SOURCE_MANUAL, $product->source);
        // The barcode is kept, so scanning it again finds this entry locally.
        $this->assertSame('000', $product->barcode);
        $this->assertSame($product->id, $list->items()->first()->product_id);
    }

    public function test_ticking_an_item_records_a_purchase_and_unticking_undoes_it(): void
    {
        $this->fakeOpenFoodFacts();
        $user = User::factory()->create();
        $list = $this->makeList($user);

        $this->actingAs($user)->postJson("/api/shopping-lists/{$list->id}/items", [
            'name' => 'Milk', 'barcode' => '5201234567890', 'quantity' => 2, 'unit' => 'l',
        ]);
        $item = $list->items()->first();

        $this->actingAs($user)->patchJson("/api/shopping-lists/{$list->id}/items/{$item->id}/toggle")->assertOk();

        $purchase = ProductPurchase::first();
        $this->assertNotNull($purchase);
        $this->assertSame($item->product_id, $purchase->product_id);
        $this->assertSame('2.00', $purchase->quantity);
        $this->assertSame($user->id, $purchase->purchased_by);

        $this->actingAs($user)->patchJson("/api/shopping-lists/{$list->id}/items/{$item->id}/toggle")->assertOk();
        $this->assertSame(0, ProductPurchase::count());
    }

    public function test_a_hand_typed_line_joins_the_catalogue_too(): void
    {
        $user = User::factory()->create();
        $list = $this->makeList($user);

        $this->actingAs($user)->postJson("/api/shopping-lists/{$list->id}/items", ['name' => 'Bread']);

        $product = Product::firstWhere('name', 'Bread');
        $this->assertNotNull($product);
        $this->assertSame(Product::SOURCE_MANUAL, $product->source);
        $this->assertSame($product->id, $list->items()->first()->product_id);
    }

    public function test_the_same_name_typed_twice_is_one_product(): void
    {
        $user = User::factory()->create();
        $list = $this->makeList($user);

        foreach (['Bread', 'bread', '  BREAD '] as $name) {
            $this->actingAs($user)->postJson("/api/shopping-lists/{$list->id}/items", ['name' => $name]);
        }

        $this->assertSame(1, Product::count());
        $this->assertSame(3, $list->items()->whereNotNull('product_id')->count());
    }

    public function test_typed_items_therefore_build_purchase_history(): void
    {
        $user = User::factory()->create();
        $list = $this->makeList($user);

        $this->actingAs($user)->postJson("/api/shopping-lists/{$list->id}/items", ['name' => 'Bread']);
        $item = $list->items()->first();

        $this->actingAs($user)->patchJson("/api/shopping-lists/{$list->id}/items/{$item->id}/toggle");

        $this->assertSame(1, ProductPurchase::count());
    }

    public function test_suggestions_come_from_what_has_been_bought_before(): void
    {
        $user = User::factory()->create();
        $rare = Product::create(['name' => 'Bread rolls']);
        $usual = Product::create(['name' => 'Bread, sliced']);
        Product::create(['name' => 'Cheese']);

        foreach ([$usual, $usual, $rare] as $p) {
            ProductPurchase::create([
                'product_id' => $p->id, 'quantity' => 1, 'unit' => 'piece',
                'purchased_at' => now(), 'purchased_by' => $user->id,
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/products/suggest?q=bre')->assertOk();

        $names = array_column($response->json(), 'name');
        $this->assertSame(['Bread, sliced', 'Bread rolls'], $names);

        // Too short a term returns nothing rather than the whole catalogue.
        $this->assertSame([], $this->actingAs($user)->getJson('/api/products/suggest?q=b')->json());
    }

    public function test_the_buying_rhythm_needs_three_purchases(): void
    {
        $user = User::factory()->create();
        $product = Product::create(['name' => 'Milk']);

        $buy = fn(int $daysAgo) => ProductPurchase::create([
            'product_id' => $product->id, 'quantity' => 1, 'unit' => 'piece',
            'purchased_at' => now()->subDays($daysAgo), 'purchased_by' => $user->id,
        ]);

        $buy(14);
        $buy(7);
        $this->assertNull($product->averageDaysBetweenPurchases());

        $buy(0);
        $this->assertSame(7, $product->averageDaysBetweenPurchases());
        $this->assertSame(now()->addDays(7)->toDateString(), $product->expectedNextPurchase()->toDateString());
    }

    public function test_promoting_an_already_linked_item_reuses_its_product(): void
    {
        $this->fakeOpenFoodFacts();
        $user = User::factory()->create();
        $list = $this->makeList($user);

        $this->actingAs($user)->postJson("/api/shopping-lists/{$list->id}/items", [
            'name' => 'Milk', 'barcode' => '5201234567890',
        ]);
        $item = $list->items()->first();

        $this->actingAs($user)->post(route('products.promote', $item))
            ->assertRedirect(route('products.show', $item->product_id));

        $this->assertSame(1, Product::count());
    }

    public function test_another_users_list_line_cannot_be_promoted(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = $this->makeList($owner);
        $item = ShoppingListItem::create(['shopping_list_id' => $list->id, 'name' => 'Bread']);

        $this->actingAs($stranger)->post(route('products.promote', $item))->assertForbidden();
    }

    public function test_a_refresh_keeps_hand_edited_values(): void
    {
        $this->fakeOpenFoodFacts();
        $user = User::factory()->create();

        $product = Product::create([
            'name' => 'My own name for it',
            'barcode' => '5201234567890',
            'source' => Product::SOURCE_MANUAL,
            'is_edited' => true,
        ]);

        $this->actingAs($user)->post(route('products.refresh', $product))->assertRedirect();

        $product->refresh();
        // The name stays; the score, which is why we asked, comes through.
        $this->assertSame('My own name for it', $product->name);
        $this->assertSame('b', $product->nutri_score);
        $this->assertSame('Delta', $product->brand); // was empty, so it was filled
    }

    public function test_a_refresh_overwrites_an_untouched_product(): void
    {
        $this->fakeOpenFoodFacts();
        $user = User::factory()->create();

        $product = Product::create([
            'name' => 'Stale name',
            'barcode' => '5201234567890',
            'source' => Product::SOURCE_API,
        ]);

        $this->actingAs($user)->post(route('products.refresh', $product))->assertRedirect();

        $this->assertSame('Semi-skimmed milk', $product->fresh()->name);
    }

    public function test_a_product_with_history_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $product = Product::create(['name' => 'Milk']);
        ProductPurchase::create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit' => 'piece',
            'purchased_at' => now(),
            'purchased_by' => $user->id,
        ]);

        $this->actingAs($user)->delete(route('products.destroy', $product))->assertRedirect();

        $this->assertNotNull($product->fresh());
    }

    public function test_editing_a_product_marks_it_as_hand_edited(): void
    {
        $user = User::factory()->create();
        $product = Product::create(['name' => 'Milk', 'source' => Product::SOURCE_API]);

        $this->actingAs($user)->put(route('products.update', $product), [
            'name' => 'Milk 1L',
            'nutri_score' => 'c',
            'nutrition' => ['calories' => '50', 'protein' => ''],
        ])->assertRedirect(route('products.show', $product));

        $product->refresh();
        $this->assertTrue($product->is_edited);
        $this->assertSame('c', $product->nutri_score);
        // Blank boxes clear rather than store zeroes.
        $this->assertSame(['calories' => 50.0], $product->nutritionFacts());
    }

    public function test_the_api_sends_the_product_so_the_list_can_show_a_badge(): void
    {
        $this->fakeOpenFoodFacts();
        $user = User::factory()->create();
        $list = $this->makeList($user);

        $this->actingAs($user)->postJson("/api/shopping-lists/{$list->id}/items", [
            'name' => 'Milk', 'barcode' => '5201234567890',
        ]);

        $this->actingAs($user)->getJson("/api/shopping-lists/{$list->id}")
            ->assertOk()
            ->assertJsonPath('items.0.product.nutri_score', 'b');
    }

    public function test_pruning_spares_products_that_are_used_bought_or_edited(): void
    {
        $user = User::factory()->create();
        $list = $this->makeList($user);
        // `created_at` is not fillable, so age has to be set after the fact.
        $age = function (Product $p): Product {
            $p->forceFill(['created_at' => now()->subMonths(2)])->save();

            return $p;
        };

        $orphan = $age(Product::create(['name' => 'Scanned once', 'source' => Product::SOURCE_API]));
        $edited = $age(Product::create(['name' => 'Corrected', 'source' => Product::SOURCE_API, 'is_edited' => true]));
        $listed = $age(Product::create(['name' => 'On a list', 'source' => Product::SOURCE_API]));
        $bought = $age(Product::create(['name' => 'Bought', 'source' => Product::SOURCE_API]));
        $manual = $age(Product::create(['name' => 'By hand', 'source' => Product::SOURCE_MANUAL]));

        ShoppingListItem::create(['shopping_list_id' => $list->id, 'product_id' => $listed->id, 'name' => 'On a list']);
        ProductPurchase::create([
            'product_id' => $bought->id, 'quantity' => 1, 'unit' => 'piece',
            'purchased_at' => now(), 'purchased_by' => $user->id,
        ]);

        $this->artisan('products:prune')->assertSuccessful();

        $this->assertNull($orphan->fresh());
        foreach ([$edited, $listed, $bought, $manual] as $kept) {
            $this->assertNotNull($kept->fresh());
        }
    }

    public function test_pruning_leaves_recent_products_alone(): void
    {
        $fresh = Product::create(['name' => 'Scanned today', 'source' => Product::SOURCE_API]);

        $this->artisan('products:prune')->assertSuccessful();

        $this->assertNotNull($fresh->fresh());
    }

    public function test_product_pages_render(): void
    {
        $user = User::factory()->create();
        $product = Product::create(['name' => 'Milk', 'nutri_score' => 'b', 'nova_group' => 1]);

        $this->actingAs($user)->get(route('products.index'))->assertOk()->assertSee('Milk');
        $this->actingAs($user)->get(route('products.index', ['grade' => 'b']))->assertOk()->assertSee('Milk');
        $this->actingAs($user)->get(route('products.show', $product))->assertOk();
        $this->actingAs($user)->get(route('products.create'))->assertOk();
        $this->actingAs($user)->get(route('products.edit', $product))->assertOk();
    }
}
