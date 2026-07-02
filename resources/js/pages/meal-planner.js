// Weekly meal planner Alpine component
window.mealPlanner = function (payload) {
    return {
        weekStart: payload.weekStart,          // 'YYYY-MM-DD' (Monday)
        days: payload.days,                    // [{date, label, weekday, isToday}]
        mealTypes: payload.mealTypes,          // [{key, label}]
        recipes: payload.recipes,              // [{id, name, emoji, servings, prep_minutes, cook_minutes}]
        lists: payload.lists,                  // [{id, name}]
        plans: payload.plans,                  // [{id, date, meal_type, recipe_id, title, servings, notes, name, emoji, recipe_url}]
        routes: payload.routes,                // {store, toList}

        // meal modal state
        modalOpen: false,
        editing: null,
        recipeSearch: '',
        form: { date: '', meal_type: '', recipe_id: null, title: '', servings: 2, notes: '' },

        // build-list modal state
        listModalOpen: false,
        listForm: { mode: 'new', shopping_list_id: '', new_list_name: '' },
        working: false,
        toast: null,

        planFor(date, type) {
            return this.plans.filter(p => p.date === date && p.meal_type === type);
        },

        get plannedCount() {
            return this.plans.length;
        },

        get filteredRecipes() {
            const q = this.recipeSearch.trim().toLowerCase();
            if (!q) return this.recipes;
            return this.recipes.filter(r => r.name.toLowerCase().includes(q));
        },

        // ── Add / edit meal ────────────────────────────────────────────────
        openAdd(date, type) {
            this.editing = null;
            this.recipeSearch = '';
            this.form = { date, meal_type: type, recipe_id: null, title: '', servings: 2, notes: '' };
            this.modalOpen = true;
        },
        openEdit(plan) {
            this.editing = plan;
            this.recipeSearch = '';
            this.form = {
                date: plan.date, meal_type: plan.meal_type,
                recipe_id: plan.recipe_id, title: plan.title || '',
                servings: plan.servings, notes: plan.notes || '',
            };
            this.modalOpen = true;
        },
        pickRecipe(r) {
            this.form.recipe_id = r.id;
            this.form.title = '';
            if (r.servings) this.form.servings = r.servings;
        },

        async saveMeal() {
            if (!this.form.recipe_id && !this.form.title.trim()) {
                this.flash('Pick a recipe or type a meal', true);
                return;
            }
            this.working = true;
            const isEdit = !!this.editing;
            const url = isEdit ? `${this.routes.base}/${this.editing.id}` : this.routes.store;
            try {
                const res = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify(this.form),
                });
                if (!res.ok) { this.flash('Could not save meal', true); this.working = false; return; }
                const { data } = await res.json();
                if (isEdit) {
                    const i = this.plans.findIndex(p => p.id === data.id);
                    if (i !== -1) this.plans.splice(i, 1, data);
                } else {
                    this.plans.push(data);
                }
                this.modalOpen = false;
            } catch (e) {
                this.flash(e.message, true);
            }
            this.working = false;
        },

        async removeMeal(plan) {
            try {
                await fetch(`${this.routes.base}/${plan.id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                this.plans = this.plans.filter(p => p.id !== plan.id);
                this.modalOpen = false;
            } catch (e) { /* ignore */ }
        },

        // ── Build shopping list ────────────────────────────────────────────
        openBuildList() {
            this.listForm = {
                mode: this.lists.length ? 'existing' : 'new',
                shopping_list_id: this.lists[0]?.id || '',
                new_list_name: '',
            };
            this.listModalOpen = true;
        },
        async buildList() {
            this.working = true;
            const body = { week: this.weekStart };
            if (this.listForm.mode === 'existing') body.shopping_list_id = this.listForm.shopping_list_id;
            else body.new_list_name = this.listForm.new_list_name;

            try {
                const res = await fetch(this.routes.toList, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (!res.ok) { this.flash(data.message || 'Failed', true); this.working = false; return; }
                this.listModalOpen = false;
                this.flash(data.message);
                setTimeout(() => { if (data.url) window.location = data.url; }, 900);
            } catch (e) {
                this.flash(e.message, true);
            }
            this.working = false;
        },

        flash(msg, isError = false) {
            this.toast = { msg, isError };
            setTimeout(() => { this.toast = null; }, 3000);
        },

        gotoWeek(offsetDays) {
            const d = new Date(this.weekStart + 'T00:00:00');
            d.setDate(d.getDate() + offsetDays);
            const iso = d.toISOString().split('T')[0];
            window.location = `${this.routes.index}?week=${iso}`;
        },
        gotoToday() {
            window.location = this.routes.index;
        },
    };
};
