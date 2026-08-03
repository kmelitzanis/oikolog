// Recipe create/edit form Alpine component
window.recipeForm = function (initial, config) {
    return {
        submitting: false,
        error: null,

        // photo state
        uploading: false,
        imageUrl: (initial && initial.image_url) || null,

        // import-from-URL state
        importOpen: false,
        importing: false,
        importUrl: '',
        importNotice: null,

        routes: config.routes,          // {upload, import}
        t: config.i18n,                 // user-facing strings live on the server

        form: {
            name: '',
            description: '',
            image_path: null,
            source_url: null,
            servings: 2,
            prep_minutes: '',
            cook_minutes: '',
            difficulty: 'easy',
            ingredients: [{ section: '', name: '', quantity: 1, unit: 'piece', product_id: null }],
            steps: [{ section: '', text: '' }],
            ...(initial || {}),
        },

        init() {
            // `image_url` is display-only; it must not be posted back as a field.
            delete this.form.image_url;
            this.ensureOneIngredient();
            this.ensureOneStep();
        },

        ensureOneIngredient() {
            if (!this.form.ingredients || this.form.ingredients.length === 0) {
                this.form.ingredients = [{ section: '', name: '', quantity: 1, unit: 'piece', product_id: null }];
            }
        },
        ensureOneStep() {
            if (!this.form.steps || this.form.steps.length === 0) {
                this.form.steps = [{ section: '', text: '' }];
            }
        },

        // ── Sections ───────────────────────────────────────────────────────
        // A section is just the heading stored on each row, so grouping is
        // derived rather than held as separate state — there is no way for the
        // two to drift apart, and reordering can't orphan a heading.
        sectionsOf(rows) {
            const seen = [];
            for (const row of rows) {
                const name = row.section || '';
                if (!seen.includes(name)) seen.push(name);
            }
            return seen.length ? seen : [''];
        },
        get ingredientSections() { return this.sectionsOf(this.form.ingredients); },
        get stepSections()       { return this.sectionsOf(this.form.steps); },

        rowsIn(rows, section) {
            return rows.filter(r => (r.section || '') === section);
        },
        /** Index in the flat array — templates iterate groups but edit the source rows. */
        indexOf(rows, row) {
            return rows.indexOf(row);
        },

        renameSection(rows, from, to) {
            const next = (to || '').trim();
            rows.forEach(r => { if ((r.section || '') === from) r.section = next; });
        },

        addIngredientSection() {
            this.form.ingredients.push({
                section: this.newSectionName(this.ingredientSections),
                name: '', quantity: 1, unit: 'piece', product_id: null,
            });
        },
        addStepSection() {
            this.form.steps.push({ section: this.newSectionName(this.stepSections), text: '' });
        },
        /** "Part 2", "Part 3"… — never a duplicate, which would merge two groups. */
        newSectionName(existing) {
            let n = existing.filter(s => s !== '').length + 1;
            let name = `${this.t.section_default} ${n}`;
            while (existing.includes(name)) name = `${this.t.section_default} ${++n}`;
            return name;
        },
        removeSection(rows, section) {
            for (let i = rows.length - 1; i >= 0; i--) {
                if ((rows[i].section || '') === section) rows.splice(i, 1);
            }
        },

        headers(json = true) {
            const h = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            };
            if (json) h['Content-Type'] = 'application/json';
            return h;
        },

        // ── Photo ──────────────────────────────────────────────────────────
        async pickImage(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            this.uploading = true;
            this.error = null;

            const body = new FormData();
            body.append('image', file);

            try {
                const res = await fetch(this.routes.upload, { method: 'POST', headers: this.headers(false), body });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.error = data.message || this.t.image_failed;
                } else {
                    this.form.image_path = data.path;
                    this.imageUrl = data.url;
                }
            } catch (e) {
                this.error = this.t.image_failed;
            }

            this.uploading = false;
            event.target.value = '';   // let the same file be re-picked after a failure
        },

        clearImage() {
            this.form.image_path = null;
            this.imageUrl = null;
        },

        // ── Import from a URL ──────────────────────────────────────────────
        async runImport() {
            const url = this.importUrl.trim();
            if (!url) return;

            this.importing = true;
            this.error = null;
            this.importNotice = null;

            try {
                const res = await fetch(this.routes.import, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({ url }),
                });
                const payload = await res.json().catch(() => ({}));

                if (!res.ok) {
                    this.error = payload.message || this.t.import_failed;
                    this.importing = false;
                    return;
                }

                this.applyImport(payload.data);
                this.importOpen = false;
                this.importUrl = '';
            } catch (e) {
                this.error = this.t.import_failed;
            }

            this.importing = false;
        },

        /**
         * Merge parsed values into the form.
         *
         * Only fields the page actually supplied are overwritten, so a partial
         * import cannot blank out something already typed in.
         */
        applyImport(data) {
            if (!data) return;

            for (const key of ['name', 'description', 'servings', 'prep_minutes', 'cook_minutes', 'source_url']) {
                if (data[key] !== null && data[key] !== undefined && data[key] !== '') {
                    this.form[key] = data[key];
                }
            }

            if (Array.isArray(data.ingredients) && data.ingredients.length) {
                this.form.ingredients = data.ingredients.map(i => ({
                    section: i.section || '', name: i.name, quantity: i.quantity, unit: i.unit, product_id: null,
                }));
            }
            if (Array.isArray(data.steps) && data.steps.length) {
                this.form.steps = data.steps.map(s => ({ section: s.section || '', text: s.text }));
            }
            this.ensureOneIngredient();
            this.ensureOneStep();

            if (data.image_path) {
                this.form.image_path = data.image_path;
                this.imageUrl = data.image_url;
            }

            // Say plainly when the page had no structured recipe, so a thin result
            // reads as "this page didn't offer much", not "the importer is broken".
            this.importNotice = data.matched ? this.t.import_review : this.t.import_partial;
        },

        // ── Ingredients ────────────────────────────────────────────────────
        addIngredient(section = '') {
            this.form.ingredients.push({ section, name: '', quantity: 1, unit: 'piece', product_id: null });
            this.$nextTick(() => {
                const inputs = this.$root.querySelectorAll('[data-ing-name]');
                inputs[inputs.length - 1]?.focus();
            });
        },
        removeIngredient(i) {
            this.form.ingredients.splice(i, 1);
            this.ensureOneIngredient();
        },

        addStep(section = '') {
            this.form.steps.push({ section, text: '' });
        },
        removeStep(i) {
            this.form.steps.splice(i, 1);
            this.ensureOneStep();
        },

        get totalMinutes() {
            const p = parseInt(this.form.prep_minutes) || 0;
            const c = parseInt(this.form.cook_minutes) || 0;
            return p + c;
        },

        async submit(action, method) {
            if (!this.form.name.trim()) { this.error = this.t.name_required; return; }
            const filled = this.form.ingredients.filter(i => i.name && i.name.trim());
            if (filled.length === 0) { this.error = this.t.ingredient_required; return; }

            this.submitting = true;
            this.error = null;
            try {
                const res = await fetch(action, {
                    method,
                    headers: this.headers(),
                    body: JSON.stringify({
                        ...this.form,
                        ingredients: filled,
                        steps: this.form.steps.filter(s => s.text && s.text.trim()),
                    }),
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    this.error = err.message || this.t.save_failed;
                    this.submitting = false;
                    return;
                }
                const data = await res.json();
                window.location = data.url;
            } catch (e) {
                this.error = this.t.save_failed;
                this.submitting = false;
            }
        },
    };
};
