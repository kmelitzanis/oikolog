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

        /**
         * Localised label for a canonical unit key.
         *
         * Labels come from the server (window.__unitLabels) so this file holds no
         * user-facing text; an unknown key is shown as-is rather than swallowed,
         * which surfaces anything that escaped canonicalisation.
         */
        unitLabel(unit) {
            if (!unit) return '';
            return (window.__unitLabels || {})[unit] || unit;
        },
        products: [],
        suggestions: [],
        suggestionIndex: -1,
        suggestTimer: null,
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

        /**
         * A list is kept permanently stocked: most lines stay ticked and only
         * what is needed gets un-ticked. So progress is measured over the
         * current round — the lines still to buy, plus the ones ticked off in
         * the last day — and not over every tick ever made. A day after the
         * shopping is done the bar is empty again, with nothing un-ticked.
         */
        checkedRecently(item) {
            if (!item.checked || !item.checked_at) return false;
            const hours = (Date.now() - new Date(item.checked_at).getTime()) / 36e5;
            return hours < 24;
        },
        get roundBought() { return this.items.filter(i => this.checkedRecently(i)).length; },
        get roundPending() { return this.items.filter(i => !i.checked).length; },
        get roundTotal() { return this.roundBought + this.roundPending; },
        get hasRound() { return this.roundTotal > 0; },
        get progress() { return this.roundTotal ? Math.round(this.roundBought / this.roundTotal * 100) : 0; },
        get progressDash() {
            const c = 2 * Math.PI * 26; // r=26
            return `${c * this.progress / 100} ${c}`;
        },

        // ── Products ───────────────────────────────────────────────────────

        /**
         * The Nutri-Score grade behind a line, or null. Items typed by hand have
         * no product and therefore no grade — the badge still renders, muted, so
         * rows keep the same shape.
         */
        grade(item) {
            const g = (item.product && item.product.nutri_score || '').toLowerCase();
            return ['a', 'b', 'c', 'd', 'e'].includes(g) ? g : null;
        },
        gradeClass(item) {
            return {
                a: 'bg-[#038141] text-white',
                b: 'bg-[#85bb2f] text-white',
                c: 'bg-[#fecb02] text-slate-900',
                d: 'bg-[#ee8100] text-white',
                e: 'bg-[#e63e11] text-white',
            }[this.grade(item)] || 'bg-gray-100 dark:bg-slate-700 text-gray-400 dark:text-slate-500';
        },
        gradeLabel(item) {
            const g = this.grade(item);
            return g ? g.toUpperCase() : '—';
        },

        /** Open the product's page, creating one first for a hand-typed line. */
        async openProduct(item) {
            if (item.product_id) {
                window.location = `/products/${item.product_id}`;
                return;
            }
            const res = await fetch(`/shopping-list/items/${item.id}/promote`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
            if (res.ok) window.location = res.url;
            else this.flash('Could not open the product', true);
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

        // ── Type-ahead on the quick-add input ──────────────────────────────

        /**
         * Ask for suggestions after a short pause, so a fast typist makes one
         * request instead of one per keystroke.
         */
        suggest() {
            clearTimeout(this.suggestTimer);
            this.suggestionIndex = -1;

            const term = this.quickName.trim();
            if (term.length < 2) { this.suggestions = []; return; }

            this.suggestTimer = setTimeout(async () => {
                try {
                    const res = await fetch(`/api/products/suggest?q=${encodeURIComponent(term)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    this.suggestions = res.ok ? await res.json() : [];
                } catch (e) { this.suggestions = []; }
            }, 180);
        },

        moveSuggestion(step) {
            if (!this.suggestions.length) return;
            const next = this.suggestionIndex + step;
            this.suggestionIndex = next < -1 ? this.suggestions.length - 1
                : next >= this.suggestions.length ? -1 : next;
        },

        dismissSuggestions() {
            this.suggestions = [];
            this.suggestionIndex = -1;
        },

        /** Add a suggested product, keeping the link to the catalogue entry. */
        async addSuggestion(p) {
            this.quickName = '';
            this.dismissSuggestions();
            await this.postItem({
                name: p.name,
                quantity: p.default_quantity || 1,
                unit: p.unit || 'piece',
                product_id: p.id,
            });
            await this.loadItems();
        },

        // Quick add straight from the header input
        async quickAdd() {
            // Enter with a highlighted suggestion takes it rather than the
            // half-typed text.
            if (this.suggestionIndex >= 0 && this.suggestions[this.suggestionIndex]) {
                return this.addSuggestion(this.suggestions[this.suggestionIndex]);
            }

            const name = this.quickName.trim();
            if (!name) return;
            this.quickName = '';
            this.dismissSuggestions();
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
