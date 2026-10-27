<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Recipes in the shape Mealie's API gives them, for apps that can read from
 * Mealie but not from Oikolog. SparkyFitness is the one this is written for:
 * it adds Oikolog as a "Mealie" data provider (base URL + API key), searches
 * recipes by name and imports the one you pick as a food, per serving.
 *
 * Only the parts SparkyFitness reads are reproduced, with the fields it maps:
 * name, slug, id, image, orgURL, recipeServings, recipeYield and nutrition.
 * The rest of a Mealie recipe (ingredients, method, times) is filled in too,
 * so a client that shows a recipe has something to show.
 *
 * Access is a personal access token with the `recipes:read` ability, made in
 * Settings. It reads the token owner's recipes and nothing else.
 */
class MealieRecipeController extends Controller
{
    public const ABILITY = 'recipes:read';

    /** Photo sizes Mealie serves; Oikolog keeps one photo and serves it for all. */
    private const IMAGE_FILES = ['original.webp', 'min-original.webp', 'tiny-original.webp'];

    /**
     * GET /api/recipes. SparkyFitness searches with Mealie's query language,
     * `queryFilter=name LIKE "%soup%"`; that one pattern is understood, and so
     * is Mealie's plain `search=` parameter.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeToken($request);

        $perPage = min(100, max(1, $request->integer('perPage', 50)));
        $page = max(1, $request->integer('page', 1));

        $query = Recipe::where('user_id', $request->user()->id)->orderBy('name');

        if (($term = $this->searchTerm($request)) !== null) {
            $query->where('name', 'like', '%' . addcslashes($term, '%_\\') . '%');
        }

        $total = (clone $query)->count();
        $recipes = $query->forPage($page, $perPage)->get();
        $totalPages = (int) ceil($total / $perPage);

        return response()->json([
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $totalPages,
            'items'       => $recipes->map(fn (Recipe $recipe) => $this->summary($recipe))->values(),
            'next'        => $page < $totalPages ? $page + 1 : null,
            'previous'    => $page > 1 ? $page - 1 : null,
        ]);
    }

    /** GET /api/recipes/{slug}. The slug is the recipe's uuid, which never changes on a rename. */
    public function show(Request $request, string $slug): JsonResponse
    {
        $this->authorizeToken($request);

        $recipe = Recipe::where('user_id', $request->user()->id)
            ->where('uuid', $slug)
            ->with(['ingredients', 'steps'])
            ->first();

        if (! $recipe) {
            return response()->json(['detail' => 'Recipe not found'], 404);
        }

        return response()->json([
            ...$this->summary($recipe),
            'recipeIngredient'   => $recipe->ingredients->sortBy('sort_order')->values()->map(fn ($ingredient, $i) => [
                'referenceId'  => "{$recipe->uuid}-i{$i}",
                'title'        => $ingredient->section,
                'note'         => $ingredient->name,
                'quantity'     => (float) $ingredient->quantity,
                'unit'         => null,
                'food'         => null,
                'display'      => trim(rtrim(rtrim(number_format((float) $ingredient->quantity, 2, '.', ''), '0'), '.')
                    . ' ' . $ingredient->unitLabel() . ' ' . $ingredient->name),
            ]),
            'recipeInstructions' => $recipe->steps->sortBy('sort_order')->values()->map(fn ($step, $i) => [
                'id'    => "{$recipe->uuid}-s{$i}",
                'title' => $step->section ?? '',
                'text'  => $step->text,
            ]),
            'nutrition'          => $this->nutrition($recipe),
        ]);
    }

    /**
     * GET /api/media/recipes/{uuid}/images/{file}. Unauthenticated, as in
     * Mealie: clients hotlink it. The uuid is the only key, and it is not
     * guessable from another recipe's.
     */
    public function image(string $uuid, string $file)
    {
        abort_unless(in_array($file, self::IMAGE_FILES, true), 404);

        $path = Recipe::where('uuid', $uuid)->value('image_path');
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control'          => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeToken(Request $request): void
    {
        abort_unless($request->user()?->tokenCan(self::ABILITY), 403, 'This token cannot read recipes.');
    }

    /** `name LIKE "%soup%"`, Mealie's `search=`, or nothing. */
    private function searchTerm(Request $request): ?string
    {
        $filter = (string) $request->query('queryFilter', '');
        if (preg_match('/name\s+LIKE\s+"%?(.*?)%?"/i', $filter, $m)) {
            $term = trim($m[1]);
        } else {
            $term = trim((string) $request->query('search', ''));
        }

        return $term === '' ? null : $term;
    }

    /** The fields of a Mealie recipe summary that have an Oikolog equivalent. */
    private function summary(Recipe $recipe): array
    {
        $total = (int) $recipe->prep_minutes + (int) $recipe->cook_minutes;

        return [
            'id'             => $recipe->uuid,
            'slug'           => $recipe->uuid,
            'name'           => $recipe->name,
            'description'    => $recipe->description,
            // Mealie puts a short key here and builds the URL from the id;
            // clients only test it for being set.
            'image'          => $recipe->image_path ? 'original' : null,
            'orgURL'         => $recipe->source_url,
            'recipeServings' => (float) $recipe->servings,
            'recipeYield'    => $recipe->servings ? $recipe->servings . ' ' . mb_strtolower(__('messages.servings')) : null,
            'prepTime'       => $recipe->prep_minutes ? "{$recipe->prep_minutes} min" : null,
            'performTime'    => $recipe->cook_minutes ? "{$recipe->cook_minutes} min" : null,
            'totalTime'      => $total > 0 ? "{$total} min" : null,
            'rating'         => $recipe->is_favorite ? 5 : null,
            'dateAdded'      => $recipe->created_at?->toDateString(),
            'dateUpdated'    => $recipe->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Mealie's nutrition block: per serving, values as strings, sodium in mg.
     * Oikolog keeps the same units, so this only renames.
     */
    private function nutrition(Recipe $recipe): array
    {
        $names = [
            'calories'      => 'calories',
            'protein'       => 'proteinContent',
            'carbs'         => 'carbohydrateContent',
            'sugar'         => 'sugarContent',
            'fat'           => 'fatContent',
            'saturated_fat' => 'saturatedFatContent',
            'fiber'         => 'fiberContent',
            'sodium'        => 'sodiumContent',
        ];

        $out = array_fill_keys(array_values($names), null);
        foreach ($recipe->nutritionFacts() as $key => $value) {
            $out[$names[$key]] = (string) $value;
        }

        return $out;
    }
}
