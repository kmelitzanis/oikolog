@extends('layouts.app')
@section('title', 'Edit Provider')
@section('content')
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.providers.index') }}"
           class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition">
            <span class="material-icons-round text-xl">arrow_back</span>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Edit Provider</h1>
    </div>
    <form method="POST" action="{{ route('admin.providers.update', $provider) }}"
          enctype="multipart/form-data"
          class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6 max-w-lg space-y-5">
        @csrf @method('PUT')
        {{-- Logo --}}
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                Logo <span class="text-gray-400 dark:text-slate-500">(optional, image)</span>
            </label>
            @if($provider->logo_url)
                <div class="mb-2 flex items-center gap-3">
                    <img src="{{ $provider->logo_url }}" alt="{{ $provider->name }}"
                         class="w-14 h-14 object-contain rounded-xl border border-gray-100 dark:border-slate-600 bg-white dark:bg-slate-700 p-1">
                    <span class="text-xs text-gray-400 dark:text-slate-500">Current logo</span>
                </div>
            @endif
            <div id="logo-drop-area"
                 class="w-full flex flex-col items-center justify-center border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-xl p-4 bg-gray-50 dark:bg-slate-700 cursor-pointer transition hover:border-amber-400 mb-2">
                <span id="logo-drop-text" class="text-gray-400 dark:text-slate-500 text-sm mb-2">Drag & drop logo here or click to select</span>
                <input id="logo-input" type="file" name="logo" accept="image/*" class="hidden">
                <img id="logo-preview" src="" alt="Logo preview"
                     class="hidden w-14 h-14 object-contain rounded-xl border border-gray-100 dark:border-slate-600 bg-white dark:bg-slate-700 p-1 mt-2">
            </div>
            <script>
                // Logo drag/drop and preview handled by resources/js/pages/file-previews.js
            </script>
            @error('logo')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Name *</label>
            <input type="text" name="name" value="{{ old('name', $provider->name) }}" required
                   class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition">
            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-2">Categories * <span
                    class="text-gray-400 dark:text-slate-500">(select one or more)</span></label>
            @php $checked = old('category_ids', $selectedCategoryIds); @endphp
            <div
                class="space-y-2 max-h-52 overflow-y-auto border border-gray-200 dark:border-slate-600 rounded-xl p-3 bg-gray-50 dark:bg-slate-700">
                @foreach($categories as $cat)
                    <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                        <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}"
                               class="w-4 h-4 rounded border-gray-300 dark:border-slate-500 text-amber-700 focus:ring-amber-500"
                            {{ in_array($cat->id, (array) $checked) ? 'checked' : '' }}>
                        <span class="material-icons-round text-base"
                              style="color:{{ $cat->color_hex }}">{{ $cat->icon }}</span>
                        <span
                            class="text-sm text-gray-700 dark:text-slate-300 group-hover:text-gray-900 dark:group-hover:text-white">{{ $cat->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('category_ids')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                Website <span class="text-gray-400 dark:text-slate-500">(optional)</span>
            </label>
            <input type="url" name="website" value="{{ old('website', $provider->website) }}"
                   placeholder="https://example.com"
                   class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition">
            @error('website')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                Phone <span class="text-gray-400 dark:text-slate-500">(optional)</span>
            </label>
            <input type="text" name="phone" value="{{ old('phone', $provider->phone) }}"
                   placeholder="+30 210 1234567"
                   class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition">
            @error('phone')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                Notes <span class="text-gray-400 dark:text-slate-500">(optional)</span>
            </label>
            <textarea name="notes" rows="3" placeholder="Any additional notes…"
                      class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition resize-none">{{ old('notes', $provider->notes) }}</textarea>
            @error('notes')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-slate-900 text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                <span class="material-icons-round text-lg">save</span> Update Provider
            </button>
            <a href="{{ route('admin.providers.index') }}"
               class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition">Cancel</a>
        </div>
    </form>
@endsection
