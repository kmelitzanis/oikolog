@extends('layouts.app')
@section('title', 'Edit Category')
@section('content')

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.categories.index') }}"
           class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition">
            <span class="material-icons-round text-xl">arrow_back</span>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Edit Category</h1>
    </div>

    <form method="POST" action="{{ route('admin.categories.update', $category) }}"
          class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6 max-w-lg space-y-5">
        @csrf @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Name *</label>
            <input type="text" name="name" value="{{ old('name', $category->name) }}" required
                   class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Icon (Material Icons
                name)</label>
            <div class="flex items-center gap-3"
                 x-data="{ iconVal: '{{ old('icon', $category->icon) }}' }">
                <input type="text" name="icon" x-model="iconVal"
                       class="flex-1 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                <span class="material-icons-round text-3xl text-indigo-500" x-text="iconVal"></span>
            </div>
            <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">e.g. bolt, water_drop, home,
                local_fire_department</p>
            @error('icon')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <div x-data="{ colorVal: '{{ old('color_hex', $category->color_hex) }}' }">
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Color</label>
            <div class="flex items-center gap-3">
                <input type="color" x-model="colorVal"
                       class="w-12 h-10 rounded-xl border border-gray-200 dark:border-slate-600 cursor-pointer bg-transparent p-1">
                <input type="text" x-model="colorVal"
                       class="flex-1 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm font-mono text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                <input type="hidden" name="color_hex" x-bind:value="colorVal">
            </div>
            @error('color_hex')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center justify-between bg-gray-50 dark:bg-slate-700/50 rounded-xl px-4 py-3">
            <label for="is_system" class="text-sm font-medium text-gray-700 dark:text-slate-300">System category
                (visible to all users)</label>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="is_system" id="is_system" value="1"
                       class="sr-only peer"
                    {{ old('is_system', $category->is_system) ? 'checked' : '' }}>
                <div class="w-11 h-6 bg-gray-200 dark:bg-slate-600 peer-focus:outline-none rounded-full peer
                            peer-checked:after:translate-x-full peer-checked:after:border-white
                            after:content-[''] after:absolute after:top-0.5 after:left-0.5
                            after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                            peer-checked:bg-green-500"></div>
            </label>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                <span class="material-icons-round text-lg">save</span> Update Category
            </button>
            <a href="{{ route('admin.categories.index') }}"
               class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition">Cancel</a>
        </div>
    </form>

@endsection
