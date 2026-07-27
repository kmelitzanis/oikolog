@extends('layouts.app')
@section('title', __('messages.recipes'))

@section('content')
    <div class="max-w-6xl mx-auto">
        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ __('messages.recipes') }}</h1>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5">
                    {{ trans_choice('messages.recipe_count', $recipes->total(), ['count' => $recipes->total()]) }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <form method="GET" class="relative">
                    <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('messages.search') }}…"
                           class="w-full sm:w-56 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl pl-10 pr-4 py-2.5 text-sm outline-none focus:border-indigo-500 dark:text-white transition">
                </form>
                <a href="{{ route('recipes.create') }}"
                   class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 shadow-sm transition shrink-0">
                    <span class="material-icons-round text-lg">add</span>
                    <span class="hidden sm:inline">{{ __('messages.add_recipe') }}</span>
                </a>
            </div>
        </div>

        @if($recipes->isEmpty())
            {{-- Empty state --}}
            <x-card flush class="px-6 py-16 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-linear-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-3xl">
                    🍳
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('messages.no_recipes') }}</h3>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-1.5 max-w-md mx-auto">{{ __('messages.no_recipes_hint') }}</p>
                <a href="{{ route('recipes.create') }}"
                   class="inline-flex items-center gap-2 mt-6 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                    <span class="material-icons-round text-lg">add</span>{{ __('messages.create_recipe') }}
                </a>
            </x-card>
        @else
            <div x-data="recipesIndex()" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($recipes as $r)
                    @php $total = $r->totalMinutes(); @endphp
                    <x-card flush class="group relative hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                        {{-- Cover band --}}
                        <a href="{{ route('recipes.show', $r) }}" class="block">
                            <div class="h-28 bg-linear-to-br from-indigo-500 via-indigo-500 to-purple-500 flex items-center justify-center relative">
                                <span class="text-5xl drop-shadow-sm">{{ $r->emoji ?: '🍽️' }}</span>
                                @if($r->difficulty)
                                    <span class="absolute bottom-2.5 left-3 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/20 text-white backdrop-blur-sm">
                                        {{ __('messages.' . $r->difficulty) }}
                                    </span>
                                @endif
                            </div>
                        </a>

                        {{-- Favorite --}}
                        <button type="button" data-fav="{{ $r->is_favorite ? '1' : '0' }}"
                                @click="toggleFavorite('{{ $r->id }}', $el)"
                                class="absolute top-2.5 right-2.5 w-9 h-9 rounded-full bg-white/90 dark:bg-slate-900/80 backdrop-blur flex items-center justify-center shadow-sm transition hover:scale-110 [&[data-fav='1']_.material-icons-round]:text-red-500 [&[data-fav='0']_.material-icons-round]:text-gray-300">
                            <span class="material-icons-round text-xl">favorite</span>
                        </button>

                        {{-- Body --}}
                        <div class="p-4">
                            <a href="{{ route('recipes.show', $r) }}"
                               class="font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition line-clamp-1">
                                {{ $r->name }}
                            </a>
                            @if($r->description)
                                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 line-clamp-2">{{ $r->description }}</p>
                            @endif

                            <div class="flex items-center gap-3 mt-3 text-xs text-gray-500 dark:text-slate-400">
                                <span class="inline-flex items-center gap-1">
                                    <span class="material-icons-round text-sm">restaurant</span>{{ $r->ingredients_count }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <span class="material-icons-round text-sm">group</span>{{ $r->servings }}
                                </span>
                                @if($total)
                                    <span class="inline-flex items-center gap-1">
                                        <span class="material-icons-round text-sm">schedule</span>{{ $total }} {{ __('messages.min_short') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </x-card>
                @endforeach
            </div>

            <div class="mt-6">{{ $recipes->links() }}</div>
        @endif
    </div>
@endsection
