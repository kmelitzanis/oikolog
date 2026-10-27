<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Recipe;
use App\Support\Units;

/**
 * Work out a recipe's nutrition per serving from its ingredients.
 *
 * Each ingredient is matched to a catalogue product, by the product it is
 * linked to or else by name the way shopping lists match them, and counted
 * from the product's per-100 g figures. That only works for amounts that can
 * be weighed: grams and kilos, and millilitres and litres taken as weighing
 * the same as water. A spoonful, a piece or a pinch has no weight to go on,
 * so those ingredients are reported as skipped rather than guessed at.
 */
class RecipeNutrition
{
    /** Grams per unit, for the units that have a weight. */
    private const GRAMS = ['g' => 1, 'kg' => 1000, 'ml' => 1, 'l' => 1000];

    /**
     * @param  array<int, array{name?: string, quantity?: mixed, unit?: ?string, product_id?: mixed}>  $ingredients
     * @return array{nutrition: array<string, float>, counted: list<string>, skipped: list<string>}
     */
    public function estimate(array $ingredients, int $servings): array
    {
        $servings = max(1, $servings);
        $totals = array_fill_keys(Recipe::NUTRIENTS, 0.0);
        $seen = [];
        $counted = [];
        $skipped = [];

        foreach ($ingredients as $ingredient) {
            $name = trim((string) ($ingredient['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $grams = $this->grams($ingredient['quantity'] ?? null, $ingredient['unit'] ?? null);
            $per100 = $grams === null ? [] : $this->per100g($ingredient['product_id'] ?? null, $name);

            if ($grams === null || $per100 === []) {
                $skipped[] = $name;

                continue;
            }

            foreach ($per100 as $key => $value) {
                $totals[$key] += $value * $grams / 100;
                $seen[$key] = true;
            }
            $counted[] = $name;
        }

        $nutrition = [];
        foreach (Recipe::NUTRIENTS as $key) {
            if (isset($seen[$key])) {
                $nutrition[$key] = round($totals[$key] / $servings, 1);
            }
        }

        return ['nutrition' => $nutrition, 'counted' => $counted, 'skipped' => $skipped];
    }

    private function grams(mixed $quantity, ?string $unit): ?float
    {
        $factor = self::GRAMS[Units::canonical($unit) ?? ''] ?? null;
        $quantity = is_numeric($quantity) ? (float) $quantity : 0.0;

        return $factor !== null && $quantity > 0 ? $quantity * $factor : null;
    }

    /**
     * A product's figures per 100 g, in a recipe's units: sodium in mg (the
     * catalogue keeps grams, as Open Food Facts does), and worked out from
     * salt when only salt is known.
     *
     * @return array<string, float>
     */
    private function per100g(mixed $productId, string $name): array
    {
        $product = ($productId ? Product::find($productId) : null) ?? $this->byName($name);

        $facts = $product?->nutritionFacts() ?? [];
        if ($facts === []) {
            return [];
        }

        if (isset($facts['sodium'])) {
            $facts['sodium'] *= 1000;
        } elseif (isset($facts['salt'])) {
            $facts['sodium'] = $facts['salt'] / 2.5 * 1000;
        }

        return array_intersect_key($facts, array_flip(Recipe::NUTRIENTS));
    }

    /**
     * The catalogue product with this name, ignoring case and accents. Only
     * one with nutrition is any use, so a same-named one without loses.
     */
    private function byName(string $name): ?Product
    {
        return Product::named($name)->whereNotNull('nutrition')->first();
    }
}
