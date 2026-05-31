<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\NutritionApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::whereNull('user_id')->orderBy('name')->paginate(20);
        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.products.form', ['product' => new Product]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('product_images', 'public');
        }

        // user_id stays null → global / admin product
        Product::create($data);

        return redirect()->route('admin.products.index')->with('success', __('Product created'));
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateProduct($request);

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->file('image')->store('product_images', 'public');
        }

        $product->update($data);

        return redirect()->route('admin.products.index')->with('success', __('Product updated'));
    }

    public function destroy(Product $product)
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', __('Product deleted'));
    }

    /**
     * AJAX barcode lookup — returns JSON from Open Food Facts.
     */
    public function lookupBarcode(Request $request, NutritionApiService $nutritionService)
    {
        $request->validate(['barcode' => 'required|string|max:50']);

        $data = $nutritionService->lookupByBarcode($request->input('barcode'));

        if (!$data) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json($data);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function validateProduct(Request $request): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'brand' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'default_quantity' => 'nullable|numeric|min:0.01',
            'image_url' => 'nullable|url|max:500',
            'image' => 'nullable|image|max:2048',
            'nutri_score' => 'nullable|string|max:5',
            'eco_score' => 'nullable|string|max:5',
            // individual nutrition sub-fields
            'nutrition.calories' => 'nullable|numeric|min:0',
            'nutrition.protein' => 'nullable|numeric|min:0',
            'nutrition.carbs' => 'nullable|numeric|min:0',
            'nutrition.fat' => 'nullable|numeric|min:0',
            'nutrition.fiber' => 'nullable|numeric|min:0',
            'nutrition.sugar' => 'nullable|numeric|min:0',
            'nutrition.sodium' => 'nullable|numeric|min:0',
        ];

        $validated = $request->validate($rules);

        // Build the nutrition JSON from individual fields; strip nulls
        $nutritionInput = $request->input('nutrition', []);
        $nutrition = array_filter(
            array_map('floatval', array_filter($nutritionInput, fn($v) => $v !== null && $v !== '')),
        );
        $validated['nutrition'] = empty($nutrition) ? null : $nutrition;
        unset($validated['image']); // handled separately (file upload)

        return $validated;
    }
}


