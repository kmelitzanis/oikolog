@extends('layouts.app')
@section('title', __('messages.shopping_lists'))

@section('content')
<div x-data="shoppingListsApp()" x-init="init()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ __('messages.shopping_lists') }}</h1>
        <button @click="createModalOpen = true"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2 transition">
            <span class="material-icons-round text-lg">add</span>{{ __('messages.new_list') }}
        </button>
    </div>

    {{-- Search & Filter --}}
    <div class="flex gap-3 mb-5">
        <input type="text" x-model="searchQuery" @input.debounce.300="loadLists()"
               placeholder="{{ __('messages.search') }}…"
               class="flex-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-indigo-500 dark:text-white">
    </div>

    {{-- Lists Grid --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <template x-if="loading">
            <div class="col-span-3 text-center py-12">
                <span class="material-icons-round animate-spin text-3xl text-gray-400">refresh</span>
            </div>
        </template>

        <template x-if="!loading && lists.length === 0">
            <div class="col-span-3 text-center py-12 text-gray-400 dark:text-slate-500">
                <span class="material-icons-round text-5xl block mb-2">shopping_cart</span>
                <p class="text-sm">{{ __('messages.no_lists') }}</p>
            </div>
        </template>

        <template x-for="list in lists" :key="list.id">
            <a :href="'/shopping-lists/' + list.id"
               class="group bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-5 hover:shadow-md transition cursor-pointer">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-900 dark:text-white text-base group-hover:text-indigo-600 transition" x-text="list.name"></h3>
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 line-clamp-2" x-text="list.description || '—'"></p>
                    </div>
                    <span x-show="list.is_completed" class="material-icons-round text-sm text-green-500">check_circle</span>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-slate-700">
                    <span class="text-xs font-semibold text-gray-500 dark:text-slate-400"
                          x-text="list.items_count + ' items'"></span>
                    <div class="flex gap-1">
                        <button @click.prevent.stop="editList(list)" class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition">
                            <span class="material-icons-round text-base">edit</span>
                        </button>
                        <button @click.prevent.stop="deleteList(list.id)" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                            <span class="material-icons-round text-base">delete</span>
                        </button>
                    </div>
                </div>
            </a>
        </template>
    </div>

    {{-- Create/Edit Modal --}}
    <div x-show="createModalOpen" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" x-cloak
         x-transition:enter="transition duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="w-full max-w-sm bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-xl p-6"
             @click.outside="createModalOpen = false">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5"
                x-text="editingList?.id ? 'Edit List' : '{{ __('messages.new_list') }}'"></h3>
            <form @submit.prevent="saveList()">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">Name *</label>
                        <input type="text" x-model="form.name" required
                               class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">Description</label>
                        <textarea x-model="form.description" rows="2"
                                  class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 resize-none"></textarea>
                    </div>
                </div>
                <div class="flex gap-3 mt-5">
                    <button type="submit" :disabled="saving"
                            class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white font-semibold rounded-xl py-2.5 text-sm transition">
                        <span x-text="saving ? 'Saving…' : 'Save'"></span>
                    </button>
                    <button type="button" @click="createModalOpen = false"
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
<script>
function shoppingListsApp() {
    return {
        lists: [],
        loading: false,
        saving: false,
        createModalOpen: false,
        searchQuery: '',
        editingList: null,
        form: { name: '', description: '' },

        async init() {
            await this.loadLists();
        },

        async loadLists() {
            this.loading = true;
            const params = new URLSearchParams();
            if (this.searchQuery) params.append('search', this.searchQuery);

            const res = await fetch(`/api/shopping-lists?${params}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            this.lists = data.data ?? [];
            this.loading = false;
        },

        editList(list) {
            this.editingList = list;
            this.form = { name: list.name, description: list.description };
            this.createModalOpen = true;
        },

        async saveList() {
            this.saving = true;
            const method = this.editingList ? 'PUT' : 'POST';
            const url = this.editingList ? `/api/shopping-lists/${this.editingList.id}` : '/api/shopping-lists';

            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.form),
                });

                if (!res.ok) {
                    const error = await res.json();
                    console.error('API Error:', error);
                    alert('Error: ' + (error.message || 'Failed to save list'));
                    this.saving = false;
                    return;
                }

                this.saving = false;
                this.createModalOpen = false;
                this.editingList = null;
                this.form = { name: '', description: '' };
                await this.loadLists();
            } catch (err) {
                console.error('Fetch error:', err);
                alert('Error: ' + err.message);
                this.saving = false;
            }
        },

        async deleteList(id) {
            if (!confirm('{{ __('messages.confirm_delete') }}')) return;

            await fetch(`/api/shopping-lists/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });

            await this.loadLists();
        },
    };
}
</script>
@endpush
