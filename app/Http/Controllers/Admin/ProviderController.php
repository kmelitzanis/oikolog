<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Provider;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function index(Request $request)
    {
        $categoryId = $request->get('category_id');
        $query = Provider::with('categories')->orderBy('name');

        if ($categoryId) {
            $query->whereHas('categories', fn($q) => $q->where('categories.id', $categoryId));
        }

        $providers = $query->paginate(30)->withQueryString();
        $categories = Category::orderBy('name')->get();
        $selectedCat = $categoryId ? Category::find($categoryId) : null;

        return view('admin.providers.index', compact('providers', 'categories', 'selectedCat'));
    }

    public function create(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        $selectedCatId = $request->get('category_id');
        return view('admin.providers.create', compact('categories', 'selectedCatId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['exists:categories,id'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $provider = Provider::create([
            'name' => $data['name'],
            'website' => $data['website'] ?? null,
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $provider->categories()->sync($data['category_ids']);

        return redirect()
            ->route('admin.providers.index', ['category_id' => $data['category_ids'][0]])
            ->with('success', 'Provider created.');
    }

    public function edit(Provider $provider)
    {
        $provider->load('categories');
        $categories = Category::orderBy('name')->get();
        $selectedCategoryIds = $provider->categories->pluck('id')->all();
        return view('admin.providers.edit', compact('provider', 'categories', 'selectedCategoryIds'));
    }

    public function update(Request $request, Provider $provider)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['exists:categories,id'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $provider->update([
            'name' => $data['name'],
            'website' => $data['website'] ?? null,
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $provider->categories()->sync($data['category_ids']);

        return redirect()
            ->route('admin.providers.index', ['category_id' => $data['category_ids'][0]])
            ->with('success', 'Provider updated.');
    }

    public function destroy(Provider $provider)
    {
        $provider->categories()->detach();
        $provider->delete();
        return redirect()
            ->route('admin.providers.index')
            ->with('success', 'Provider deleted.');
    }
}

