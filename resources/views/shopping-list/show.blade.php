@extends('layouts.app')
@section('title', $list->name)

@section('content')
<div x-data="shoppingListApp()" x-init="init({{ json_encode($list) }})">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('shopping-list.index') }}" class="text-indigo-600 dark:text-indigo-400 text-sm font-semibold mb-2 inline-flex items-center gap-1">
                <span class="material-icons-round text-lg">arrow_back</span>{{ __('messages.back') }}
            </a>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white" x-text="list.name"></h1>
        </div>
        <div class="flex gap-2">
            <button @click="viewMode = 'list'"
                    :class="viewMode === 'list' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 border border-gray-200 dark:border-slate-700'"
                    class="p-2 rounded-xl transition">
                <span class="material-icons-round">list</span>
            </button>
            <button @click="viewMode = 'tiles'"
                    :class="viewMode === 'tiles' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 border border-gray-200 dark:border-slate-700'"
                    class="p-2 rounded-xl transition">
                <span class="material-icons-round">dashboard</span>
            </button>
        </div>
    </div>

    {{-- Barcode Scanner & Add Item --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-5 mb-6">
        <div class="flex gap-3">
            <div class="flex-1">
                <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-2">{{ __('messages.barcode_scan') }}</label>
                <div class="flex gap-2">
                    <input type="text" x-model="barcodeInput" @keyup.enter="scanBarcode()"
                           placeholder="Scan or enter barcode…"
                           class="flex-1 bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500">
                    <button @click="scanBarcode()" type="button"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition">
                        <span class="material-icons-round">qr_code_scanner</span>
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-2">{{ __('messages.add_item') }}</label>
                <button @click="addItemModalOpen = true" type="button"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition">
                    <span class="material-icons-round">add</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Barcode Lookup Result (temporary) --}}
    <template x-if="barcodeResult">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl p-5 mb-6">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-bold text-blue-900 dark:text-blue-300" x-text="barcodeResult.name"></h3>
                    <p class="text-sm text-blue-700 dark:text-blue-400 mt-1" x-text="barcodeResult.brand || 'Unknown brand'"></p>
                </div>
                <button @click="barcodeResult = null" type="button" class="text-blue-500 hover:text-blue-700">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <template x-if="barcodeResult.nutrition">
                <div class="grid grid-cols-3 gap-2 text-xs mb-3">
                    <div class="bg-white dark:bg-slate-800 rounded p-2">
                        <div class="text-blue-600 dark:text-blue-400 font-bold" x-text="barcodeResult.nutrition.calories + ' kcal'"></div>
                        <div class="text-blue-500 text-xs">Calories</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded p-2">
                        <div class="text-blue-600 dark:text-blue-400 font-bold" x-text="barcodeResult.nutrition.protein + 'g'"></div>
                        <div class="text-blue-500 text-xs">Protein</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded p-2">
                        <div class="text-blue-600 dark:text-blue-400 font-bold" x-text="barcodeResult.nutrition.fat + 'g'"></div>
                        <div class="text-blue-500 text-xs">Fat</div>
                    </div>
                </div>
            </template>
            <button @click="addFromBarcode()" type="button"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl py-2 text-sm transition">
                Add to List
            </button>
        </div>
    </template>

    {{-- Items List View --}}
    <template x-if="viewMode === 'list'">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <template x-if="items.length === 0">
                <div class="text-center py-12 text-gray-400 dark:text-slate-500">
                    <span class="material-icons-round text-5xl block mb-2">shopping_bag</span>
                    <p class="text-sm">{{ __('messages.no_items') }}</p>
                </div>
            </template>
            <table x-show="items.length > 0" x-cloak class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-100 dark:border-slate-700">
                    <tr>
                        <th class="px-5 py-3 text-left"><input type="checkbox" @change="toggleAllItems()" :checked="allChecked"></th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Item</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider hidden sm:table-cell">Qty</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-700">
                    <template x-for="item in items" :key="item.id">
                        <tr :class="item.checked ? 'bg-gray-50 dark:bg-slate-700/30' : ''"
                            class="hover:bg-gray-50 dark:hover:bg-slate-700/40 transition">
                            <td class="px-5 py-3">
                                <input type="checkbox" :checked="item.checked" @change="toggleItem(item)" class="rounded">
                            </td>
                            <td class="px-5 py-3">
                                <div class="font-medium text-gray-900 dark:text-white" :class="item.checked ? 'line-through text-gray-400' : ''" x-text="item.name"></div>
                                <template x-if="item.barcode">
                                    <div class="text-xs text-gray-400 dark:text-slate-500" x-text="'Barcode: ' + item.barcode"></div>
                                </template>
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-slate-400 hidden sm:table-cell">
                                <span x-text="item.quantity + ' ' + item.unit"></span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <button @click="editItem(item)" type="button" class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition">
                                    <span class="material-icons-round text-base">edit</span>
                                </button>
                                <button @click="deleteItem(item.id)" type="button" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition ml-1">
                                    <span class="material-icons-round text-base">delete</span>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    {{-- Items Tile View --}}
    <template x-if="viewMode === 'tiles'">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <template x-if="items.length === 0">
                <div class="col-span-3 text-center py-12 text-gray-400 dark:text-slate-500">
                    <span class="material-icons-round text-5xl block mb-2">shopping_bag</span>
                    <p class="text-sm">{{ __('messages.no_items') }}</p>
                </div>
            </template>
            <template x-for="item in items" :key="item.id">
                <div :class="item.checked ? 'bg-gray-50 dark:bg-slate-700/40 opacity-60' : 'bg-white dark:bg-slate-800'"
                     class="rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-4 transition hover:shadow-md">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" :checked="item.checked" @change="toggleItem(item)" class="rounded">
                                <span class="font-semibold text-gray-900 dark:text-white text-sm" :class="item.checked ? 'line-through text-gray-400' : ''" x-text="item.name"></span>
                            </label>
                            <template x-if="item.nutrition">
                                <div class="text-xs text-indigo-600 dark:text-indigo-400 mt-2 font-semibold">Nutrition info available</div>
                            </template>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <button @click="editItem(item)" type="button" class="p-1 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition">
                                <span class="material-icons-round text-sm">edit</span>
                            </button>
                            <button @click="deleteItem(item.id)" type="button" class="p-1 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                                <span class="material-icons-round text-sm">delete</span>
                            </button>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-slate-400 pt-3 border-t border-gray-100 dark:border-slate-700">
                        <span x-text="item.quantity + ' ' + item.unit"></span>
                    </div>
                </div>
            </template>
        </div>
    </template>

    {{-- Add/Edit Item Modal --}}
    <div x-show="addItemModalOpen" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" x-cloak>
        <div class="w-full max-w-sm bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-xl p-6"
             @click.outside="addItemModalOpen = false">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5"
                x-text="editingItem?.id ? 'Edit Item' : 'Add Item'"></h3>
            <form @submit.prevent="saveItem()">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">Pick saved
                            product or enter name</label>
                        <div class="flex gap-2">
                            <select x-model="selectedProductId"
                                    @change="selectProduct(products.find(p => p.id == selectedProductId))"
                                    class="flex-1 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none">
                                <option value="">— Select product —</option>
                                <template x-for="p in products" :key="p.id">
                                    <option :value="p.id" x-text="p.name + (p.brand ? ' ('+p.brand+')' : '')"></option>
                                </template>
                            </select>
                            <a href="{{ route('admin.products.create') }}" target="_blank"
                               class="inline-flex items-center gap-1 px-3 py-2 bg-gray-100 dark:bg-slate-700 rounded-xl text-sm">Create</a>
                        </div>
                        <div class="mt-2">
                            <input type="text" x-model="itemForm.name" required
                                   class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.quantity') }}</label>
                            <input type="number" step="0.1" min="0.1" x-model="itemForm.quantity"
                                   class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.unit') }}</label>
                            <input type="text" x-model="itemForm.unit" placeholder="e.g. kg, piece"
                                   class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500">
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 mt-5">
                    <button type="submit" :disabled="saving"
                            class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white font-semibold rounded-xl py-2.5 text-sm transition">
                        <span x-text="saving ? 'Saving…' : 'Save'"></span>
                    </button>
                    <button type="button" @click="addItemModalOpen = false"
                            class="flex-1 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 font-semibold rounded-xl py-2.5 text-sm transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
    {{-- Shopping list scripts moved to resources/js/pages/shopping-list.js --}}
@endpush
