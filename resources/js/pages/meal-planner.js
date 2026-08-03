// Weekly meal planner Alpine component
window.mealPlanner = function (payload) {
    return {
        weekStart: payload.weekStart,          // 'YYYY-MM-DD' (Monday)
        days: payload.days,                    // [{date, label, weekday, isToday}]
        mealTypes: payload.mealTypes,          // [{key, label}]
        recipes: payload.recipes,              // [{id, name, image, servings, minutes}]
        lists: payload.lists,                  // [{id, name}]
        plans: payload.plans,                  // [{id, date, meal_type, recipe_id, title, servings, notes, name, image, recipe_url, minutes}]
        routes: payload.routes,                // {index, store, base, toList}
        t: payload.i18n,                       // user-facing strings — this file must not hardcode any

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

        // drag & drop state
        dragging: null,                        // the plan being dragged
        dragOverSlot: null,                    // 'YYYY-MM-DD|meal_type' currently hovered

        planFor(date, type) {
            return this.plans.filter(p => p.date === date && p.meal_type === type);
        },

        // ── Week summary ───────────────────────────────────────────────────
        get plannedCount() {
            return this.plans.length;
        },
        get totalSlots() {
            return this.days.length * this.mealTypes.length;
        },
        /** Slots holding at least one meal — not the meal count, which can exceed one per slot. */
        get filledSlots() {
            return new Set(this.plans.map(p => `${p.date}|${p.meal_type}`)).size;
        },
        get fillPercent() {
            return this.totalSlots ? Math.round((this.filledSlots / this.totalSlots) * 100) : 0;
        },
        /** Distinct recipes in the week — what the shopping list will actually draw from. */
        get recipeCount() {
            return new Set(this.plans.filter(p => p.recipe_id).map(p => p.recipe_id)).size;
        },
        get servingsCount() {
            return this.plans.reduce((sum, p) => sum + (Number(p.servings) || 0), 0);
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

        headers(json = true) {
            const h = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            };
            if (json) h['Content-Type'] = 'application/json';
            return h;
        },

        async saveMeal() {
            if (!this.form.recipe_id && !this.form.title.trim()) {
                this.flash(this.t.pick_recipe_or_title, true);
                return;
            }
            this.working = true;
            const isEdit = !!this.editing;
            const url = isEdit ? `${this.routes.base}/${this.editing.id}` : this.routes.store;
            try {
                const res = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: this.headers(),
                    body: JSON.stringify(this.form),
                });
                if (!res.ok) { this.flash(this.t.save_failed, true); this.working = false; return; }
                const { data } = await res.json();
                if (isEdit) {
                    const i = this.plans.findIndex(p => p.id === data.id);
                    if (i !== -1) this.plans.splice(i, 1, data);
                } else {
                    this.plans.push(data);
                }
                this.modalOpen = false;
            } catch (e) {
                this.flash(this.t.save_failed, true);
            }
            this.working = false;
        },

        async removeMeal(plan) {
            try {
                const res = await fetch(`${this.routes.base}/${plan.id}`, {
                    method: 'DELETE',
                    headers: this.headers(false),
                });
                if (!res.ok) { this.flash(this.t.delete_failed, true); return; }
                this.plans = this.plans.filter(p => p.id !== plan.id);
                this.modalOpen = false;
            } catch (e) {
                this.flash(this.t.delete_failed, true);
            }
        },

        // ── Drag & drop ────────────────────────────────────────────────────
        // Moving a meal is just an update of its date + meal_type, so this reuses
        // the existing PUT rather than adding an endpoint. The move is applied
        // locally first and rolled back if the request fails.
        slotKey(date, type) {
            return `${date}|${type}`;
        },
        onDragStart(plan, event) {
            this.dragging = plan;
            if (event?.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                // Firefox refuses to start a drag unless some data is set.
                event.dataTransfer.setData('text/plain', plan.id);
            }
        },
        onDragEnd() {
            this.dragging = null;
            this.dragOverSlot = null;
        },
        /** True when the dragged meal would actually land somewhere new. */
        isDropTarget(date, type) {
            if (!this.dragging) return false;
            return this.dragging.date !== date || this.dragging.meal_type !== type;
        },
        onDragOver(date, type) {
            if (this.isDropTarget(date, type)) this.dragOverSlot = this.slotKey(date, type);
        },
        onDragLeave(date, type) {
            if (this.dragOverSlot === this.slotKey(date, type)) this.dragOverSlot = null;
        },
        async onDrop(date, type) {
            const plan = this.dragging;
            this.dragOverSlot = null;
            this.dragging = null;
            if (!plan || (plan.date === date && plan.meal_type === type)) return;

            await this.movePlan(plan, date, type);
        },
        async movePlan(plan, date, meal_type) {
            const from = { date: plan.date, meal_type: plan.meal_type };
            const i = this.plans.findIndex(p => p.id === plan.id);
            if (i === -1) return;

            // Optimistic: the grid should follow the pointer immediately.
            this.plans.splice(i, 1, { ...plan, date, meal_type });

            try {
                const res = await fetch(`${this.routes.base}/${plan.id}`, {
                    method: 'PUT',
                    headers: this.headers(),
                    body: JSON.stringify({ date, meal_type }),
                });
                if (!res.ok) throw new Error();
                const { data } = await res.json();
                const j = this.plans.findIndex(p => p.id === data.id);
                if (j !== -1) this.plans.splice(j, 1, data);
            } catch (e) {
                const j = this.plans.findIndex(p => p.id === plan.id);
                if (j !== -1) this.plans.splice(j, 1, { ...plan, ...from });
                this.flash(this.t.move_failed, true);
            }
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
                    headers: this.headers(),
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (!res.ok) { this.flash(data.message || this.t.save_failed, true); this.working = false; return; }
                this.listModalOpen = false;
                this.flash(data.message);
                setTimeout(() => { if (data.url) window.location = data.url; }, 900);
            } catch (e) {
                this.flash(this.t.save_failed, true);
            }
            this.working = false;
        },

        flash(msg, isError = false) {
            this.toast = { msg, isError };
            setTimeout(() => { this.toast = null; }, 3000);
        },

        // ── Week navigation ────────────────────────────────────────────────
        /**
         * Format a Date using its *local* civil date.
         *
         * `toISOString()` converts to UTC first, so east-of-Greenwich users lost a
         * day: a Monday became the preceding Sunday, and the server's
         * startOfWeek(MONDAY) then snapped that back another week. The visible
         * symptom was that "next week" returned the week you were already on.
         */
        isoDate(d) {
            const pad = n => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        },
        gotoWeek(offsetDays) {
            const d = new Date(this.weekStart + 'T00:00:00');
            d.setDate(d.getDate() + offsetDays);
            window.location = `${this.routes.index}?week=${this.isoDate(d)}`;
        },
        gotoToday() {
            window.location = this.routes.index;
        },
    };
};
