// Bills page Alpine component
window.billsPageCal = function () {
    return {
        calOpen: true,
        today: new Date(),
        current: new Date(),
        events: [],
        loading: false,

        get year() {
            return this.current.getFullYear();
        },
        get month() {
            return this.current.getMonth();
        },
        get monthName() {
            return this.current.toLocaleString('default', {month: 'long'});
        },

        init() {
            this.current = new Date(this.today.getFullYear(), this.today.getMonth(), 1);
            this.fetchEvents();
        },

        prevMonth() {
            this.current = new Date(this.year, this.month - 1, 1);
            this.fetchEvents();
        },
        nextMonth() {
            this.current = new Date(this.year, this.month + 1, 1);
            this.fetchEvents();
        },
        goToday() {
            this.current = new Date(this.today.getFullYear(), this.today.getMonth(), 1);
            this.fetchEvents();
        },

        async fetchEvents() {
            this.loading = true;
            const start = new Date(this.year, this.month, 1);
            const end = new Date(this.year, this.month + 1, 0);
            const fmt = d => d.toISOString().split('T')[0];
            try {
                const r = await fetch(`/bills/events?start=${fmt(start)}&end=${fmt(end)}`);
                this.events = await r.json();
            } catch (e) {
                this.events = [];
            }
            this.loading = false;
        },

        get calendarCells() {
            const year = this.year, month = this.month;
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            let startDow = firstDay.getDay();
            startDow = startDow === 0 ? 6 : startDow - 1;
            const cells = [];
            for (let i = startDow - 1; i >= 0; i--) {
                const d = new Date(year, month, -i);
                cells.push({day: d.getDate(), date: this.dateStr(d), currentMonth: false, isToday: false, events: []});
            }
            for (let d = 1; d <= lastDay.getDate(); d++) {
                const date = new Date(year, month, d);
                const ds = this.dateStr(date);
                cells.push({
                    day: d,
                    date: ds,
                    currentMonth: true,
                    isToday: ds === this.dateStr(this.today),
                    events: this.events.filter(e => e.start === ds)
                });
            }
            const rem = 7 - (cells.length % 7);
            if (rem < 7) for (let i = 1; i <= rem; i++) {
                const d = new Date(year, month + 1, i);
                cells.push({day: d.getDate(), date: this.dateStr(d), currentMonth: false, isToday: false, events: []});
            }
            return cells;
        },

        dateStr(d) {
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        },
    };
};

