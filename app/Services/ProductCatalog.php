<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one place products enter and leave the shared catalogue.
 *
 * Three doors lead in — scanning a barcode on a list, the product form, and
 * promoting a hand-typed list line — and they all end up here so a barcode
 * never produces two records and API data never silently overwrites something
 * a person corrected by hand.
 */
class ProductCatalog
{
    public function __construct(private NutritionApiService $nutrition) {}

    /**
     * Find the product for a barcode, fetching and storing it the first time.
     *
     * Returns null only when the barcode is unknown to the API and nothing is
     * stored locally — the caller then keeps the item as free text.
     */
    public function findOrCreateByBarcode(string $barcode, ?User $user = null): ?Product
    {
        $existing = Product::where('barcode', $barcode)->first();
        if ($existing) {
            return $existing;
        }

        $data = $this->nutrition->lookupByBarcode($barcode);
        if (!$data) {
            return null;
        }

        // Never let a catalogue problem stop the item reaching the list — the
        // line is what the user asked for, the product is a bonus.
        try {
            return Product::create($this->attributesFromApi($data) + [
                'barcode' => $barcode,
                'user_id' => $user?->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not store product for barcode ' . $barcode . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Re-fetch a product from its barcode.
     *
     * Hand-edited fields are kept: a refresh fills gaps and updates scores, it
     * does not undo someone's correction.
     */
    public function refreshFromApi(Product $product): bool
    {
        if (!$product->isRefreshable()) {
            return false;
        }

        $data = $this->nutrition->lookupByBarcode($product->barcode);
        if (!$data) {
            return false;
        }

        $fresh = $this->attributesFromApi($data);

        if ($product->is_edited) {
            // Only fill what is still empty, plus the scores, which are the
            // whole point of asking again.
            $keep = ['nutri_score', 'eco_score', 'nova_group', 'last_synced_at'];
            $fresh = array_filter(
                $fresh,
                fn($value, $key) => in_array($key, $keep, true) || blank($product->{$key}),
                ARRAY_FILTER_USE_BOTH,
            );
            unset($fresh['source']);
        }

        $product->update($fresh);

        return true;
    }

    /**
     * Attach a hand-typed line to the catalogue, by name.
     *
     * An existing product with the same name is reused, so writing "milk" on
     * three lists builds one history rather than three lookalike entries.
     * Matching is case- and accent-insensitive on the trimmed name.
     */
    public function linkByName(ShoppingListItem $item, ?User $user = null): ?Product
    {
        $name = trim((string) $item->name);
        if ($name === '') {
            return null;
        }

        $product = Product::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? $this->createFromItem($item, $user);

        if ($item->product_id !== $product->id) {
            $item->update(['product_id' => $product->id]);
        }

        return $product;
    }

    /** A product for a hand-typed line, so it can have a page of its own. */
    public function createFromItem(ShoppingListItem $item, ?User $user = null): Product
    {
        $product = Product::create([
            'name' => $item->name,
            'unit' => $item->unit ?: 'piece',
            'default_quantity' => (float) ($item->quantity ?: 1),
            'barcode' => $item->barcode ?: null,
            'nutrition' => $item->nutrition ?: null,
            'source' => Product::SOURCE_MANUAL,
            'user_id' => $user?->id,
        ]);

        $item->update(['product_id' => $product->id]);

        return $product;
    }

    /**
     * Record that an item was bought. Idempotent: ticking an already-ticked
     * item does not double-count it.
     */
    public function recordPurchase(ShoppingListItem $item, User $buyer): ?ProductPurchase
    {
        if (!$item->product_id) {
            return null;
        }

        return DB::transaction(function () use ($item, $buyer) {
            $existing = ProductPurchase::where('shopping_list_item_id', $item->id)->first();
            if ($existing) {
                return $existing;
            }

            return ProductPurchase::create([
                'product_id' => $item->product_id,
                'shopping_list_id' => $item->shopping_list_id,
                'shopping_list_item_id' => $item->id,
                'quantity' => (float) ($item->quantity ?: 1),
                'unit' => $item->unit ?: 'piece',
                'purchased_at' => now(),
                'purchased_by' => $buyer->id,
            ]);
        });
    }

    /** Un-ticking an item takes the purchase back off the history. */
    public function undoPurchase(ShoppingListItem $item): void
    {
        ProductPurchase::where('shopping_list_item_id', $item->id)->delete();
    }

    /**
     * Map an API payload onto product columns.
     *
     * Open Food Facts is crowd-sourced and loose with its values: a grade can
     * come back as "unknown" or "not-applicable", a serving size as "?", and
     * free-text fields can be longer than the columns. Everything is squeezed
     * into shape here so one odd product can't break an insert.
     */
    private function attributesFromApi(array $data): array
    {
        return [
            'name' => $this->text($data['name'] ?? null, 255) ?: 'Unknown',
            'brand' => $this->text($data['brand'] ?? null, 100),
            'image_url' => $this->text($data['image_url'] ?? null, 500),
            'nutrition' => array_filter($data['nutrition'] ?? [], fn($v) => $v !== null) ?: null,
            'nutri_score' => $this->grade($data['nutri_score'] ?? null),
            'eco_score' => $this->grade($data['eco_score'] ?? null),
            'nova_group' => $this->nova($data['nova_group'] ?? null),
            'ingredients_text' => $data['ingredients_text'] ?: null,
            'allergens' => $data['allergens'] ?: null,
            'category' => $this->text($data['category'] ?? null, 100),
            'net_quantity' => $this->text($data['net_quantity'] ?? null, 60),
            'serving_size' => $this->text($data['serving_size'] ?? null, 60),
            'source' => Product::SOURCE_API,
            'last_synced_at' => now(),
        ];
    }

    /** A single a–e grade, or nothing. */
    private function grade(?string $value): ?string
    {
        $grade = strtolower(trim((string) $value));

        return in_array($grade, Product::GRADES, true) ? $grade : null;
    }

    private function nova(mixed $value): ?int
    {
        $nova = (int) $value;

        return $nova >= 1 && $nova <= 4 ? $nova : null;
    }

    /** Trimmed to fit its column; placeholder junk like "?" becomes null. */
    private function text(?string $value, int $limit): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '?' || $value === '-') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }
}
