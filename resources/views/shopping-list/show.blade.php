@extends('layouts.app')
@section('title', $list->name)

{{--
    Shopping list — a build of the `atShopping` panel in mockup 3a: a 260px
    list picker beside the open list.

    The mockup shows one flat item list with checked rows struck through, rather
    than the separate "to buy" / "in cart" sections this page used to have, so
    the two collections are rendered as a single sequence.

    Search, barcode scanning and the detailed add/edit dialog have no equivalent
    in the mockup but are real features; they sit in the list header.
--}}

@section('content')
    {{-- Unit labels for the Alpine component: it must render translated
         text without hardcoding any. --}}
    <script>window.__unitLabels = @json(\App\Support\Units::options());</script>

<div x-data="shoppingListApp()" x-init="init({{ Illuminate\Support\Js::from($list) }})"
     class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-[18px] items-start">

    {{-- ── List picker ────────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-[24px] p-3">
        @foreach($lists as $l)
            @php
                $isActive = $l->id === $list->id;
                $pending = $l->pending_items_count;
            @endphp
            <a href="{{ route('shopping-list.show', $l) }}"
               class="flex items-center gap-3 p-3 rounded-2xl transition
                      {{ $isActive ? 'bg-amber-500/[0.16]' : 'hover:bg-gray-50 dark:hover:bg-slate-700/50' }}">
                <span class="w-[34px] h-[34px] rounded-xl shrink-0 flex items-center justify-center
                             {{ $isActive
                                 ? 'bg-orange-500/[0.18] text-orange-500'
                                 : 'bg-amber-500/[0.14] text-amber-600 dark:text-amber-400' }}">
                    <span class="material-icons-round text-lg">shopping_cart</span>
                </span>
                <span class="flex-1 min-w-0">
                    <span class="block text-[0.84rem] font-semibold text-gray-900 dark:text-white truncate">{{ $l->name }}</span>
                    <span class="block text-[0.68rem] mt-px {{ $isActive ? 'text-amber-600 dark:text-amber-300' : 'text-gray-400 dark:text-slate-500' }}">
                        {{ $pending > 0
                            ? __('messages.pending_items', ['count' => $pending])
                            : __('messages.nothing_to_buy') }}
                    </span>
                </span>
            </a>
        @endforeach
        <a href="{{ route('shopping-list.index') }}"
           class="w-full mt-1.5 h-[42px] rounded-2xl border border-dashed border-gray-200 dark:border-slate-700 text-amber-600 dark:text-amber-400 text-[0.82rem] font-semibold flex items-center justify-center transition hover:bg-amber-50 dark:hover:bg-amber-500/10">
            + {{ __('messages.new_list') }}
        </a>

    </div>

    {{-- ── Open list ──────────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-[24px] overflow-hidden">

        {{-- Header --}}
        <div class="px-5 pt-[18px] pb-3.5">
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-[1.05rem] font-bold text-gray-900 dark:text-white truncate" x-text="list.name"></div>
                    {{-- What is left to buy says more than when the list was
                         last touched, on a list that is never really "done". --}}
                    <div class="text-[0.74rem] text-gray-400 dark:text-slate-500 mt-0.5">
                        <span x-show="roundPending > 0"
                              x-text="roundPending + ' {{ __('messages.left_to_buy') }}'"></span>
                        <span x-show="roundPending === 0" x-cloak>{{ __('messages.nothing_to_buy') }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    {{-- Both the figure and the bar belong to the round in
                         progress; with nothing pending there is nothing to
                         report, so they step aside rather than sit at 100%. --}}
                    <div x-show="hasRound" x-cloak
                         class="text-[1.3rem] font-extrabold text-amber-500 dark:text-amber-300" x-text="progress + '%'"></div>
                </div>
            </div>
            <div x-show="hasRound" x-cloak class="h-2 rounded-full bg-gray-100 dark:bg-slate-900 mt-3 overflow-hidden">
                <div class="h-full rounded-full transition-[width] duration-[350ms] ease-out"
                     :class="progress === 100 ? 'bg-emerald-500' : 'bg-linear-to-r from-amber-500 to-amber-700'"
                     :style="`width: ${progress}%`"></div>
            </div>

            {{-- Search + barcode — not in the mockup, kept as real features --}}
            <div class="flex gap-2 mt-3.5">
                <div class="relative flex-1">
                    <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">search</span>
                    <input type="text" x-model="search" placeholder="{{ __('messages.search_items') }}"
                           class="w-full bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl pl-9 pr-3 py-2 text-sm outline-none focus:border-amber-500 dark:text-white transition">
                </div>
                <input type="text" x-model="barcodeInput" @keyup.enter="scanBarcode()" placeholder="{{ __('messages.barcode_scan') }}"
                       class="w-28 sm:w-36 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm outline-none focus:border-amber-500 dark:text-white transition">
                <button @click="scanBarcode()" type="button" title="{{ __('messages.barcode_scan') }}"
                        class="shrink-0 w-10 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 text-gray-500 hover:text-amber-600 rounded-xl flex items-center justify-center transition">
                    <span class="material-icons-round text-lg">qr_code_scanner</span>
                </button>
            </div>
        </div>

        {{-- Barcode result --}}
        <template x-if="barcodeResult">
            <div class="mx-5 mb-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/40 rounded-2xl p-4">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <h3 class="font-bold text-amber-800 dark:text-amber-200" x-text="barcodeResult.name"></h3>
                        <p class="text-sm text-amber-700 dark:text-amber-400" x-text="barcodeResult.brand || ''"></p>
                    </div>
                    <button @click="barcodeResult = null" class="text-amber-400 hover:text-amber-700"><span class="material-icons-round">close</span></button>
                </div>
                <button @click="addFromBarcode()" class="w-full bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold rounded-xl py-2 text-sm transition">{{ __('messages.add') }}</button>
            </div>
        </template>

        {{-- Loading --}}
        <template x-if="loading">
            <div class="text-center py-12 border-t border-gray-100 dark:border-slate-700/60">
                <span class="material-icons-round animate-spin text-3xl text-gray-300">refresh</span>
            </div>
        </template>

        {{-- Empty --}}
        <template x-if="!loading && total === 0">
            <div class="px-6 py-14 text-center border-t border-gray-100 dark:border-slate-700/60">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-amber-50 dark:bg-amber-500/15 flex items-center justify-center">
                    <span class="material-icons-round text-3xl text-amber-500">shopping_basket</span>
                </div>
                <p class="text-sm text-gray-400 dark:text-slate-500">{{ __('messages.no_items') }}</p>
            </div>
        </template>

        {{-- Items — one flat sequence, unchecked first, as in the mockup --}}
        <template x-for="item in [...toBuy, ...inCart]" :key="item.id">
            <div class="flex flex-wrap sm:flex-nowrap items-center gap-x-3.5 gap-y-2 px-5 py-[13px] border-t border-gray-100 dark:border-slate-700/60 group hover:bg-gray-50/60 dark:hover:bg-slate-700/30 transition">
                <button @click="toggleItem(item)"
                        class="shrink-0 w-6 h-6 rounded-lg border-2 flex items-center justify-center transition"
                        :class="item.checked
                            ? 'bg-emerald-500 border-emerald-500 text-white'
                            : 'border-gray-300 dark:border-slate-500 hover:border-amber-500'">
                    <span class="material-icons-round text-sm" x-show="item.checked">check</span>
                </button>
                {{-- Nutri-Score of the product behind this line. Muted "—" when
                     there is no product or no grade, so rows stay aligned. --}}
                <button type="button" @click="openProduct(item)"
                        class="shrink-0 w-6 h-6 rounded-md inline-flex items-center justify-center text-[0.72rem] font-extrabold uppercase transition hover:scale-110"
                        :class="gradeClass(item)"
                        :title="'{{ __('messages.nutri_score') }}'"
                        x-text="gradeLabel(item)"></button>

                <div class="flex-1 min-w-0">
                    <button type="button" @click="openProduct(item)"
                            class="block w-full text-left text-[0.88rem] font-semibold truncate hover:underline"
                            :class="item.checked
                                ? 'text-gray-400 dark:text-slate-500 line-through'
                                : 'text-gray-900 dark:text-white'"
                            x-text="item.name"></button>
                    <div class="text-[0.68rem] text-gray-400 dark:text-slate-500 truncate"
                         x-show="item.product && item.product.brand" x-cloak
                         x-text="item.product && item.product.brand"></div>
                </div>
                {{-- Stepper and actions share a second line on phones. --}}
                <div class="w-full flex items-center justify-between gap-2 pl-[3.25rem] sm:pl-0 sm:w-auto sm:contents">

                {{-- Quantity stepper --}}
                <div class="flex items-center gap-2 shrink-0">
                    <button @click="bumpQuantity(item, -1)"
                            class="w-6 h-6 rounded-lg bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 text-gray-500 dark:text-slate-400 hover:text-amber-600 flex items-center justify-center font-bold text-sm transition">−</button>
                    <span class="min-w-[56px] sm:min-w-[74px] text-center text-[0.76rem] font-semibold text-gray-600 dark:text-slate-300"
                          x-text="(+item.quantity) + ' ' + unitLabel(item.unit)"></span>
                    <button @click="bumpQuantity(item, 1)"
                            class="w-6 h-6 rounded-lg bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 text-gray-500 dark:text-slate-400 hover:text-amber-600 flex items-center justify-center font-bold text-sm transition">+</button>
                </div>
                <div class="flex items-center shrink-0 sm:opacity-0 sm:group-hover:opacity-100 transition">
                    <button @click="editItem(item)" class="w-8 h-8 rounded-lg text-gray-400 hover:text-amber-600 flex items-center justify-center transition">
                        <span class="material-icons-round text-base">edit</span>
                    </button>
                    <button @click="deleteItem(item.id)" class="w-8 h-8 rounded-lg text-gray-400 hover:text-red-500 flex items-center justify-center transition">
                        <span class="material-icons-round text-base">delete</span>
                    </button>
                </div>
                </div>
            </div>
        </template>

        {{-- Add row, with type-ahead over the product catalogue --}}
        <form @submit.prevent="quickAdd()" @click.outside="dismissSuggestions()"
              class="relative flex items-center gap-2.5 px-5 py-3.5 border-t border-gray-100 dark:border-slate-700/60">
            <input type="text" x-model="quickName" placeholder="{{ __('messages.add_product_ph') }}"
                   @input="suggest()"
                   @keydown.arrow-down.prevent="moveSuggestion(1)"
                   @keydown.arrow-up.prevent="moveSuggestion(-1)"
                   @keydown.escape="dismissSuggestions()"
                   autocomplete="off"
                   class="flex-1 min-w-0 bg-transparent border-0 outline-none p-0 text-[0.8rem] text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-600">

            {{-- Suggestions from what has been bought before. Above the row, so
                 it doesn't cover the list while you read it. --}}
            <div x-show="suggestions.length" x-cloak x-transition.opacity
                 class="absolute left-4 right-4 bottom-full mb-2 z-20 bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-600 rounded-2xl shadow-[0_12px_40px_rgba(2,6,23,0.25)] overflow-hidden">
                <template x-for="(p, i) in suggestions" :key="p.id">
                    <button type="button" @click="addSuggestion(p)" @mouseenter="suggestionIndex = i"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 text-left transition"
                            :class="suggestionIndex === i ? 'bg-amber-50 dark:bg-amber-500/10' : ''">
                        <span class="w-8 h-8 rounded-lg overflow-hidden bg-gray-50 dark:bg-slate-900 shrink-0 flex items-center justify-center">
                            <template x-if="p.image">
                                <img :src="p.image" alt="" class="w-full h-full object-contain">
                            </template>
                            <template x-if="!p.image">
                                <span class="material-icons-round text-base text-gray-300 dark:text-slate-600">shopping_basket</span>
                            </template>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[0.82rem] font-semibold text-gray-900 dark:text-white truncate" x-text="p.name"></span>
                            <span class="block text-[0.68rem] text-gray-400 dark:text-slate-500 truncate">
                                <span x-text="p.brand || ''"></span>
                                <template x-if="p.purchases_count > 0">
                                    <span x-text="(p.brand ? ' · ' : '') + (p.purchases_count === 1
                                        ? '{{ __('messages.bought_once') }}'
                                        : '{{ __('messages.bought_times', ['count' => ':n']) }}'.replace(':n', p.purchases_count))"></span>
                                </template>
                            </span>
                        </span>
                        <span class="shrink-0 w-6 h-6 rounded-md inline-flex items-center justify-center text-[0.72rem] font-extrabold uppercase"
                              :class="gradeClass({ product: p })"
                              x-text="gradeLabel({ product: p })"></span>
                    </button>
                </template>
            </div>
            <button type="button" @click="openAdd()" title="{{ __('messages.add_item') }}"
                    class="shrink-0 w-8 h-8 rounded-xl bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-600 flex items-center justify-center transition">
                <span class="material-icons-round text-base">tune</span>
            </button>
            <button type="submit"
                    class="shrink-0 h-[34px] px-3.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-[0.78rem] font-semibold transition">
                {{ __('messages.add') }}
            </button>
        </form>
    </div>

    {{-- Add/Edit item modal --}}
    <div x-show="addItemModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
        <div class="absolute inset-0 bg-black/40" @click="addItemModalOpen = false"></div>
        <div class="relative w-full max-w-sm bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-2xl p-6"
             x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5" x-text="editingItem ? '{{ __('messages.edit') }}' : '{{ __('messages.add_item') }}'"></h3>
            <form @submit.prevent="saveItem()" class="space-y-3">
                <div x-show="products.length">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.products') ?? 'Product' }}</label>
                    <select x-model="selectedProductId" @change="selectProduct(products.find(p => p.id == selectedProductId))"
                            class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none">
                        <option value="">—</option>
                        <template x-for="p in products" :key="p.id">
                            <option :value="p.id" x-text="p.name + (p.brand ? ' ('+p.brand+')' : '')"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.add_item') }} *</label>
                    <input type="text" x-model="itemForm.name" required
                           class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.quantity') }}</label>
                        <input type="number" step="0.1" min="0.1" x-model="itemForm.quantity"
                               class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.unit') }}</label>
                        {{-- Canonical keys as values, translated labels as text. --}}
                        <select x-model="itemForm.unit"
                                class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500">
                            @foreach(\App\Support\Units::options() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" :disabled="saving" class="flex-1 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-slate-900 font-semibold rounded-xl py-2.5 text-sm transition">
                        <span x-text="saving ? '…' : '{{ __('messages.save') }}'"></span>
                    </button>
                    <button type="button" @click="addItemModalOpen = false" class="px-5 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 font-semibold rounded-xl py-2.5 text-sm">{{ __('messages.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-5 py-3 rounded-2xl shadow-xl text-white text-sm font-medium"
         :class="toast?.isError ? 'bg-red-500' : 'bg-gray-900 dark:bg-slate-700'"
         x-text="toast?.msg"></div>
</div>
@endsection
