// Shopping list (single) Alpine component
window.shoppingListApp = function () {
    return {
        list: {},
        items: [],
        loading: false,
        saving: false,
        viewMode: 'list',
        addItemModalOpen: false,
        editingItem: null,
        itemForm: {name: '', quantity: 1, unit: 'piece'},
        products: [],
        productQuery: '',
        selectedProduct: null,
        selectedProductId: null,
        barcodeInput: '',
        barcodeResult: null,
        barcodeLoading: false,

        init(listData) {
            this.list = listData;
            this.loadItems();
            this.loadProducts();
        },

        async loadItems() {
            this.loading = true;
            const res = await fetch(`/api/shopping-lists/${this.list.id}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();
            this.items = data.items ?? [];
            this.loading = false;
        },

        get allChecked() {
            return this.items.length > 0 && this.items.every(i => i.checked);
        },

        toggleAllItems() {
            const newChecked = !this.allChecked;
            this.items.forEach(item => {
                if (item.checked !== newChecked) this.toggleItem(item);
            });
        },

        async toggleItem(item) {
            await fetch(`/api/shopping-lists/${this.list.id}/items/${item.id}/toggle`, {
                method: 'PATCH',
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}
            });
            await this.loadItems();
        },

        editItem(item) {
            this.editingItem = item;
            this.itemForm = {name: item.name, quantity: item.quantity, unit: item.unit};
            this.addItemModalOpen = true;
        },

        async loadProducts() {
            try {
                const res = await fetch('/api/products?per_page=200', {headers: {'Accept': 'application/json'}});
                if (!res.ok) return;
                const data = await res.json();
                // API returns paginated data
                this.products = data.data || data;
            } catch (e) {
                console.error('Failed to load products', e);
            }
        },

        selectProduct(p) {
            this.selectedProduct = p;
            this.itemForm.name = p.name;
            this.itemForm.unit = p.unit || 'piece';
            this.itemForm.quantity = p.default_quantity || 1;
            this.itemForm.barcode = p.barcode || null;
        },

        async saveItem() {
            this.saving = true;
            const method = this.editingItem ? 'PUT' : 'POST';
            const url = this.editingItem ? `/api/shopping-lists/${this.list.id}/items/${this.editingItem.id}` : `/api/shopping-lists/${this.list.id}/items`;
            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.itemForm),
                });

                if (!res.ok) {
                    const error = await res.json();
                    console.error('API Error:', error);
                    alert('Error: ' + (error.message || 'Failed to save item'));
                    this.saving = false;
                    return;
                }

                this.saving = false;
                this.addItemModalOpen = false;
                this.editingItem = null;
                this.itemForm = {name: '', quantity: 1, unit: 'piece'};
                await this.loadItems();
            } catch (err) {
                console.error('Fetch error:', err);
                alert('Error: ' + err.message);
                this.saving = false;
            }
        },

        async deleteItem(id) {
            if (!confirm('Are you sure?')) return;
            await fetch(`/api/shopping-lists/${this.list.id}/items/${id}`, {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}
            });
            await this.loadItems();
        },

        async scanBarcode() {
            if (!this.barcodeInput) return;
            this.barcodeLoading = true;
            try {
                const res = await fetch('/api/shopping-lists/lookup-barcode', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({barcode: this.barcodeInput}),
                });

                if (res.ok) this.barcodeResult = await res.json(); else alert('Product not found');
            } catch (e) {
                alert('Error scanning barcode');
            }
            this.barcodeLoading = false;
        },

        async addFromBarcode() {
            if (!this.barcodeResult) return;
            const itemData = {
                name: this.barcodeResult.name,
                quantity: 1,
                unit: 'piece',
                barcode: this.barcodeResult.barcode
            };
            await fetch(`/api/shopping-lists/${this.list.id}/items`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(itemData)
            });
            this.barcodeResult = null;
            this.barcodeInput = '';
            await this.loadItems();
        },
    };
};

