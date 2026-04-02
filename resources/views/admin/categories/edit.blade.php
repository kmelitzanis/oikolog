@extends('layouts.app')
@section('title', 'Edit Category')
@section('content')

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.categories.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <span class="material-icons-round text-xl">arrow_back</span>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900">Edit Category</h1>
    </div>

    <form method="POST" action="{{ route('admin.categories.update', $category) }}"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-lg space-y-5">
        @csrf @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">Name *</label>
            <input type="text" name="name" value="{{ old('name', $category->name) }}" required
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">Icon (Material Icons name)</label>
            <div class="flex items-center gap-3"
                 x-data="{ iconVal: '{{ old('icon', $category->icon) }}' }">
                <input type="text" name="icon" x-model="iconVal"
                       class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                <span class="material-icons-round text-3xl text-indigo-500" x-text="iconVal"></span>
            </div>
            <p class="mt-1 text-xs text-gray-400">e.g. bolt, water_drop, home, local_fire_department</p>
            @error('icon')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <div x-data="{ colorVal: '{{ old('color_hex', $category->color_hex) }}' }">
            <label class="block text-sm font-medium text-gray-600 mb-1.5">Color</label>
            <div class="flex items-center gap-3">
                <input type="color" x-model="colorVal"
                       class="w-12 h-10 rounded-xl border border-gray-200 cursor-pointer bg-transparent p-1">
                <input type="text" x-model="colorVal"
                       class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                <input type="hidden" name="color_hex" x-bind:value="colorVal">
            </div>
            @error('color_hex')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-3">
            <input type="checkbox" name="is_system" id="is_system" value="1"
                   class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                {{ old('is_system', $category->is_system) ? 'checked' : '' }}>
            <label for="is_system" class="text-sm font-medium text-gray-700">System category (visible to all
                users)</label>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                <span class="material-icons-round text-lg">save</span> Update Category
            </button>
            <a href="{{ route('admin.categories.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700 transition">Cancel</a>
        </div>
    </form>

@endsection
