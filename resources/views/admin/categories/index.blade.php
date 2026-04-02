@extends('layouts.app')
@section('title', 'Manage Categories')
@section('content')

    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Categories</h1>
        <a href="{{ route('admin.categories.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
            <span class="material-icons-round text-lg">add</span> Add Category
        </a>
    </div>

    <div
        class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-gray-100 dark:border-slate-700 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                <th class="px-5 py-3">Name</th>
                <th class="px-5 py-3">Icon</th>
                <th class="px-5 py-3">Color</th>
                <th class="px-5 py-3">System</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700">
            @forelse($categories as $cat)
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition">
                    <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $cat->name }}</td>
                    <td class="px-5 py-3">
                        <span class="material-icons-round text-xl"
                              style="color:{{ $cat->color_hex }}">{{ $cat->icon }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-4 h-4 rounded-full inline-block border border-gray-200 dark:border-slate-600"
                                  style="background:{{ $cat->color_hex }}"></span>
                            <span
                                class="font-mono text-xs text-gray-500 dark:text-slate-400">{{ $cat->color_hex }}</span>
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        @if($cat->is_system)
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">System</span>
                        @else
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Custom</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <a href="{{ route('admin.categories.edit', $cat) }}"
                               class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 transition">
                                <span class="material-icons-round text-base">edit</span> Edit
                            </a>
                            <form action="{{ route('admin.categories.destroy', $cat) }}" method="POST"
                                  onsubmit="return confirm('Delete category \'{{ addslashes($cat->name) }}\'?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-700 transition">
                                    <span class="material-icons-round text-base">delete</span> Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-10 text-center text-gray-400 dark:text-slate-500 text-sm">No
                        categories found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
        @if($categories->hasPages())
            <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $categories->links() }}</div>
        @endif
    </div>

@endsection
