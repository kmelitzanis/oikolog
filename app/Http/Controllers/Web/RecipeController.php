<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\ShoppingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $recipe->load('ingredients.product');

        $shoppingLists = ShoppingList::where('user_id', auth()->id())
            ->where('is_completed', false)
            ->orderByDesc('created_at')
            ->get(['id', 'name']);

        return view('recipes.show', compact('recipe', 'shoppingLists'));
    }

    public function edit(Recipe $recipe)
    {
        $this->authorizeOwner($recipe);
        $recipe->load('ingredients');

        return view('recipes.form', compact('recipe'));
    }

    public function update(Request $request, Recipe $recipe)
    {
        $this->authorizeOwner($recipe);
        $v = $this->validateRecipe($request);

        DB::transaction(function () use ($recipe, $v) {
            $recipe->update($this->recipeAttributes($v));
            $recipe->ingredients()->delete();
            $this->syncIngredients($recipe, $v['ingredients']);
        });

        if ($request->wantsJson()) {
            return response()->json(['url' => route('recipes.show', $recipe)]);
        }

        return redirect()->route('recipes.show', $recipe)->with('success', __('messages.recipe_updated'));
    }

    public function destroy(Recipe $recipe)
    {
        $this->authorizeOwner($recipe);
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
                    'unit'     => $ing->unit ?: 'piece',
                ]);
            }

            return $list;
        });

        return response()->json([
            'message' => __('messages.meal_added_to_list', ['count' => $recipe->ingredients->count()]),
            'url'     => route('shopping-list.show', $list),
        ]);
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
            'instructions'             => ['nullable', 'string', 'max:20000'],
            'emoji'                    => ['nullable', 'string', 'max:16'],
            'ingredients'              => ['required', 'array', 'min:1'],
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
            'instructions' => $v['instructions'] ?? null,
            'emoji'        => $v['emoji'] ?? null,
        ];
    }

    private function syncIngredients(Recipe $recipe, array $ingredients): void
    {
        foreach ($ingredients as $ing) {
            $recipe->ingredients()->create([
                'product_id' => $ing['product_id'] ?? null,
                'name'       => $ing['name'],
                'quantity'   => $ing['quantity'] ?? 1,
                'unit'       => $ing['unit'] ?? 'piece',
            ]);
        }
    }

    private function authorizeOwner(Recipe $recipe): void
    {
        abort_unless($recipe->user_id === auth()->id(), 403);
    }
}
