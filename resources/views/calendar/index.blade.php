@extends('layouts.app')
@section('title', 'Calendar')

@section('content')
    <div
        x-data="calendarApp()"
        x-init="init()"
        class="max-w-5xl mx-auto space-y-4"
    >
        {{-- ── Header ────────────────────────────────────────────────────────── --}}
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <div class="flex items-center gap-2">
                    <button @click="prevMonth()"
                            class="w-10 h-10 flex items-center justify-center rounded-2xl bg-indigo-500 hover:bg-indigo-600 text-white shadow-sm transition">
                        <span class="material-icons-round text-lg">chevron_left</span>
                    </button>
                    <button @click="nextMonth()"
                            class="w-10 h-10 flex items-center justify-center rounded-2xl bg-indigo-500 hover:bg-indigo-600 text-white shadow-sm transition">
                        <span class="material-icons-round text-lg">chevron_right</span>
                    </button>
                </div>
                <button @click="goToday()"
                        class="px-3 py-2 rounded-2xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-xs font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                    Today
                </button>
                <div class="min-w-0">
                    <div class="text-xl font-extrabold text-gray-900 dark:text-white">
                        <span x-text="monthName"></span>
                        <span class="text-gray-400 dark:text-slate-400 ml-2" x-text="year"></span>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-3 lg:items-end">
                <div class="flex flex-wrap items-center gap-3 text-xs font-medium text-gray-500 dark:text-slate-400">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                        <span>Overdue</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                        <span>Soon</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Paid</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        <span>Upcoming</span>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 self-start lg:self-auto">
                    <button @click="view='month'"
                            :class="view==='month' ? 'bg-indigo-500 text-white shadow-sm' : 'bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700'"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold transition">
                        Month
                    </button>
                    <button @click="view='list'"
                            :class="view==='list' ? 'bg-indigo-500 text-white shadow-sm' : 'bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700'"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold transition">
                        List
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Month View ─────────────────────────────────────────────────────── --}}
        <div x-show="view==='month'" x-cloak class="space-y-3">
            {{-- Day headers --}}
            <div class="grid grid-cols-7 gap-2 px-1">
                <template x-for="d in ['MON','TUE','WED','THU','FRI','SAT','SUN']">
                    <div
                        class="text-center text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-400 dark:text-slate-500 py-2"
                         x-text="d"></div>
                </template>
            </div>

            {{-- Calendar grid --}}
            <x-card flush class="p-2 md:p-3">
                <div class="grid grid-cols-7 gap-1.5 md:gap-2">
                    <template x-for="(cell, i) in calendarCells" :key="i">
                        <div
                            :class="{
                                'opacity-30': !cell.currentMonth,
                                'ring-2 ring-indigo-400 ring-offset-1 dark:ring-offset-slate-800': cell.isToday,
                                'bg-red-50/60 dark:bg-red-900/10': cell.hasOverdue && cell.currentMonth,
                            }"
                            class="relative min-h-[72px] md:min-h-[96px] rounded-2xl p-1.5 md:p-2.5 transition hover:bg-gray-50 dark:hover:bg-slate-700/40 flex flex-col"
                        >
                            {{-- Day number --}}
                            <div class="flex justify-center md:justify-start">
                                <span
                                        :class="{
                                        'bg-indigo-500 text-white shadow-sm': cell.isToday,
                                        'text-gray-800 dark:text-white font-semibold': !cell.isToday
                                    }"
                                        class="w-7 h-7 md:w-8 md:h-8 flex items-center justify-center rounded-full text-xs font-bold transition"
                                        x-text="cell.day"
                                ></span>
                            </div>

                            {{-- Status indicator dots (always shown when events exist) --}}
                            <div class="mt-1.5 flex items-center justify-center md:justify-start gap-1 flex-wrap">
                                {{-- Overdue dot --}}
                                <template x-if="cell.hasOverdue">
                                    <span class="w-2.5 h-2.5 rounded-full bg-red-500 shadow-sm shadow-red-200 dark:shadow-red-900 shrink-0"
                                          title="Overdue"></span>
                                </template>
                                {{-- Soon dot --}}
                                <template x-if="cell.hasSoon">
                                    <span class="w-2.5 h-2.5 rounded-full bg-orange-400 shadow-sm shadow-orange-200 dark:shadow-orange-900 shrink-0"
                                          title="Due soon"></span>
                                </template>
                                {{-- Paid dot --}}
                                <template x-if="cell.hasPaid">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-sm shadow-emerald-200 dark:shadow-emerald-900 shrink-0"
                                          title="Paid"></span>
                                </template>
                                {{-- Upcoming dot --}}
                                <template x-if="cell.hasUpcoming">
                                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-400 shadow-sm shadow-indigo-200 dark:shadow-indigo-900 shrink-0"
                                          title="Upcoming"></span>
                                </template>
                                {{-- Count badge when more than 3 events --}}
                                <template x-if="cell.count > 3">
                                    <span class="text-[9px] font-bold text-gray-400 dark:text-slate-500 leading-none ml-0.5"
                                          x-text="'+' + (cell.count - 3)"></span>
                                </template>
                            </div>

                            {{-- Event labels — desktop only (md+) --}}
                            <div class="hidden md:flex mt-1.5 flex-col gap-1">
                                <template x-for="(ev, ei) in cell.events.slice(0, 2)" :key="ei">
                                    <a :href="ev.url"
                                       :title="ev.title.replace('• ','')"
                                       class="inline-flex max-w-full items-center gap-1 rounded-lg px-1.5 py-0.5 text-[10px] font-semibold leading-none hover:opacity-80 transition truncate"
                                       :style="'background:' + ev.color + '22; color:' + ev.color">
                                        <span class="w-1.5 h-1.5 rounded-full shrink-0"
                                              :style="'background:' + ev.color"></span>
                                        <span class="truncate" x-text="ev.title.replace('• ','')"></span>
                                    </a>
                                </template>
                                <template x-if="cell.events.length > 2">
                                    <span class="text-[10px] font-semibold text-gray-400 dark:text-slate-500 pl-1"
                                          x-text="'+ ' + (cell.events.length - 2) + ' more'"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </x-card>
        </div>

        {{-- ── List View ──────────────────────────────────────────────────────── --}}
        <div x-show="view==='list'" x-cloak>
            <x-card flush class="overflow-hidden">
                <template x-if="listEvents.length === 0">
                    <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-slate-500">
                        <span class="material-icons-round text-4xl mb-2">event_busy</span>
                        <p class="text-sm font-medium">No events this month</p>
                    </div>
                </template>
                <template x-for="(group, gi) in groupedListEvents" :key="gi">
                    <div>
                        <div
                            class="px-4 py-2 bg-gray-50 dark:bg-slate-700/50 border-b border-gray-100 dark:border-slate-700">
                            <span class="text-xs font-bold text-gray-400 dark:text-slate-400 uppercase tracking-wide"
                                  x-text="group.label"></span>
                        </div>
                        <template x-for="(ev, ei) in group.events" :key="ei">
                            <a :href="ev.url"
                               class="flex items-center gap-3 px-4 py-3.5 border-b border-gray-50 dark:border-slate-700/50 last:border-b-0 hover:bg-gray-50 dark:hover:bg-slate-700/40 transition group">
                                <div class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background:' + ev.color"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white truncate"
                                         x-text="ev.title.replace('• ','')"></div>
                                    <div class="text-xs text-gray-400 dark:text-slate-500 mt-0.5"
                                         x-text="ev.extendedProps?.amount ?? ''"></div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <template x-if="ev.extendedProps?.paid">
                                        <span
                                            class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 rounded-full">Paid</span>
                                    </template>
                                    <template x-if="ev.extendedProps?.overdue">
                                        <span
                                            class="text-[10px] font-bold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 px-2 py-0.5 rounded-full">Overdue</span>
                                    </template>
                                    <span
                                        class="material-icons-round text-sm text-gray-300 dark:text-slate-600 group-hover:text-gray-400 dark:group-hover:text-slate-400 transition">chevron_right</span>
                                </div>
                            </a>
                        </template>
                    </div>
                </template>
            </x-card>
    </div>

        {{-- ── Upcoming Bills (below calendar in month view) ──────────────────── --}}
        <div x-show="view==='month'" x-cloak>
            <h2 class="text-sm font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wide mb-3">Upcoming this
                month</h2>
            <x-card flush class="overflow-hidden">
                <template x-if="listEvents.length === 0 && !loading">
                    <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-slate-500">
                        <span class="material-icons-round text-3xl mb-2">event_available</span>
                        <p class="text-sm font-medium">No bills this month</p>
                    </div>
                </template>
                <template x-for="(ev, i) in listEvents" :key="i">
                    <a :href="ev.url"
                       class="flex items-center gap-3 px-4 py-3.5 border-b border-gray-50 dark:border-slate-700/50 last:border-b-0 hover:bg-gray-50 dark:hover:bg-slate-700/40 transition group">
                        {{-- Color dot --}}
                        <div class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background:' + ev.color"></div>
                        {{-- Date badge --}}
                        <div class="w-14 shrink-0 text-center">
                            <div class="text-xs font-bold text-gray-500 dark:text-slate-400"
                                 x-text="formatShortDate(ev.start)"></div>
                        </div>
                        {{-- Name --}}
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white truncate"
                                 x-text="ev.title.replace('• ','')"></div>
                            <div x-show="ev.extendedProps?.provider"
                                 class="text-xs text-gray-400 dark:text-slate-500 truncate"
                                 x-text="ev.extendedProps?.provider"></div>
                        </div>
                        {{-- Amount + status --}}
                        <div class="flex items-center gap-2 shrink-0">
                        <span class="text-sm font-bold" :class="{
                            'text-emerald-600 dark:text-emerald-400': ev.extendedProps?.paid,
                            'text-red-500 dark:text-red-400': ev.extendedProps?.overdue,
                            'text-orange-500 dark:text-orange-400': ev.extendedProps?.soon && !ev.extendedProps?.paid,
                            'text-gray-700 dark:text-slate-300': !ev.extendedProps?.paid && !ev.extendedProps?.overdue && !ev.extendedProps?.soon
                        }" x-text="ev.extendedProps?.amount ?? ''"></span>
                            <span
                                class="material-icons-round text-sm text-gray-300 dark:text-slate-600 group-hover:text-gray-400 dark:group-hover:text-slate-400 transition">chevron_right</span>
                        </div>
                    </a>
                </template>
                <template x-if="loading">
                    <div class="flex items-center justify-center py-10">
                        <span class="material-icons-round text-indigo-400 animate-spin text-2xl">refresh</span>
                    </div>
                </template>
            </x-card>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function calendarApp() {
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

                // Returns all days in the grid (including leading/trailing blank days to start on Monday)
                get calendarCells() {
                    const year = this.year, month = this.month;
                    const firstDay = new Date(year, month, 1);
                    const lastDay = new Date(year, month + 1, 0);

                    // Monday=0 offset
                    let startDow = firstDay.getDay(); // 0=Sun
                    startDow = startDow === 0 ? 6 : startDow - 1; // shift so Mon=0

                    const cells = [];

                    // Leading days from previous month
                    for (let i = startDow - 1; i >= 0; i--) {
                        const d = new Date(year, month, -i);
                        cells.push({
                            day: d.getDate(),
                            date: this.dateStr(d),
                            currentMonth: false,
                            isToday: false,
                            events: []
                        });
                    }

                    // Days of current month
                    for (let d = 1; d <= lastDay.getDate(); d++) {
                        const date = new Date(year, month, d);
                        const ds = this.dateStr(date);
                        const td = this.dateStr(this.today);
                        const dayEvents = this.events.filter(e => e.start === ds);

                        // Aggregate status flags for the day
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
                            count: dayEvents.length,
                        });
                    }

                    // Trailing days to fill last row
                    const remaining = 7 - (cells.length % 7);
                    if (remaining < 7) {
                        for (let i = 1; i <= remaining; i++) {
                            const d = new Date(year, month + 1, i);
                            cells.push({
                                day: d.getDate(),
                                date: this.dateStr(d),
                                currentMonth: false,
                                isToday: false,
                                events: []
                            });
                        }
                    }

                    return cells;
                },

                // Sorted flat list of events for the current month
                get listEvents() {
                    return this.events
                        .filter(e => {
                            const [y, m] = e.start.split('-').map(Number);
                            return y === this.year && m === this.month + 1;
                        })
                        .sort((a, b) => a.start.localeCompare(b.start));
                },

                // Grouped by date for list view
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
        }
    </script>
@endpush

