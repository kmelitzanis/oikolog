@extends('layouts.app')
@section('title', isset($product) && $product->id ? __('Edit Product') : __('Add Product'))

@section('content')
    @php
        $isEdit    = isset($product) && $product->id;
        $nutrition = old('nutrition', $product->nutrition ?? []);
        if (is_string($nutrition)) $nutrition = json_decode($nutrition, true) ?? [];
    @endphp

    <div class="max-w-2xl"
         x-data="{
            lookupLoading: false,
            lookupError: '',
            lookupSuccess: false,
            barcode: '{{ old('barcode', $product->barcode ?? '') }}',
            async lookupBarcode() {
                if (!this.barcode.trim()) return;
                this.lookupLoading = true;
                this.lookupError   = '';
                this.lookupSuccess = false;
                try {
                    const res = await fetch('{{ route('admin.products.lookup-barcode') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ barcode: this.barcode.trim() }),
                    });
                    if (res.status === 404) { this.lookupError = 'Product not found in Open Food Facts.'; return; }
                    if (!res.ok) { this.lookupError = 'Lookup failed — check your connection.'; return; }
                    const d = await res.json();
                    // Fill product fields
                    if (d.name)      document.getElementById('field-name').value        = d.name;
                    if (d.brand)     document.getElementById('field-brand').value       = d.brand;
                    if (d.image_url) document.getElementById('field-image-url').value   = d.image_url;
                    if (d.nutri_score) document.getElementById('field-nutri-score').value = d.nutri_score;
                    if (d.eco_score)   document.getElementById('field-eco-score').value   = d.eco_score;
                    // Fill individual nutrition fields
                    const n = d.nutrition ?? {};
                    ['calories','protein','carbs','fat','fiber','sugar','sodium'].forEach(k => {
                        const el = document.getElementById('nutrition-' + k);
                        if (el && n[k] != null) el.value = n[k];
                    });
                    this.lookupSuccess = true;
                } catch(e) {
                    this.lookupError = 'An unexpected error occurred.';
                } finally {
                    this.lookupLoading = false;
                }
            }
         }">

        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('admin.products.index') }}"
               class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <h1 class="text-xl font-extrabold text-gray-900 dark:text-white">
                {{ $isEdit ? 'Edit Product' : 'Add Product' }}
            </h1>
        </div>

        <form method="POST" enctype="multipart/form-data"
              action="{{ $isEdit ? route('admin.products.update', $product) : route('admin.products.store') }}">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            {{-- ── Barcode lookup ─────────────────────────────────────────── --}}
            <x-card flush class="p-6 mb-4">
                <p class="text-sm font-semibold text-gray-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                    <span class="material-icons-round text-indigo-500">qr_code_scanner</span>
                    Auto-fill from barcode
                </p>
                <div class="flex gap-2">
                    <input type="text" x-model="barcode" name="barcode" id="field-barcode"
                           placeholder="e.g. 5000112637922"
                           class="flex-1 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <button type="button" @click="lookupBarcode()"
                            :disabled="lookupLoading"
                            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                        <span class="material-icons-round text-lg"
                              x-text="lookupLoading ? 'hourglass_top' : 'search'"></span>
                        <span x-text="lookupLoading ? 'Looking up…' : 'Lookup'"></span>
                    </button>
                </div>
                <p x-show="lookupError" x-text="lookupError" x-cloak
                   class="mt-2 text-xs text-red-500 flex items-center gap-1">
                </p>
                <p x-show="lookupSuccess" x-cloak
                   class="mt-2 text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
                    <span class="material-icons-round text-sm">check_circle</span>
                    Fields filled from Open Food Facts.
                </p>
            </x-card>

            {{-- ── Product details ────────────────────────────────────────── --}}
            <x-card flush class="p-6 space-y-5 mb-4">
                <p class="text-sm font-semibold text-gray-700 dark:text-slate-200 -mb-1">Product info</p>

                <div>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Name *</label>
                    <input type="text" name="name" id="field-name" value="{{ old('name', $product->name ?? '') }}"
                           required
                           class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Brand</label>
                        <input type="text" name="brand" id="field-brand"
                               value="{{ old('brand', $product->brand ?? '') }}"
                               class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Category</label>
                        <input type="text" name="category" value="{{ old('category', $product->category ?? '') }}"
                               class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Description</label>
                    <textarea name="description" rows="2"
                              class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">{{ old('description', $product->description ?? '') }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Unit</label>
                        <select name="unit"
                                class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                            @foreach(['piece','kg','g','l','ml','pack','box'] as $u)
                                <option value="{{ $u }}" {{ old('unit', $product->unit ?? 'piece') === $u ? 'selected' : '' }}>{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Default
                            Qty</label>
                        <input type="number" name="default_quantity" step="0.01" min="0.01"
                               value="{{ old('default_quantity', $product->default_quantity ?? 1) }}"
                               class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                {{-- Image --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Image URL (from
                        barcode)</label>
                    <input type="url" name="image_url" id="field-image-url"
                           value="{{ old('image_url', $product->image_url ?? '') }}" placeholder="https://…"
                           class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Upload image
                        (overrides URL)</label>
                    @if(!empty($product->image_path ?? null))
                        <div class="mb-2">
                            <img src="{{ Storage::disk('public')->url($product->image_path) }}"
                                 class="w-24 h-24 object-cover rounded-lg" alt="">
                        </div>
                    @endif
                    <input type="file" name="image" accept="image/*"
                           class="text-sm text-gray-500 dark:text-slate-400">
                </div>
            </x-card>

            {{-- ── Nutrition ────────────────────────────────────────────── --}}
            <x-card flush class="p-6 space-y-4 mb-4">
                <p class="text-sm font-semibold text-gray-700 dark:text-slate-200 flex items-center gap-2">
                    <span class="material-icons-round text-green-500">nutrition</span>
                    Nutrition (per 100 g / serving)
                </p>

                @php
                    $nutritionFields = [
                        'calories' => ['label' => 'Calories',      'unit' => 'kcal'],
                        'protein'  => ['label' => 'Protein',       'unit' => 'g'],
                        'carbs'    => ['label' => 'Carbohydrates', 'unit' => 'g'],
                        'fat'      => ['label' => 'Fat',           'unit' => 'g'],
                        'fiber'    => ['label' => 'Fiber',         'unit' => 'g'],
                        'sugar'    => ['label' => 'Sugar',         'unit' => 'g'],
                        'sodium'   => ['label' => 'Sodium',        'unit' => 'mg'],
                    ];
                @endphp

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach($nutritionFields as $key => $meta)
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-slate-400 mb-1">
                                {{ $meta['label'] }}
                                <span class="text-gray-400">({{ $meta['unit'] }})</span>
                            </label>
                            <input type="number" step="0.01" min="0"
                                   name="nutrition[{{ $key }}]"
                                   id="nutrition-{{ $key }}"
                                   value="{{ old('nutrition.' . $key, $nutrition[$key] ?? '') }}"
                                   placeholder="—"
                                   class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-2 gap-3 pt-1 border-t border-gray-100 dark:border-slate-700">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-slate-400 mb-1">
                            Nutri-Score <span class="text-gray-400">(A–E)</span>
                        </label>
                        <select name="nutri_score" id="field-nutri-score"
                                class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">—</option>
                            @foreach(['a','b','c','d','e'] as $s)
                                <option value="{{ $s }}" {{ strtolower(old('nutri_score', $product->nutri_score ?? '')) === $s ? 'selected' : '' }}>
                                    {{ strtoupper($s) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-slate-400 mb-1">
                            Eco-Score <span class="text-gray-400">(A–E)</span>
                        </label>
                        <select name="eco_score" id="field-eco-score"
                                class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">—</option>
                            @foreach(['a','b','c','d','e'] as $s)
                                <option value="{{ $s }}" {{ strtolower(old('eco_score', $product->eco_score ?? '')) === $s ? 'selected' : '' }}>
                                    {{ strtoupper($s) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </x-card>

            <div class="flex gap-3 mt-2">
                <button type="submit"
                        class="flex-1 sm:flex-none bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-6 py-3 transition">
                    Save
                </button>
                <a href="{{ route('admin.products.index') }}"
                   class="flex-1 sm:flex-none text-center bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 text-gray-700 dark:text-gray-100 text-sm font-semibold rounded-xl px-6 py-3 transition">Cancel</a>
            </div>
        </form>
    </div>
@endsection



