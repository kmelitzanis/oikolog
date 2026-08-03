@extends('layouts.app')
@section('title', $product->name)

{{--
    One product: what it is, what is in it, and what it has cost you in trips
    to the shop. The scores sit next to the image because they are the reason
    most people open this page.
--}}

@section('content')
    @php
        $grade = $product->grade();
        $eco   = strtolower((string) $product->eco_score);
        $facts = $product->nutritionFacts();
        $units = [
            'calories' => 'kcal', 'protein' => 'g', 'carbs' => 'g', 'sugar' => 'g',
            'fat' => 'g', 'saturated_fat' => 'g', 'fiber' => 'g', 'salt' => 'g', 'sodium' => 'g',
        ];
    @endphp

    <div class="max-w-4xl">

        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('products.index') }}"
               class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white truncate">{{ $product->name }}</h1>
                @if($product->brand)
                    <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5 truncate">{{ $product->brand }}</p>
                @endif
            </div>
            @if($product->isRefreshable())
                <form method="POST" action="{{ route('products.refresh', $product) }}">
                    @csrf
                    <x-icon-btn tone="neutral" icon="sync" type="submit" title="{{ __('messages.refresh_from_barcode') }}" />
                </form>
            @endif
            <x-icon-btn tone="neutral" icon="edit" :href="route('products.edit', $product)" title="{{ __('messages.edit') }}" />
            <form method="POST" action="{{ route('products.destroy', $product) }}"
                  onsubmit="return confirm('{{ addslashes(__('messages.confirm_delete')) }}')">
                @csrf @method('DELETE')
                <x-icon-btn tone="danger" icon="delete" type="submit" title="{{ __('messages.delete') }}" />
            </form>
        </div>

        {{-- ── Identity ───────────────────────────────────────────────────── --}}
        <x-card class="mb-4">
            <div class="flex flex-col sm:flex-row gap-5">
                <div class="w-full sm:w-40 h-40 shrink-0 rounded-2xl overflow-hidden bg-gray-50 dark:bg-slate-900 flex items-center justify-center">
                    @if($product->imageUrl())
                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-contain">
                    @else
                        <span class="material-icons-round text-4xl text-gray-300 dark:text-slate-600">shopping_basket</span>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-4 mb-4">
                        <div>
                            <div class="text-[0.66rem] font-semibold uppercase tracking-[0.08em] text-gray-400 dark:text-slate-500 mb-1">
                                {{ __('messages.nutri_score') }}
                            </div>
                            <x-nutri-score :grade="$grade" size="lg" :label="true" />
                        </div>

                        @if(in_array($eco, ['a','b','c','d','e'], true))
                            <div>
                                <div class="text-[0.66rem] font-semibold uppercase tracking-[0.08em] text-gray-400 dark:text-slate-500 mb-1">
                                    {{ __('messages.eco_score') }}
                                </div>
                                <span class="w-12 h-12 rounded-xl bg-emerald-600 text-white inline-flex items-center justify-center text-xl font-extrabold uppercase">
                                    {{ strtoupper($eco) }}
                                </span>
                            </div>
                        @endif

                        @if($product->nova_group)
                            <div class="min-w-0">
                                <div class="text-[0.66rem] font-semibold uppercase tracking-[0.08em] text-gray-400 dark:text-slate-500 mb-1">
                                    {{ __('messages.nova_group') }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-12 h-12 rounded-xl bg-slate-700 text-white inline-flex items-center justify-center text-xl font-extrabold">
                                        {{ $product->nova_group }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-slate-400 max-w-40">
                                        {{ __('messages.nova_' . $product->nova_group) }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        @foreach([
                            'barcode' => $product->barcode,
                            'category' => $product->category,
                            'net_quantity' => $product->net_quantity,
                            'serving_size' => $product->serving_size,
                        ] as $key => $value)
                            @if($value)
                                <div class="min-w-0">
                                    <dt class="text-[0.7rem] text-gray-400 dark:text-slate-500">{{ __('messages.' . $key) }}</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white truncate">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>

                    @if($product->last_synced_at)
                        <div class="text-[0.7rem] text-gray-400 dark:text-slate-500 mt-3 flex items-center gap-1">
                            <span class="material-icons-round" style="font-size:13px;">cloud_done</span>
                            {{ __('messages.synced_at', ['date' => $product->last_synced_at->translatedFormat('j M Y')]) }}
                            @if($product->is_edited) · {{ __('messages.manually_edited') }} @endif
                        </div>
                    @endif
                </div>
            </div>

            @if($product->description)
                <p class="text-sm text-gray-600 dark:text-slate-300 mt-5 whitespace-pre-line">{{ $product->description }}</p>
            @endif
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            {{-- ── Nutrition ──────────────────────────────────────────────── --}}
            <x-card>
                <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-1">{{ __('messages.nutrition_facts') }}</h2>
                <p class="text-xs text-gray-400 dark:text-slate-500 mb-3">{{ __('messages.per_100g') }}</p>

                @if($facts)
                    <div class="divide-y divide-gray-50 dark:divide-slate-700">
                        @foreach($facts as $key => $value)
                            <div class="flex items-center justify-between py-2 text-sm">
                                <span class="text-gray-500 dark:text-slate-400">{{ __('messages.nutrient_' . $key) }}</span>
                                <span class="font-semibold text-gray-900 dark:text-white tabular-nums">
                                    {{ rtrim(rtrim(number_format($value, 1), '0'), '.') }} {{ $units[$key] ?? '' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-8 text-center text-sm text-gray-400 dark:text-slate-500">
                        {{ __('messages.no_nutrition_yet') }}
                        <div class="mt-3">
                            <x-btn variant="ghost" size="sm" :href="route('products.edit', $product)" icon="edit">
                                {{ __('messages.add_manually') }}
                            </x-btn>
                        </div>
                    </div>
                @endif

                @if($product->ingredients_text)
                    <div class="mt-4 pt-4 border-t border-gray-50 dark:border-slate-700">
                        <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-gray-400 dark:text-slate-500 mb-1">
                            {{ __('messages.product_ingredients') }}
                        </div>
                        <p class="text-xs text-gray-600 dark:text-slate-300 leading-relaxed">{{ $product->ingredients_text }}</p>
                    </div>
                @endif

                @if(!empty($product->allergens))
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach($product->allergens as $allergen)
                            <span class="px-2 py-0.5 rounded-full bg-red-500/10 text-red-600 dark:text-red-400 text-[0.68rem] font-semibold">
                                {{ ucfirst($allergen) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- ── Purchase history ───────────────────────────────────────── --}}
            <x-card>
                <div class="flex items-baseline justify-between gap-3 mb-3">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ __('messages.purchase_history') }}</h2>
                    <span class="text-xs text-gray-400 dark:text-slate-500">
                        {{ $product->purchases_count === 1
                            ? __('messages.bought_once')
                            : __('messages.bought_times', ['count' => $product->purchases_count]) }}
                    </span>
                </div>

                {{-- Once there are enough buys to see a habit, say what it is:
                     this is the difference between a log and something useful. --}}
                @if($rhythm['every'])
                    <div class="mb-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-3.5 py-2.5 flex items-center gap-2.5">
                        <span class="material-icons-round text-emerald-600 dark:text-emerald-400 text-lg shrink-0">update</span>
                        <div class="min-w-0 text-[0.76rem] text-emerald-800 dark:text-emerald-300">
                            {{ __('messages.bought_every', ['days' => $rhythm['every']]) }}
                            @if($rhythm['next'])
                                <span class="block text-[0.7rem] opacity-80">
                                    {{ __('messages.expected_again', ['when' => $rhythm['next']->diffForHumans()]) }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endif

                @if($openItems->isNotEmpty())
                    <div class="mb-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 px-3.5 py-2.5">
                        <div class="text-[0.7rem] font-semibold text-amber-700 dark:text-amber-400 mb-1">{{ __('messages.on_a_list_now') }}</div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($openItems as $item)
                                <a href="{{ route('shopping-list.show', $item->shopping_list_id) }}"
                                   class="text-[0.72rem] font-medium text-amber-800 dark:text-amber-300 underline">
                                    {{ $item->shoppingList?->name ?? __('messages.shopping_lists') }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @forelse($purchases as $purchase)
                    <div class="flex items-center gap-3 py-2.5 {{ !$loop->first ? 'border-t border-gray-50 dark:border-slate-700' : '' }}">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/[0.14] text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <span class="material-icons-round text-base">shopping_cart_checkout</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ rtrim(rtrim(number_format((float) $purchase->quantity, 2), '0'), '.') }}
                                {{ \App\Support\Units::label($purchase->unit) }}
                            </div>
                            <div class="text-[0.7rem] text-gray-400 dark:text-slate-500 truncate">
                                {{ $purchase->purchased_at->translatedFormat('j M Y') }}
                                @if($purchase->buyer) · {{ $purchase->buyer->name }} @endif
                            </div>
                        </div>
                        @if($purchase->price)
                            <div class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                                {{ number_format((float) $purchase->price, 2) }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-8 text-center text-sm text-gray-400 dark:text-slate-500">
                        <span class="material-icons-round text-3xl block mb-1 text-gray-300 dark:text-slate-600">shopping_cart</span>
                        {{ __('messages.never_bought') }}
                    </div>
                @endforelse
            </x-card>
        </div>
    </div>
@endsection
