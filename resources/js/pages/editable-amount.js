// Inline editor for a varying bill's amount for the current cycle.
// Paired with resources/views/components/editable-amount.blade.php.

window.editableAmount = function ({url, currency, amount}) {
    return {
        editing: false,
        saving: false,
        amount,
        currency,
        draft: amount === null ? '' : String(amount),

        format(value) {
            return Number(value).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        edit() {
            if (this.saving) return;
            this.draft = this.amount === null ? '' : String(this.amount);
            this.editing = true;
            // $refs.input only exists once the template has rendered.
            this.$nextTick(() => {
                this.$refs.input?.focus();
                this.$refs.input?.select();
            });
        },

        cancel() {
            this.editing = false;
        },

        async save() {
            if (!this.editing) return;

            const raw = String(this.draft).trim();
            const next = raw === '' ? null : Number(raw);

            // Nothing typed, or the same figure: don't spend a request on it.
            if (next === this.amount || (next !== null && Number.isNaN(next))) {
                this.editing = false;
                return;
            }

            this.editing = false;
            this.saving = true;

            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                    body: JSON.stringify({current_amount: next}),
                });

                if (!res.ok) throw new Error(await res.text());

                const data = await res.json();
                this.amount = data.current_amount === null ? null : Number(data.current_amount);
                // Other places on the page show the same figure (the row's
                // per-month line, the page total); let them refresh.
                window.dispatchEvent(new CustomEvent('bill-amount-saved', {detail: {url, ...data}}));
            } catch (e) {
                // Put the old value back rather than showing a figure the
                // server never accepted.
                this.draft = this.amount === null ? '' : String(this.amount);
            } finally {
                this.saving = false;
            }
        },
    };
};
