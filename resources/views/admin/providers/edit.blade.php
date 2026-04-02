@extends('layouts.app')
@section('title', 'Edit Provider')
@section('content')
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.providers.index') }}"
           class="text-gray-400 hover:text-gray-600 transition">
            <span class="material-icons-round text-xl">arrow_back</span>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900">Edit Provider</h1>
    </div>
    <form method="POST" action="{{ route('admin.providers.update', $provider) }}"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-lg space-y-5">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">Name *</label>
            <input type="text" name="name" value="{{ old('name', $provider->name) }}" required
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-2">Categories * <span class="text-gray-400">(select one or more)</span></label>
            @php $checked = old('category_ids', $selectedCategoryIds); @endphp
            <div class="space-y-2 max-h-52 overflow-y-auto border border-gray-200 rounded-xl p-3 bg-gray-50">
                @foreach($categories as $cat)
                    <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                        <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}"
                               class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            {{ in_array($cat->id, (array) $checked) ? 'checked' : '' }}>
                        <span class="material-icons-round text-base"
                              style="color:{{ $cat->color_hex }}">{{ $cat->icon }}</span>
                        <span class="text-sm text-gray-700 group-hover:text-gray-900">{{ $cat->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('category_ids')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">
                Website <span class="text-gray-400">(optional)</span>
            </label>
            <input type="url" name="website" value="{{ old('website', $provider->website) }}"
                   placeholder="https://example.com"
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
            @error('website')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">
                Phone <span class="text-gray-400">(optional)</span>
            </label>
            <input type="text" name="phone" value="{{ old('phone', $provider->phone) }}"
                   placeholder="+30 210 1234567"
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
            @error('phone')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">
                Notes <span class="text-gray-400">(optional)</span>
            </label>
            <textarea name="notes" rows="3" placeholder="Any additional notes…"
                      class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition resize-none">{{ old('notes', $provider->notes) }}</textarea>
            @error('notes')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                <span class="material-icons-round text-lg">save</span> Update Provider
            </button>
            <a href="{{ route('admin.providers.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700 transition">Cancel</a>
        </div>
    </form>
@endsection
