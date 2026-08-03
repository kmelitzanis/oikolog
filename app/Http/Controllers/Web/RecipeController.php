<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Services\RecipeImageStore;
use App\Services\RecipeImporter;
use App\Support\Units;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $query = Recipe::where('user_id', auth()->id())->withCount('ingredients');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $recipes = $query->orderByDesc('is_favorite')->orderBy('name')->paginate(24)->withQueryString();

        return view('recipes.index', compact('recipes'));
    }

    public function create()
    {
        return view('recipes.form', ['recipe' => null]);
    }

    public function store(Request $request)
    {
        $v = $this->validateRecipe($request);

        $recipe = DB::transaction(function () use ($v) {
            $recipe = Recipe::create([
                ...$this->recipeAttributes($v),
                'user_id' => auth()->id(),
            ]);
            $this->syncIngredients($recipe, $v['ingredients']);
            $this->syncSteps($recipe, $v['steps'] ?? []);
            return $recipe;
        });

        if ($request->wantsJson()) {
            return response()->json(['url' => route('recipes.show', $recipe)], 201);
        }

        return redirect()->route('recipes.show', $recipe)->with('success', __('messages.recipe_created'));
    }

    public function show(Recipe $recipe)
    {
        $this->authorizeOwner($recipe);
        $recipe->load(['ingredients.product', 'steps']);

        $shoppingLists = ShoppingList::where('user_id', auth()->id())
            ->where('is_completed', false)
            ->orderByDesc('created_at')
            ->get(['id', 'name']);

        return view('recipes.show', compact('recipe', 'shoppingLists'));
    }

    public function edit(Recipe $recipe)
    {
        $this->authorizeOwner($recipe);
        $recipe->load(['ingredients', 'steps']);

        return view('recipes.form', compact('recipe'));
    }

    public function update(Request $request, Recipe $recipe, RecipeImageStore $images)
    {
        $this->authorizeOwner($recipe);
        $v = $this->validateRecipe($request);

        // Replacing the photo should not leave the old file behind on disk.
        $previousImage = $recipe->image_path;
        $newImage = $v['image_path'] ?? null;

        DB::transaction(function () use ($recipe, $v) {
            $recipe->update($this->recipeAttributes($v));
            $recipe->ingredients()->delete();
            $recipe->steps()->delete();
            $this->syncIngredients($recipe, $v['ingredients']);
            $this->syncSteps($recipe, $v['steps'] ?? []);
        });

        if ($previousImage && $previousImage !== $newImage) {
            $images->delete($previousImage);
        }

        if ($request->wantsJson()) {
            return response()->json(['url' => route('recipes.show', $recipe)]);
        }

        return redirect()->route('recipes.show', $recipe)->with('success', __('messages.recipe_updated'));
    }

    public function destroy(Recipe $recipe, RecipeImageStore $images)
    {
        $this->authorizeOwner($recipe);
        $images->delete($recipe->image_path);
        $recipe->delete();

        return redirect()->route('recipes.index')->with('success', __('messages.recipe_deleted'));
    }

    public function toggleFavorite(Recipe $recipe)
    {
        $this->authorizeOwner($recipe);
        $recipe->update(['is_favorite' => ! $recipe->is_favorite]);

        return response()->json(['is_favorite' => $recipe->is_favorite]);
    }

    /** Send this recipe's ingredients (scaled to the requested servings) to a shopping list. */
    public function toShoppingList(Request $request, Recipe $recipe)
    {
        $this->authorizeOwner($recipe);

        $data = $request->validate([
            'shopping_list_id' => ['nullable', 'exists:shopping_lists,id'],
            'new_list_name'    => ['nullable', 'string', 'max:255', 'required_without:shopping_list_id'],
            'servings'         => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $recipe->load('ingredients');
        $ratio = ($data['servings'] ?? null) && $recipe->servings > 0
            ? $data['servings'] / $recipe->servings
            : 1;

        $list = DB::transaction(function () use ($data, $recipe, $ratio) {
            if (!empty($data['shopping_list_id'])) {
                $list = ShoppingList::findOrFail($data['shopping_list_id']);
                abort_unless($list->user_id === auth()->id(), 403);
            } else {
                $list = ShoppingList::create([
                    'user_id' => auth()->id(),
                    'name'    => $data['new_list_name'],
                    'description' => $recipe->name,
                ]);
            }

            foreach ($recipe->ingredients as $ing) {
                $list->items()->create([
                    'name'     => $ing->name,
                    'quantity' => round((float) $ing->quantity * $ratio, 2),
                    'unit'     => Units::canonicalOrDefault($ing->unit),
                ]);
            }

            return $list;
        });

        return response()->json([
            'message' => __('messages.meal_added_to_list', ['count' => $recipe->ingredients->count()]),
            'url'     => route('shopping-list.show', $list),
        ]);
    }

    /**
     * Store a chosen photo straight away and hand back its path.
     *
     * Uploading on pick rather than on submit lets the form preview the image and
     * keeps one code path for both sources: an upload and an import each end up
     * as an `image_path` string that the form posts with the rest of the fields.
     */
    public function uploadImage(Request $request, RecipeImageStore $images)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:8192'],
        ]);

        try {
            $path = $images->storeUpload($request->file('image'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'path' => $path,
            'url'  => \Illuminate\Support\Facades\Storage::disk('public')->url($path),
        ], 201);
    }

    /** Detach and delete a saved recipe's photo. */
    public function destroyImage(Recipe $recipe, RecipeImageStore $images)
    {
        $this->authorizeOwner($recipe);

        $images->delete($recipe->image_path);
        $recipe->update(['image_path' => null]);

        return response()->json(['ok' => true]);
    }

    /**
     * Read a recipe off a web page and return it as form values for review.
     *
     * Nothing is persisted here beyond the downloaded photo — the user checks and
     * corrects the parsed fields, then saves through the normal store/update path.
     * Parsing someone else's page is never exact, so it must not write straight
     * into the database.
     */
    public function import(Request $request, RecipeImporter $importer)
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        try {
            $recipe = $importer->import($data['url']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('messages.import_failed')], 422);
        }

        if (! $recipe['matched'] && empty($recipe['name'])) {
            return response()->json(['message' => __('messages.import_no_recipe_found')], 422);
        }

        $recipe['image_url'] = $recipe['image_path']
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($recipe['image_path'])
            : null;

        return response()->json(['data' => $recipe]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validateRecipe(Request $request): array
    {
        return $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string', 'max:2000'],
            'servings'                 => ['nullable', 'integer', 'min:1', 'max:50'],
            'prep_minutes'             => ['nullable', 'integer', 'min:0', 'max:1440'],
            'cook_minutes'             => ['nullable', 'integer', 'min:0', 'max:1440'],
            'difficulty'               => ['nullable', 'in:easy,medium,hard'],
            'steps'                    => ['nullable', 'array', 'max:200'],
            'steps.*.section'          => ['nullable', 'string', 'max:120'],
            'steps.*.text'             => ['required', 'string', 'max:2000'],
            // Not merely a string: the client posts this back, so it is
            // untrusted and must match a path RecipeImageStore actually created.
            'image_path'               => ['nullable', 'string', 'max:255', function ($attr, $value, $fail) {
                if (! RecipeImageStore::isManagedPath($value)) {
                    $fail(__('messages.image_invalid'));
                }
            }],
            'source_url'               => ['nullable', 'url', 'max:2048'],
            'ingredients'              => ['required', 'array', 'min:1'],
            'ingredients.*.section'    => ['nullable', 'string', 'max:120'],
            'ingredients.*.name'       => ['required', 'string', 'max:255'],
            'ingredients.*.quantity'   => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.unit'       => ['nullable', 'string', 'max:50'],
            'ingredients.*.product_id' => ['nullable', 'exists:products,id'],
        ]);
    }

    private function recipeAttributes(array $v): array
    {
        return [
            'name'         => $v['name'],
            'description'  => $v['description'] ?? null,
            'servings'     => $v['servings'] ?? 2,
            'prep_minutes' => $v['prep_minutes'] ?? null,
            'cook_minutes' => $v['cook_minutes'] ?? null,
            'difficulty'   => $v['difficulty'] ?? null,
            'image_path'   => $v['image_path'] ?? null,
            'source_url'   => $v['source_url'] ?? null,
        ];
    }

    private function syncIngredients(Recipe $recipe, array $ingredients): void
    {
        foreach (array_values($ingredients) as $order => $ing) {
            $recipe->ingredients()->create([
                'product_id' => $ing['product_id'] ?? null,
                'section'    => $this->section($ing['section'] ?? null),
                'sort_order' => $order,
                'name'       => $ing['name'],
                'quantity'   => $ing['quantity'] ?? 1,
                // Whatever spelling arrives, one canonical key is stored.
                'unit'       => Units::canonicalOrDefault($ing['unit'] ?? null),
            ]);
        }
    }

    private function syncSteps(Recipe $recipe, array $steps): void
    {
        foreach (array_values($steps) as $order => $step) {
            $text = trim((string) ($step['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $recipe->steps()->create([
                'section'    => $this->section($step['section'] ?? null),
                'sort_order' => $order,
                'text'       => $text,
            ]);
        }
    }

    /** An empty heading means "ungrouped", which is stored as NULL, not ''. */
    private function section(?string $raw): ?string
    {
        $trimmed = trim((string) $raw);

        return $trimmed === '' ? null : $trimmed;
    }

    private function authorizeOwner(Recipe $recipe): void
    {
        abort_unless($recipe->user_id === auth()->id(), 403);
    }
}
