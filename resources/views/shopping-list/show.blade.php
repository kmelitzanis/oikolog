@extends('layouts.app')
@section('title', $list->name)

@section('content')
<div x-data="shoppingListApp()" x-init="init({{ Illuminate\Support\Js::from($list) }})" class="max-w-3xl mx-auto">

    {{-- Back --}}
    <a href="{{ route('shopping-list.index') }}"
       class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 text-sm font-semibold mb-4 hover:gap-2 transition-all">
        <span class="material-icons-round text-lg">arrow_back</span>{{ __('messages.shopping_lists') }}
    </a>

    {{-- Header card with progress ring --}}
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-sm p-5 sm:p-6 mb-5">
        <div class="flex items-center gap-5">
            {{-- Progress ring --}}
            <div class="relative shrink-0 w-[68px] h-[68px]">
                <svg class="w-full h-full -rotate-90" viewBox="0 0 60 60">
                    <circle cx="30" cy="30" r="26" fill="none" stroke="currentColor" stroke-width="6" class="text-gray-100 dark:text-slate-700"></circle>
                    <circle cx="30" cy="30" r="26" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round"
                            class="text-indigo-500 transition-all duration-500" :stroke-dasharray="progressDash"></circle>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-sm font-extrabold text-gray-900 dark:text-white" x-text="progress + '%'"></span>
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-extrabold text-gray-900 dark:text-white truncate" x-text="list.name"></h1>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5"
                   x-text="total === 0 ? '{{ __('messages.no_items') }}' : (progress === 100 ? '{{ __('messages.all_done') }}' : '{{ __('messages.items_done', ['done' => '#DONE#', 'total' => '#TOTAL#']) }}'.replace('#DONE#', checkedCount).replace('#TOTAL#', total))"></p>
            </div>
            <button x-show="checkedCount > 0" @click="clearChecked()" x-cloak
                    class="shrink-0 inline-flex items-center gap-1 text-xs font-semibold text-gray-400 hover:text-red-500 transition">
                <span class="material-icons-round text-base">delete_sweep</span>
                <span class="hidden sm:inline">{{ __('messages.clear_checked') }}</span>
            </button>
        </div>

        {{-- Quick add --}}
        <form @submit.prevent="quickAdd()" class="flex gap-2 mt-5">
            <div class="relative flex-1">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">add_shopping_cart</span>
                <input type="text" x-model="quickName" placeholder="{{ __('messages.add_item') }}…"
                       class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl pl-10 pr-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900/40 transition">
            </div>
            <button type="submit" class="shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl px-4 text-sm transition">
                {{ __('messages.add') }}
            </button>
            <button type="button" @click="openAdd()" title="{{ __('messages.add_item') }}"
                    class="shrink-0 w-11 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-600 dark:text-slate-300 rounded-xl flex items-center justify-center transition">
                <span class="material-icons-round">tune</span>
            </button>
        </form>
    </div>

    {{-- Search + barcode --}}
    <div class="flex gap-2 mb-4">
        <div class="relative flex-1">
            <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
            <input type="text" x-model="search" placeholder="{{ __('messages.search_items') }}"
                   class="w-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl pl-10 pr-4 py-2.5 text-sm outline-none focus:border-indigo-500 dark:text-white transition">
        </div>
        <div class="relative flex gap-2">
            <input type="text" x-model="barcodeInput" @keyup.enter="scanBarcode()" placeholder="{{ __('messages.barcode_scan') }}"
                   class="w-32 sm:w-40 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-indigo-500 dark:text-white transition">
            <button @click="scanBarcode()" type="button"
                    class="shrink-0 w-11 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-500 hover:text-indigo-600 rounded-xl flex items-center justify-center transition">
                <span class="material-icons-round">qr_code_scanner</span>
            </button>
        </div>
    </div>

    {{-- Barcode result --}}
    <template x-if="barcodeResult">
        <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-2xl p-4 mb-4">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h3 class="font-bold text-indigo-900 dark:text-indigo-200" x-text="barcodeResult.name"></h3>
                    <p class="text-sm text-indigo-600 dark:text-indigo-400" x-text="barcodeResult.brand || ''"></p>
                </div>
                <button @click="barcodeResult = null" class="text-indigo-400 hover:text-indigo-600"><span class="material-icons-round">close</span></button>
            </div>
            <button @click="addFromBarcode()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl py-2 text-sm transition">{{ __('messages.add') }}</button>
        </div>
    </template>

    {{-- Loading --}}
    <template x-if="loading">
        <div class="text-center py-12"><span class="material-icons-round animate-spin text-3xl text-gray-300">refresh</span></div>
    </template>

    {{-- Empty --}}
    <template x-if="!loading && total === 0">
        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-sm px-6 py-14 text-center">
            <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                <span class="material-icons-round text-3xl text-indigo-400">shopping_basket</span>
            </div>
            <p class="text-sm text-gray-400 dark:text-slate-500">{{ __('messages.no_items') }}</p>
        </div>
    </template>

    {{-- To buy --}}
    <div x-show="!loading && toBuy.length > 0" x-cloak class="mb-5">
        <h2 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-slate-500 mb-2 px-1">
            {{ __('messages.to_buy') }}
            <span class="px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400 text-[11px]" x-text="toBuy.length"></span>
        </h2>
        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-sm divide-y divide-gray-50 dark:divide-slate-700/60 overflow-hidden">
            <template x-for="item in toBuy" :key="item.id">
                <div class="flex items-center gap-3 px-4 py-3 group hover:bg-gray-50/60 dark:hover:bg-slate-700/30 transition">
                    <button @click="toggleItem(item)"
                            class="shrink-0 w-6 h-6 rounded-full border-2 border-gray-300 dark:border-slate-500 hover:border-indigo-500 flex items-center justify-center transition">
                    </button>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.name"></div>
                    </div>
                    {{-- Quantity stepper --}}
                    <div class="flex items-center gap-1 shrink-0">
                        <button @click="bumpQuantity(item, -1)" class="w-6 h-6 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center justify-center transition">
                            <span class="material-icons-round text-sm">remove</span>
                        </button>
                        <span class="text-xs font-semibold text-gray-600 dark:text-slate-300 min-w-[3.5rem] text-center" x-text="(+item.quantity) + ' ' + item.unit"></span>
                        <button @click="bumpQuantity(item, 1)" class="w-6 h-6 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center justify-center transition">
                            <span class="material-icons-round text-sm">add</span>
                        </button>
                    </div>
                    <div class="flex items-center shrink-0 opacity-0 group-hover:opacity-100 transition">
                        <button @click="editItem(item)" class="w-8 h-8 rounded-lg text-gray-400 hover:text-indigo-600 flex items-center justify-center transition">
                            <span class="material-icons-round text-base">edit</span>
                        </button>
                        <button @click="deleteItem(item.id)" class="w-8 h-8 rounded-lg text-gray-400 hover:text-red-500 flex items-center justify-center transition">
                            <span class="material-icons-round text-base">delete</span>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- In cart --}}
    <div x-show="!loading && inCart.length > 0" x-cloak>
        <h2 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-slate-500 mb-2 px-1">
            {{ __('messages.in_cart') }}
            <span class="px-1.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 text-[11px]" x-text="inCart.length"></span>
        </h2>
        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-sm divide-y divide-gray-50 dark:divide-slate-700/60 overflow-hidden">
            <template x-for="item in inCart" :key="item.id">
                <div class="flex items-center gap-3 px-4 py-3 group">
                    <button @click="toggleItem(item)"
                            class="shrink-0 w-6 h-6 rounded-full bg-emerald-500 border-2 border-emerald-500 flex items-center justify-center transition">
                        <span class="material-icons-round text-white text-sm">check</span>
                    </button>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-400 dark:text-slate-500 line-through truncate" x-text="item.name"></div>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-slate-600 shrink-0" x-text="(+item.quantity) + ' ' + item.unit"></span>
                    <button @click="deleteItem(item.id)" class="w-8 h-8 shrink-0 rounded-lg text-gray-300 hover:text-red-500 flex items-center justify-center transition opacity-0 group-hover:opacity-100">
                        <span class="material-icons-round text-base">delete</span>
                    </button>
                </div>
            </template>
        </div>
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
                           class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.quantity') }}</label>
                        <input type="number" step="0.1" min="0.1" x-model="itemForm.quantity"
                               class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.unit') }}</label>
                        <input type="text" x-model="itemForm.unit" placeholder="kg, piece…"
                               class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" :disabled="saving" class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white font-semibold rounded-xl py-2.5 text-sm transition">
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
