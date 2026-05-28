// Calendar Alpine component
window.calendarApp = function () {
    return {
        today: new Date(),
        current: new Date(),
        view: 'month',
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
                const td = this.dateStr(this.today);
                const dayEvents = this.events.filter(e => e.start === ds);
                const hasPaid = dayEvents.some(e => e.extendedProps?.paid);
                const hasOverdue = dayEvents.some(e => e.extendedProps?.overdue);
                const hasSoon = dayEvents.some(e => e.extendedProps?.soon && !e.extendedProps?.paid);
                const hasUpcoming = dayEvents.some(e => !e.extendedProps?.paid && !e.extendedProps?.overdue && !e.extendedProps?.soon);
                cells.push({
                    day: d,
                    date: ds,
                    currentMonth: true,
                    isToday: ds === td,
                    events: dayEvents,
                    hasPaid,
                    hasOverdue,
                    hasSoon,
                    hasUpcoming,
                    count: dayEvents.length
                });
            }
            const remaining = 7 - (cells.length % 7);
            if (remaining < 7) for (let i = 1; i <= remaining; i++) {
                const d = new Date(year, month + 1, i);
                cells.push({day: d.getDate(), date: this.dateStr(d), currentMonth: false, isToday: false, events: []});
            }
            return cells;
        },

        get listEvents() {
            return this.events.filter(e => {
                const [y, m] = e.start.split('-').map(Number);
                return y === this.year && m === this.month + 1;
            }).sort((a, b) => a.start.localeCompare(b.start));
        },

        get groupedListEvents() {
            const groups = {};
            this.listEvents.forEach(ev => {
                if (!groups[ev.start]) groups[ev.start] = {label: this.formatShortDate(ev.start), events: []};
                groups[ev.start].events.push(ev);
            });
            return Object.values(groups);
        },

        dateStr(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },

        formatShortDate(str) {
            const [y, m, d] = str.split('-').map(Number);
            const date = new Date(y, m - 1, d);
            return date.toLocaleString('default', {month: 'short', day: 'numeric'});
        },
    };
};

