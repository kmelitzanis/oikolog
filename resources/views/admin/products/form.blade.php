@extends('layouts.app')
@section('title', isset($product) && $product->id ? __('Edit Product') : __('Add Product'))

@section('content')
    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('admin.products.index') }}"
               class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <h1 class="text-xl font-extrabold text-gray-900 dark:text-white">{{ isset($product) && $product->id ? 'Edit Product' : 'Add Product' }}</h1>
        </div>

        <form method="POST" enctype="multipart/form-data"
              action="{{ isset($product) && $product->id ? route('admin.products.update', $product) : route('admin.products.store') }}">
            @csrf
            @if(isset($product) && $product->id)
                @method('PUT')
            @endif

            <div
                class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6 space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Name *</label>
                    <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" required
                           class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none">
                </div>

                <div>
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none">{{ old('description', $product->description ?? '') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Nutrition
                        (JSON)</label>
                    <textarea name="nutrition" rows="4"
                              class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none">{{ old('nutrition', is_array($product->nutrition ?? null) ? json_encode($product->nutrition, JSON_PRETTY_PRINT) : ($product->nutrition ?? '')) }}</textarea>
                    <p class="text-xs text-gray-400 mt-1">Provide nutrition information as JSON (e.g.
                        {"calories":100,"protein":2}).</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Image</label>
                    @if(!empty($product->image_path))
                        <div class="mb-2">
                            <img src="{{ Storage::disk('public')->url($product->image_path) }}"
                                 class="w-24 h-24 object-cover rounded-lg" alt="">
                        </div>
                    @endif
                    <input type="file" name="image" accept="image/*">
                </div>
            </div>

            <div class="flex gap-3 mt-6">
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

