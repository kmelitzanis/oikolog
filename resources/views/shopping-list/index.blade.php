@extends('layouts.app')
@section('title', __('messages.shopping_lists'))

@section('content')
<div x-data="shoppingListsApp()" x-init="init()" class="max-w-5xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ __('messages.shopping_lists') }}</h1>
        <button @click="openCreate()"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 shadow-sm transition">
            <span class="material-icons-round text-lg">add</span><span class="hidden sm:inline">{{ __('messages.new_list') }}</span>
        </button>
    </div>

    {{-- Search --}}
    <div class="relative mb-5">
        <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
        <input type="text" x-model="searchQuery" @input.debounce.300="loadLists()"
               placeholder="{{ __('messages.search') }}…"
               class="w-full sm:w-72 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl pl-10 pr-4 py-2.5 text-sm outline-none focus:border-indigo-500 dark:text-white transition">
    </div>

    {{-- Loading --}}
    <template x-if="loading">
        <div class="text-center py-16"><span class="material-icons-round animate-spin text-3xl text-gray-300">refresh</span></div>
    </template>

    {{-- Empty --}}
    <template x-if="!loading && lists.length === 0">
        <x-card flush class="px-6 py-16 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-linear-to-br from-indigo-500 to-purple-500 flex items-center justify-center">
                <span class="material-icons-round text-white text-3xl">shopping_cart</span>
            </div>
            <p class="text-sm text-gray-400 dark:text-slate-500">{{ __('messages.no_lists') }}</p>
            <button @click="openCreate()" class="inline-flex items-center gap-2 mt-5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                <span class="material-icons-round text-lg">add</span>{{ __('messages.new_list') }}
            </button>
        </x-card>
    </template>

    {{-- Grid --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <template x-for="list in lists" :key="list.id">
            <x-card flush class="group relative hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
                <a :href="'/shopping-lists/' + list.id" class="block p-5">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition truncate" x-text="list.name"></h3>
                            <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5 line-clamp-1" x-text="list.description || ''"></p>
                        </div>
                        <div class="w-10 h-10 shrink-0 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                            <span class="material-icons-round text-indigo-500">shopping_basket</span>
                        </div>
                    </div>

                    {{-- Progress --}}
                    <div class="mb-1.5 flex items-center justify-between text-xs">
                        <span class="font-semibold text-gray-500 dark:text-slate-400"
                              x-text="progressLabel(list)"></span>
                        <span class="font-bold" :class="pct(list) === 100 ? 'text-emerald-500' : 'text-indigo-500'" x-text="pct(list) + '%'"></span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500"
                             :class="pct(list) === 100 ? 'bg-emerald-500' : 'bg-linear-to-r from-indigo-500 to-purple-500'"
                             :style="`width: ${pct(list)}%`"></div>
                    </div>
                </a>

                {{-- Actions --}}
                <div class="absolute top-3 right-3 flex gap-1 opacity-0 group-hover:opacity-100 transition">
                    <button @click.prevent.stop="openEdit(list)" class="w-8 h-8 rounded-lg bg-white/90 dark:bg-slate-900/70 backdrop-blur text-gray-400 hover:text-indigo-600 flex items-center justify-center shadow-sm transition">
                        <span class="material-icons-round text-base">edit</span>
                    </button>
                    <button @click.prevent.stop="deleteList(list.id)" class="w-8 h-8 rounded-lg bg-white/90 dark:bg-slate-900/70 backdrop-blur text-gray-400 hover:text-red-500 flex items-center justify-center shadow-sm transition">
                        <span class="material-icons-round text-base">delete</span>
                    </button>
                </div>
            </x-card>
        </template>
    </div>

    {{-- Create/Edit modal --}}
    <div x-show="createModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
        <div class="absolute inset-0 bg-black/40" @click="createModalOpen = false"></div>
        <div class="relative w-full max-w-sm bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-2xl p-6"
             x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5" x-text="editingList?.id ? '{{ __('messages.edit') }}' : '{{ __('messages.new_list') }}'"></h3>
            <form @submit.prevent="saveList()" class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.new_list') }} *</label>
                    <input type="text" x-model="form.name" required placeholder="{{ __('messages.new_list') }}"
                           class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.description') }}</label>
                    <textarea x-model="form.description" rows="2"
                              class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 resize-none"></textarea>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" :disabled="saving" class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white font-semibold rounded-xl py-2.5 text-sm transition">
                        <span x-text="saving ? '…' : '{{ __('messages.save') }}'"></span>
                    </button>
                    <button type="button" @click="createModalOpen = false" class="px-5 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 font-semibold rounded-xl py-2.5 text-sm">{{ __('messages.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
