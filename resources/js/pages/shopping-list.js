// Shopping list (single) Alpine component
window.shoppingListApp = function () {
    return {
        list: {},
        items: [],
        loading: false,
        saving: false,
        search: '',
        addItemModalOpen: false,
        editingItem: null,
        itemForm: { name: '', quantity: 1, unit: 'piece' },
        products: [],
        selectedProductId: null,
        barcodeInput: '',
        barcodeResult: null,
        barcodeLoading: false,
        quickName: '',
        toast: null,

        init(listData) {
            this.list = listData;
            this.loadItems();
            this.loadProducts();
        },

        async loadItems() {
            this.loading = true;
            const res = await fetch(`/api/shopping-lists/${this.list.id}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            this.items = data.items ?? [];
            this.loading = false;
        },

        // ── Derived ────────────────────────────────────────────────────────
        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.items;
            return this.items.filter(i => i.name.toLowerCase().includes(q));
        },
        get toBuy() { return this.filtered.filter(i => !i.checked); },
        get inCart() { return this.filtered.filter(i => i.checked); },
        get total() { return this.items.length; },
        get checkedCount() { return this.items.filter(i => i.checked).length; },
        get progress() { return this.total ? Math.round(this.checkedCount / this.total * 100) : 0; },
        get progressDash() {
            const c = 2 * Math.PI * 26; // r=26
            return `${c * this.progress / 100} ${c}`;
        },

        // ── Item mutations (optimistic) ────────────────────────────────────
        async toggleItem(item) {
            item.checked = !item.checked; // optimistic
            await fetch(`/api/shopping-lists/${this.list.id}/items/${item.id}/toggle`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
        },

        openAdd() {
            this.editingItem = null;
            this.selectedProductId = null;
            this.itemForm = { name: '', quantity: 1, unit: 'piece' };
            this.addItemModalOpen = true;
        },
        editItem(item) {
            this.editingItem = item;
            this.itemForm = { name: item.name, quantity: parseFloat(item.quantity), unit: item.unit };
            this.addItemModalOpen = true;
        },

        // Quick add straight from the header input
        async quickAdd() {
            const name = this.quickName.trim();
            if (!name) return;
            this.quickName = '';
            await this.postItem({ name, quantity: 1, unit: 'piece' });
            await this.loadItems();
        },

        async loadProducts() {
            try {
                const res = await fetch('/api/products?per_page=200', { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                this.products = data.data || data;
            } catch (e) { /* ignore */ }
        },
        selectProduct(p) {
            if (!p) return;
            this.itemForm.name = p.name;
            this.itemForm.unit = p.unit || 'piece';
            this.itemForm.quantity = p.default_quantity || 1;
            this.itemForm.barcode = p.barcode || null;
        },

        async postItem(payload) {
            return fetch(`/api/shopping-lists/${this.list.id}/items`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(payload),
            });
        },

        async saveItem() {
            this.saving = true;
            const method = this.editingItem ? 'PUT' : 'POST';
            const url = this.editingItem
                ? `/api/shopping-lists/${this.list.id}/items/${this.editingItem.id}`
                : `/api/shopping-lists/${this.list.id}/items`;
            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify(this.itemForm),
                });
                if (!res.ok) { this.flash('Failed to save item', true); this.saving = false; return; }
                this.addItemModalOpen = false;
                this.editingItem = null;
                this.itemForm = { name: '', quantity: 1, unit: 'piece' };
                await this.loadItems();
            } catch (err) {
                this.flash(err.message, true);
            }
            this.saving = false;
        },

        // Adjust quantity inline
        async bumpQuantity(item, delta) {
            const q = Math.max(0.1, Math.round((parseFloat(item.quantity) + delta) * 100) / 100);
            item.quantity = q; // optimistic
            await fetch(`/api/shopping-lists/${this.list.id}/items/${item.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ quantity: q }),
            });
        },

        async deleteItem(id) {
            this.items = this.items.filter(i => i.id !== id); // optimistic
            await fetch(`/api/shopping-lists/${this.list.id}/items/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
        },

        async clearChecked() {
            const checked = this.items.filter(i => i.checked);
            this.items = this.items.filter(i => !i.checked);
            await Promise.all(checked.map(i =>
                fetch(`/api/shopping-lists/${this.list.id}/items/${i.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                })
            ));
        },

        // ── Barcode ────────────────────────────────────────────────────────
        async scanBarcode() {
            if (!this.barcodeInput) return;
            this.barcodeLoading = true;
            try {
                const res = await fetch('/api/shopping-lists/lookup-barcode', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ barcode: this.barcodeInput }),
                });
                if (res.ok) this.barcodeResult = await res.json(); else this.flash('Product not found', true);
            } catch (e) {
                this.flash('Error scanning barcode', true);
            }
            this.barcodeLoading = false;
        },
        async addFromBarcode() {
            if (!this.barcodeResult) return;
            await this.postItem({
                name: this.barcodeResult.name, quantity: 1, unit: 'piece', barcode: this.barcodeResult.barcode,
            });
            this.barcodeResult = null;
            this.barcodeInput = '';
            await this.loadItems();
        },

        flash(msg, isError = false) {
            this.toast = { msg, isError };
            setTimeout(() => { this.toast = null; }, 2500);
        },
    };
};
