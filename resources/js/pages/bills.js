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
        /** Mon…Sun in the browser's locale, matching the Monday-first grid. */
        get weekdayNames() {
            return Array.from({length: 7}, (_, i) =>
                // 2024-01-01 was a Monday, so this walks Mon → Sun.
                new Date(2024, 0, 1 + i).toLocaleString('default', {weekday: 'short'}));
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
            // Fetch the whole visible grid, not just the month: macOS-style month
            // views show events on the leading/trailing days too.
            const days = this.gridDays();
            const start = days[0];
            const end = days[days.length - 1];
            try {
                const r = await fetch(`/bills/events?start=${this.dateStr(start)}&end=${this.dateStr(end)}`);
                this.events = await r.json();
            } catch (e) {
                this.events = [];
            }
            this.loading = false;
        },

        /** The 35 or 42 Date objects the month grid renders, Monday-first. */
        gridDays() {
            const year = this.year, month = this.month;
            let startDow = new Date(year, month, 1).getDay();
            startDow = startDow === 0 ? 6 : startDow - 1;
            const total = new Date(year, month + 1, 0).getDate();
            const count = Math.ceil((startDow + total) / 7) * 7;
            const days = [];
            for (let i = 0; i < count; i++) {
                days.push(new Date(year, month, 1 - startDow + i));
            }
            return days;
        },

        get calendarCells() {
            const todayStr = this.dateStr(this.today);
            return this.gridDays().map(d => {
                const ds = this.dateStr(d);
                return {
                    day: d.getDate(),
                    date: ds,
                    // First cell of a month gets the month name, like macOS ("Aug 1").
                    monthLabel: d.getDate() === 1 ? d.toLocaleString('default', {month: 'short'}) : '',
                    currentMonth: d.getMonth() === this.month,
                    isWeekend: d.getDay() === 0 || d.getDay() === 6,
                    isToday: ds === todayStr,
                    events: this.events.filter(e => e.start === ds),
                };
            });
        },

        /** Titles arrive prefixed with a bullet for the old FullCalendar look. */
        eventTitle(e) {
            return (e.title || '').replace(/^[•·]\s*/, '');
        },

        dateStr(d) {
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        },
    };
};

