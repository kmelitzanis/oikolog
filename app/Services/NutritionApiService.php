<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NutritionApiService
{
    private const OPEN_FOOD_FACTS_API = 'https://world.openfoodfacts.org/api/v0/product';

    /**
     * Lookup product by barcode using Open Food Facts API
     */
    public function lookupByBarcode(string $barcode): ?array
    {
        try {
            $response = Http::timeout(10)
                ->get(self::OPEN_FOOD_FACTS_API . "/{$barcode}.json");

            if ($response->successful() && $response->json('product')) {
                $parsed = $this->parseOpenFoodFactsProduct($response->json('product'));
                // Trust the barcode we asked for over whatever came back.
                $parsed['barcode'] = $parsed['barcode'] ?: $barcode;

                return $parsed;
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Nutrition API Error (barcode lookup): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Lookup product by query/name
     */
    public function lookupByQuery(string $query): ?array
    {
        try {
            $response = Http::timeout(10)
                ->get(self::OPEN_FOOD_FACTS_API . '/search', [
                    'search_terms' => $query,
                    'action' => 'process',
                    'json' => 1,
                ]);

            if ($response->successful()) {
                $products = $response->json('products', []);
                if (count($products) > 0) {
                    return $this->parseOpenFoodFactsProduct($products[0]);
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Nutrition API Error (query lookup): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse Open Food Facts product response
     */
    private function parseOpenFoodFactsProduct(array $product): array
    {
        $nutrients = $product['nutriments'] ?? [];

        return [
            'source' => 'open_food_facts',
            // Open Food Facts returns the barcode as `code`.
            'barcode' => $product['code'] ?? $product['barcode'] ?? null,
            'name' => $product['product_name'] ?? 'Unknown',
            'brand' => $product['brands'] ?? null,
            'image_url' => $product['image_url'] ?? null,
            'nutrition' => [
                'calories' => $nutrients['energy_kcal'] ?? $nutrients['energy_kcal_100g'] ?? null,
                'protein' => $nutrients['proteins_100g'] ?? null,
                'carbs' => $nutrients['carbohydrates_100g'] ?? null,
                'fat' => $nutrients['fat_100g'] ?? null,
                'saturated_fat' => $nutrients['saturated-fat_100g'] ?? null,
                'fiber' => $nutrients['fiber_100g'] ?? null,
                'sugar' => $nutrients['sugars_100g'] ?? null,
                'salt' => $nutrients['salt_100g'] ?? null,
                'sodium' => $nutrients['sodium_100g'] ?? null,
            ],
            'nutri_score' => $product['nutriscore_grade'] ?? null,
            'eco_score' => $product['ecoscore_grade'] ?? null,
            // How processed the food is, 1–4. Comes free with the same call.
            'nova_group' => isset($product['nova_group']) ? (int) $product['nova_group'] : null,
            'ingredients_text' => $product['ingredients_text'] ?? null,
            'allergens' => $this->tagList($product['allergens_tags'] ?? []),
            'category' => $this->tagList($product['categories_tags'] ?? [])[0] ?? null,
            'net_quantity' => $product['quantity'] ?? null,
            'serving_size' => $product['serving_size'] ?? null,
        ];
    }

    /**
     * Open Food Facts tags come language-prefixed ("en:milk"); strip the prefix
     * and tidy them into readable words.
     */
    private function tagList(array $tags): array
    {
        return array_values(array_filter(array_map(function ($tag) {
            $value = str_contains($tag, ':') ? substr($tag, strpos($tag, ':') + 1) : $tag;

            return trim(str_replace('-', ' ', $value));
        }, $tags)));
    }
}
