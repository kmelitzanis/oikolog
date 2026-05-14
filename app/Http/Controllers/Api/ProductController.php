<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::forUser($request->user())->orderBy('name');
        if ($search = $request->input('search')) {
            $query->search($search);
        }
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }
        return response()->json($query->paginate($request->integer('per_page', 50)));
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = Product::forUser($request->user())
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();
        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'brand' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'default_quantity' => 'nullable|numeric|min:0.01',
            'image_url' => 'nullable|url|max:500',
            'nutrition' => 'nullable|array',
        ]);
        $validated['user_id'] = $request->user()->id;
        $validated['unit'] ??= 'piece';
        $validated['default_quantity'] ??= 1;
        return response()->json(Product::create($validated), 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->user_id === $request->user()->id, 403);
        return response()->json($product);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->user_id === $request->user()->id, 403);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'brand' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'default_quantity' => 'nullable|numeric|min:0.01',
            'image_url' => 'nullable|url|max:500',
            'nutrition' => 'nullable|array',
        ]);
        $product->update($validated);
        return response()->json($product);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->user_id === $request->user()->id, 403);
        $product->delete();
        return response()->json(null, 204);
    }
}
