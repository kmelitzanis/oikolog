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
            'logo' => ['nullable', 'image', 'max:2048'],
            // Invoice-mail crawling. Validated as regexes so a typo is caught
            // here rather than silently never matching.
            'email_from_pattern'    => ['nullable', 'string', 'max:190', 'regex:/^[^\x00]*$/'],
            'email_subject_pattern' => ['nullable', 'string', 'max:190'],
            'email_amount_pattern'  => ['nullable', 'string', 'max:500'],
        ]);

        foreach (['email_from_pattern', 'email_subject_pattern', 'email_amount_pattern'] as $field) {
            if (filled($data[$field] ?? null) && @preg_match('/' . str_replace('/', '\/', $data[$field]) . '/iu', '') === false) {
                return back()->withInput()->withErrors([$field => __('messages.invalid_regex')]);
            }
        }

        $logoUrl = null;
        if ($request->hasFile('logo')) {
            $logoUrl = $request->file('logo')->store('provider_logos', 'public');
            $logoUrl = '/storage/' . $logoUrl;
        }

        $provider = Provider::create([
            'name' => $data['name'],
            'website' => $data['website'] ?? null,
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'logo_url' => $logoUrl,
            'email_from_pattern'    => $data['email_from_pattern'] ?? null,
            'email_subject_pattern' => $data['email_subject_pattern'] ?? null,
            'email_amount_pattern'  => $data['email_amount_pattern'] ?? null,
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
            'logo' => ['nullable', 'image', 'max:2048'],
            // Invoice-mail crawling. Validated as regexes so a typo is caught
            // here rather than silently never matching.
            'email_from_pattern'    => ['nullable', 'string', 'max:190', 'regex:/^[^\x00]*$/'],
            'email_subject_pattern' => ['nullable', 'string', 'max:190'],
            'email_amount_pattern'  => ['nullable', 'string', 'max:500'],
        ]);

        foreach (['email_from_pattern', 'email_subject_pattern', 'email_amount_pattern'] as $field) {
            if (filled($data[$field] ?? null) && @preg_match('/' . str_replace('/', '\/', $data[$field]) . '/iu', '') === false) {
                return back()->withInput()->withErrors([$field => __('messages.invalid_regex')]);
            }
        }

        $updateData = [
            'name' => $data['name'],
            'website' => $data['website'] ?? null,
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'email_from_pattern'    => $data['email_from_pattern'] ?? null,
            'email_subject_pattern' => $data['email_subject_pattern'] ?? null,
            'email_amount_pattern'  => $data['email_amount_pattern'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            $logoUrl = $request->file('logo')->store('provider_logos', 'public');
            $updateData['logo_url'] = '/storage/' . $logoUrl;
        }

        $provider->update($updateData);
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

