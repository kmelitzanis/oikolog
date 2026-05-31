<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function index()
    {
        $recipes = Recipe::where('user_id', auth()->id())->orderBy('name')->paginate(20);
        return view('recipes.index', compact('recipes'));
    }

    public function create()
    {
        return view('recipes.create', ['recipe' => new Recipe]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'servings' => 'nullable|integer|min:1',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.name' => 'required|string|max:255',
            'ingredients.*.quantity' => 'nullable|numeric|min:0',
            'ingredients.*.unit' => 'nullable|string|max:50',
            'ingredients.*.product_id' => 'nullable|exists:products,id',
        ]);

        $recipe = Recipe::create([
            'user_id' => auth()->id(),
            'name' => $v['name'],
            'description' => $v['description'] ?? null,
            'servings' => $v['servings'] ?? 1,
        ]);

        foreach ($v['ingredients'] as $ing) {
            $recipe->ingredients()->create([
                'product_id' => $ing['product_id'] ?? null,
                'name' => $ing['name'],
                'quantity' => $ing['quantity'] ?? 1,
                'unit' => $ing['unit'] ?? 'piece',
            ]);
        }

        return redirect()->route('recipes.index')->with('success', 'Recipe created');
    }

    public function show(Recipe $recipe)
    {
        if ($recipe->user_id !== auth()->id()) abort(403);
        $recipe->load('ingredients.product');
        return view('recipes.show', compact('recipe'));
    }

    public function destroy(Recipe $recipe)
    {
        $this->authorize('delete', $recipe);
        $recipe->delete();
        return redirect()->route('recipes.index')->with('success', 'Recipe deleted');
    }
}


