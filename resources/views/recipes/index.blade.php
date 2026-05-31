@extends('layouts.app')
@section('title', __('Recipes'))

@section('content')
    <div class="max-w-4xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Recipes</h1>
            <a href="{{ route('recipes.create') }}"
               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5">Add
                Recipe</a>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-100 dark:border-slate-700">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Ingredients</th>
                    <th class="px-4 py-3">&nbsp;</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-700">
                @forelse($recipes as $r)
                    <tr>
                        <td class="px-4 py-3">{{ $r->name }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $r->ingredients()->count() }} ingredients</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('recipes.show', $r) }}"
                               class="inline-flex items-center gap-2 bg-gray-50 dark:bg-slate-700 hover:bg-gray-100 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-200 rounded-xl px-3 py-1.5 text-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-gray-400">No recipes yet</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $recipes->links() }}</div>
    </div>
@endsection

