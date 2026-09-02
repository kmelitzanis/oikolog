// Shopping lists index Alpine component
// Labels come from the Blade side so the copy stays in messages.*.
window.shoppingListsApp = function (labels = {}) {
    return {
        pendingLabel: labels.pending ?? '',
        nothingLabel: labels.nothing ?? '',
        emptyLabel:   labels.empty ?? '',

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
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            this.lists = data.data ?? [];
            this.loading = false;
        },

        /**
         * How much is still to buy. A list is never "done" — the same items
         * come back every week — so the card states what is pending rather
         * than a completion percentage.
         */
        itemsLabel(list) {
            const total = list.items_count || 0;
            const pending = total - (list.checked_items_count || 0);

            if (!total) return this.emptyLabel;
            return pending > 0 ? `${pending} ${this.pendingLabel}` : this.nothingLabel;
        },

        openCreate() {
            this.editingList = null;
            this.form = { name: '', description: '' };
            this.createModalOpen = true;
        },
        openEdit(list) {
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
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify(this.form),
                });

                if (!res.ok) {
                    const error = await res.json();
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
                alert('Error: ' + err.message);
                this.saving = false;
            }
        },

        async deleteList(id) {
            if (!confirm('Are you sure?')) return;
            await fetch(`/api/shopping-lists/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
            await this.loadLists();
        },
    };
};
