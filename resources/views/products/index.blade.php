@extends('layouts.app')
@section('title', __('messages.products'))

{{--
    The product library. Nobody fills this in by hand as a chore — it is what
    has been scanned or entered from the shopping lists, so the page is built
    for finding something again rather than for data entry.
--}}

@section('content')

    <div class="flex items-end justify-between gap-5 mb-[22px] flex-wrap">
        <div class="flex items-end gap-3 min-w-0">
            <a href="{{ route('shopping-list.index') }}"
               class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0 pb-1">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <div class="min-w-0">
            <div class="text-[1.6rem] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white leading-tight">
                {{ __('messages.bought_products') }}
            </div>
            <div class="text-[0.82rem] text-gray-400 dark:text-slate-500 mt-[3px]">
                {{ __('messages.products_summary', [
                    'total' => $stats['total'],
                    'scored' => $stats['scored'],
                ]) }}
            </div>
            </div>
        </div>
        <a href="{{ route('products.create') }}"
           class="h-10 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-[0.82rem] font-bold whitespace-nowrap flex items-center gap-2 transition shadow-[0_6px_18px_rgba(245,158,11,0.32)]">
            <span class="material-icons-round text-base">add</span>{{ __('messages.add_product') }}
        </a>
    </div>

    @if($stats['scored'] > 0)
        {{-- How the catalogue splits across the score. One bar says more than
             five counters, and it doubles as a filter. --}}
        <div class="mb-[18px]">
            <div class="flex h-2.5 rounded-full overflow-hidden bg-gray-100 dark:bg-slate-700">
                @foreach($stats['grades'] as $g => $count)
                    @if($count > 0)
                        <a href="{{ route('products.index', ['grade' => $g]) }}"
                           style="width: {{ round($count / $stats['scored'] * 100, 2) }}%;
                                  background-color: {{ ['a'=>'#038141','b'=>'#85bb2f','c'=>'#fecb02','d'=>'#ee8100','e'=>'#e63e11'][$g] }}"
                           title="{{ strtoupper($g) }} · {{ $count }}"></a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('products.index') }}"
          class="flex flex-wrap gap-2.5 mb-[18px]" x-data>
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="{{ __('messages.search_products') }}"
               class="flex-1 min-w-48 bg-white dark:bg-slate-800 dark:text-white border border-gray-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition">

        {{-- Grade filter, using the badge itself as the control. --}}
        <div class="flex items-center gap-1.5">
            <a href="{{ route('products.index', array_filter(['search' => request('search'), 'sort' => request('sort')])) }}"
               class="h-10 px-3 rounded-xl border text-sm font-semibold flex items-center transition
                      {{ request('grade') ? 'border-gray-200 dark:border-slate-700 text-gray-500 dark:text-slate-400' : 'border-amber-500 bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300' }}">
                {{ __('messages.all') }}
            </a>
            @foreach(['a','b','c','d','e'] as $g)
                <a href="{{ route('products.index', array_filter(['search' => request('search'), 'sort' => request('sort'), 'grade' => $g])) }}"
                   class="{{ request('grade') === $g ? 'ring-2 ring-offset-2 ring-gray-400 dark:ring-offset-slate-900 rounded-md' : '' }}">
                    <x-nutri-score :grade="$g" size="md" />
                </a>
            @endforeach
        </div>

        <select name="sort" @change="$el.form.submit()"
                class="bg-white dark:bg-slate-800 dark:text-white border border-gray-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:border-amber-500 transition">
            <option value="">{{ __('messages.sort_by_name') }}</option>
            <option value="bought" {{ request('sort') === 'bought' ? 'selected' : '' }}>{{ __('messages.sort_by_bought') }}</option>
            <option value="recent" {{ request('sort') === 'recent' ? 'selected' : '' }}>{{ __('messages.sort_by_recent') }}</option>
        </select>

        <button type="submit"
                class="h-10 px-4 rounded-xl border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-slate-700 transition">
            {{ __('messages.search') }}
        </button>
    </form>

    @if($products->isEmpty())
        <x-card class="py-14 text-center">
            <div class="w-14 h-14 rounded-2xl bg-gray-50 dark:bg-slate-900 flex items-center justify-center mx-auto mb-3">
                <span class="material-icons-round text-2xl text-gray-300 dark:text-slate-600">shopping_basket</span>
            </div>
            <div class="text-sm font-semibold text-gray-700 dark:text-slate-200">{{ __('messages.no_products') }}</div>
            <div class="text-[0.78rem] text-gray-400 dark:text-slate-500 mt-1 max-w-md mx-auto">{{ __('messages.no_products_hint') }}</div>
        </x-card>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
            @foreach($products as $product)
                <a href="{{ route('products.show', $product) }}"
                   class="group bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-3.5 flex gap-3.5 items-center
                          hover:border-amber-300 dark:hover:border-amber-500/40 transition">
                    <div class="w-14 h-14 rounded-xl overflow-hidden bg-gray-50 dark:bg-slate-900 shrink-0 flex items-center justify-center">
                        @if($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="" class="w-full h-full object-contain">
                        @else
                            <span class="material-icons-round text-xl text-gray-300 dark:text-slate-600">shopping_basket</span>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="text-[0.88rem] font-semibold text-gray-900 dark:text-white truncate">{{ $product->name }}</div>
                        <div class="text-[0.7rem] text-gray-400 dark:text-slate-500 truncate">
                            {{ $product->brand ?: ($product->category ?: __('messages.no_brand')) }}
                        </div>
                        @if($product->purchases_count > 0)
                            <div class="text-[0.68rem] text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5 truncate">
                                {{ $product->purchases_count === 1
                                    ? __('messages.bought_once')
                                    : __('messages.bought_times', ['count' => $product->purchases_count]) }}
                                @if($product->last_purchased_at)
                                    · {{ \Carbon\Carbon::parse($product->last_purchased_at)->diffForHumans(['short' => true]) }}
                                @endif
                            </div>
                        @endif
                    </div>

                    <x-nutri-score :grade="$product->grade()" size="md" class="shrink-0" />
                </a>
            @endforeach
        </div>

        @if($products->hasPages())
            <div class="mt-4">{{ $products->links() }}</div>
        @endif
    @endif
@endsection
