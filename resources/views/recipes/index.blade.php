@extends('layouts.app')
@section('title', __('messages.recipes'))

@section('content')
    <div>
        {{-- Header — the standard 1.6rem title / .82rem sub treatment --}}
        <div class="flex items-end justify-between gap-5 mb-[22px] flex-wrap">
            <div>
                <div class="text-[1.6rem] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white leading-tight">
                    {{ __('messages.recipes') }}
                </div>
                <div class="text-[0.82rem] text-gray-400 dark:text-slate-500 mt-[3px]">
                    {{ trans_choice('messages.recipe_count', $recipes->total(), ['count' => $recipes->total()]) }}
                </div>
            </div>
            <div class="flex items-center gap-[9px] shrink-0">
                <form method="GET" class="relative">
                    <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">search</span>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('messages.search') }}…"
                           class="w-full sm:w-56 h-10 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl pl-9 pr-3 text-[0.82rem] outline-none focus:border-amber-500 dark:text-white transition">
                </form>
                <a href="{{ route('recipes.create') }}"
                   class="h-10 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-[0.82rem] font-bold whitespace-nowrap flex items-center gap-2 transition shadow-[0_6px_18px_rgba(245,158,11,0.32)]">
                    <span class="material-icons-round text-base">add</span>
                    <span class="hidden sm:inline">{{ __('messages.add_recipe') }}</span>
                </a>
            </div>
        </div>

        @if($recipes->isEmpty())
            {{-- Empty state --}}
            <x-card flush class="px-6 py-16 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-linear-to-br from-amber-500 to-amber-400 flex items-center justify-center text-3xl">
                    🍳
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('messages.no_recipes') }}</h3>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-1.5 max-w-md mx-auto">{{ __('messages.no_recipes_hint') }}</p>
                <a href="{{ route('recipes.create') }}"
                   class="inline-flex items-center gap-2 mt-6 bg-amber-500 hover:bg-amber-600 text-slate-900 text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                    <span class="material-icons-round text-lg">add</span>{{ __('messages.create_recipe') }}
                </a>
            </x-card>
        @else
            {{-- The mockup's four-up grid at 16px gap. Each card is a 110px
                 band with the category chip bottom-left, then name and a
                 time / servings line. --}}
            <div x-data="recipesIndex()" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($recipes as $r)
                    @php $total = $r->totalMinutes(); @endphp
                    <div class="group relative bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-[24px] overflow-hidden transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5">
                        {{-- Cover band --}}
                        <a href="{{ route('recipes.show', $r) }}" class="block">
                            <div class="h-[110px] bg-linear-to-br from-amber-500 via-amber-500 to-amber-400 flex items-end p-3 relative">
                                <span class="absolute inset-0 flex items-center justify-center text-5xl drop-shadow-sm pointer-events-none">
                                    {{ $r->emoji ?: '🍽️' }}
                                </span>
                                @if($r->difficulty)
                                    <span class="relative px-2.5 py-1 rounded-full text-[0.64rem] font-semibold bg-slate-900/[0.55] text-white backdrop-blur-sm">
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
                        <div class="p-3.5">
                            <a href="{{ route('recipes.show', $r) }}"
                               class="block text-[0.9rem] font-bold text-gray-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition line-clamp-1">
                                {{ $r->name }}
                            </a>
                            <div class="flex gap-3.5 mt-2 text-[0.7rem] text-gray-400 dark:text-slate-500">
                                @if($total)
                                    <span>{{ $total }} {{ __('messages.min_short') }}</span>
                                @endif
                                <span>{{ trans_choice('messages.serving_count', $r->servings, ['count' => $r->servings]) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">{{ $recipes->links() }}</div>
        @endif
    </div>
@endsection
