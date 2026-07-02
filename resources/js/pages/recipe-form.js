// Recipe create/edit form Alpine component
window.recipeForm = function (initial) {
    return {
        submitting: false,
        error: null,
        emojiChoices: ['🍽️', '🍕', '🍝', '🥗', '🍜', '🍲', '🥘', '🍳', '🥞', '🧀', '🍔', '🌮', '🍛', '🍣', '🍤', '🥩', '🍗', '🥧', '🍰', '🧁', '🍩', '☕', '🥤', '🫓'],
        form: {
            name: '',
            emoji: '🍽️',
            description: '',
            servings: 2,
            prep_minutes: '',
            cook_minutes: '',
            difficulty: 'easy',
            instructions: '',
            ingredients: [{ name: '', quantity: 1, unit: 'piece', product_id: null }],
            ...(initial || {}),
        },

        init() {
            if (!this.form.ingredients || this.form.ingredients.length === 0) {
                this.form.ingredients = [{ name: '', quantity: 1, unit: 'piece', product_id: null }];
            }
            if (!this.form.emoji) this.form.emoji = '🍽️';
        },

        addIngredient() {
            this.form.ingredients.push({ name: '', quantity: 1, unit: 'piece', product_id: null });
            this.$nextTick(() => {
                const inputs = this.$root.querySelectorAll('[data-ing-name]');
                inputs[inputs.length - 1]?.focus();
            });
        },
        removeIngredient(i) {
            this.form.ingredients.splice(i, 1);
            if (this.form.ingredients.length === 0) this.addIngredient();
        },

        get totalMinutes() {
            const p = parseInt(this.form.prep_minutes) || 0;
            const c = parseInt(this.form.cook_minutes) || 0;
            return p + c;
        },

        async submit(action, method) {
            if (!this.form.name.trim()) { this.error = 'Name is required'; return; }
            const filled = this.form.ingredients.filter(i => i.name && i.name.trim());
            if (filled.length === 0) { this.error = 'Add at least one ingredient'; return; }

            this.submitting = true;
            this.error = null;
            try {
                const res = await fetch(action, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ ...this.form, ingredients: filled }),
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    this.error = err.message || 'Something went wrong';
                    this.submitting = false;
                    return;
                }
                const data = await res.json();
                window.location = data.url;
            } catch (e) {
                this.error = e.message;
                this.submitting = false;
            }
        },
    };
};
