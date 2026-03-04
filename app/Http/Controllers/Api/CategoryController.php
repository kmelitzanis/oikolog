<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $categories = Category::where('is_system', true)
            ->when($user->family_id, fn($q) => $q->orWhere('family_id', $user->family_id))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn($c) => [
                'id'        => $c->id,
                'name'      => $c->name,
                'icon'      => $c->icon,
                'color_hex' => $c->color_hex,
                'is_system' => $c->is_system,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->family_id, 422, 'Join a family to create custom categories.');

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:60'],
            'icon'      => ['nullable', 'string', 'max:60'],
            'color_hex' => ['nullable', 'string', 'max:9'],
        ]);

        $category = Category::create([
            ...$data,
            'family_id' => $request->user()->family_id,
            'is_system' => false,
        ]);

        return response()->json(['data' => [
            'id'        => $category->id,
            'name'      => $category->name,
            'icon'      => $category->icon,
            'color_hex' => $category->color_hex,
            'is_system' => $category->is_system,
        ]], 201);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        abort_if($category->is_system, 403, 'Cannot delete system categories.');
        abort_unless($category->family_id === $request->user()->family_id, 403, 'Not yours.');
        $category->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
