@extends('layouts.app')
@section('title', 'Manage Providers')
@section('content')
    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Providers</h1>
        <a href="{{ route('admin.providers.create', array_filter(['category_id' => request('category_id')])) }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
            <span class="material-icons-round text-lg">add</span> Add Provider
        </a>
    </div>
    {{-- Category filter --}}
    <form method="GET" action="{{ route('admin.providers.index') }}" class="mb-5 flex items-center gap-3 flex-wrap">
        <select name="category_id" onchange="this.form.submit()"
                class="bg-white dark:bg-slate-800 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
            <option value="">All categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('category_id') === $cat->id ? 'selected' : '' }}>
                    {{ $cat->name }}
                </option>
            @endforeach
        </select>
        @if(request('category_id'))
            <a href="{{ route('admin.providers.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700 transition">Clear filter</a>
        @endif
    </form>
    @if(session('success'))
        <div
            class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3">
            <span class="material-icons-round text-base">check_circle</span>
            {{ session('success') }}
        </div>
    @endif
    <div
        class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-gray-100 dark:border-slate-700 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                <th class="px-5 py-3">Name</th>
                <th class="px-5 py-3">Categories</th>
                <th class="px-5 py-3">Website</th>
                <th class="px-5 py-3">Phone</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700">
            @forelse($providers as $provider)
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition">
                    <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $provider->name }}</td>
                    <td class="px-5 py-3">
                        <div class="flex flex-wrap gap-1">
                            @foreach($provider->categories as $cat)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                    style="background:{{ $cat->color_hex }}20; color:{{ $cat->color_hex }}">
                                    <span class="material-icons-round text-xs"
                                          style="font-size:12px">{{ $cat->icon }}</span>
                                    {{ $cat->name }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        @if($provider->website)
                            <a href="{{ $provider->website }}" target="_blank" rel="noopener"
                               class="text-indigo-600 hover:text-indigo-800 transition truncate max-w-45 inline-block">
                                {{ parse_url($provider->website, PHP_URL_HOST) ?? $provider->website }}
                            </a>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-gray-700 dark:text-slate-300">{{ $provider->phone ?? '—' }}</td>
                    <td class="px-5 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <a href="{{ route('admin.providers.edit', $provider) }}"
                               class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 transition">
                                <span class="material-icons-round text-base">edit</span> Edit
                            </a>
                            <form action="{{ route('admin.providers.destroy', $provider) }}" method="POST"
                                  onsubmit="return confirm('Delete provider \'{{ addslashes($provider->name) }}\'?')">
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
                        providers found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
        @if($providers->hasPages())
            <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $providers->links() }}</div>
        @endif
    </div>
@endsection
