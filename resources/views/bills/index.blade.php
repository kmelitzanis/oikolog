@extends('layouts.app')
@section('title', __('messages.bills'))

@section('content')

    <div class="flex items-center justify-between mb-4 gap-4 flex-wrap"
         x-data="billsPageCal()"
         x-init="init()">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ __('messages.bills') }}</h1>
        <div class="flex items-center gap-2">
            <button type="button"
                    @click="calOpen = !calOpen"
                    :class="calOpen ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 border rounded-xl px-4 py-2.5 text-sm font-medium transition">
                <span class="material-icons-round text-base">calendar_month</span>
                <span x-text="calOpen ? 'Hide Calendar' : 'Show Calendar'"></span>
            </button>
            <a href="{{ route('bills.create') }}"
               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                <span class="material-icons-round text-lg">add</span> {{ __('messages.add_bill') }}
            </a>
        </div>

        {{-- Inline Calendar Panel --}}
        <div x-show="calOpen" x-cloak
             x-transition:enter="transition duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="w-full mt-2">
            <div
                class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-sm p-4 sm:p-5">
                {{-- Calendar toolbar --}}
                <div
                    class="flex flex-col gap-3 border-b border-gray-100 dark:border-slate-700 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <button @click="prevMonth()"
                                class="w-8 h-8 flex items-center justify-center rounded-2xl bg-indigo-500 hover:bg-indigo-600 text-white transition">
                            <span class="material-icons-round text-sm">chevron_left</span>
                        </button>
                        <button @click="nextMonth()"
                                class="w-8 h-8 flex items-center justify-center rounded-2xl bg-indigo-500 hover:bg-indigo-600 text-white transition">
                            <span class="material-icons-round text-sm">chevron_right</span>
                        </button>
                        <span class="ml-1 text-sm font-bold text-gray-800 dark:text-white"
                              x-text="monthName + ' ' + year"></span>
                    </div>
                    <div class="flex items-center justify-between gap-3 sm:justify-end">
                        <div
                            class="hidden lg:flex items-center gap-3 flex-wrap text-xs font-medium text-gray-400 dark:text-slate-500">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block w-2 h-2 rounded-full bg-red-500 shrink-0"></span> Overdue
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block w-2 h-2 rounded-full bg-orange-500 shrink-0"></span> Soon
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span> Paid
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block w-2 h-2 rounded-full bg-indigo-500 shrink-0"></span> Upcoming
                            </div>
                        </div>
                        <button @click="goToday()"
                                class="px-3 py-1.5 rounded-2xl bg-gray-100 dark:bg-slate-700 text-xs font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-600 transition">
                            Today
                        </button>
                    </div>
                </div>
                <div class="pt-4">
                    {{-- Day headers --}}
                    <div class="grid grid-cols-7 gap-1 mb-1">
                        <template x-for="d in ['M','T','W','T','F','S','S']" :key="d">
                            <div
                                class="text-center text-[10px] font-bold text-gray-400 dark:text-slate-500 py-1 uppercase tracking-[0.24em]"
                                x-text="d"></div>
                        </template>
                    </div>
                    {{-- Grid --}}
                    <div class="grid grid-cols-7 gap-1">
                        <template x-for="(cell, i) in calendarCells" :key="i">
                            <div
                                :class="{
                                    'opacity-25': !cell.currentMonth
                                }"
                                class="relative min-h-[60px] rounded-2xl py-2 px-1 flex flex-col items-center transition hover:bg-gray-50 dark:hover:bg-slate-700/40"
                            >
                                <div class="flex justify-center">
                                    <span
                                        :class="{
                                            'bg-indigo-500 text-white shadow-sm': cell.isToday,
                                            'text-gray-700 dark:text-slate-300': !cell.isToday
                                        }"
                                        class="w-7 h-7 flex items-center justify-center rounded-full text-[11px] font-bold"
                                        x-text="cell.day"
                                    ></span>
                                </div>
                                <div class="mt-1.5 flex items-center justify-center gap-1 min-h-[12px]">
                                    <template x-for="(ev, ei) in cell.events.slice(0, 3)" :key="ei">
                                        <a :href="ev.url"
                                           :title="ev.title.replace('• ','')"
                                           class="block w-1.5 h-1.5 rounded-full transition hover:scale-110"
                                           :style="'background:' + ev.color"></a>
                                    </template>
                                    <template x-if="cell.events.length > 3">
                                        <div class="text-[10px] font-semibold text-gray-400 dark:text-slate-500"
                                             x-text="'+' + (cell.events.length - 3)"></div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <template x-if="loading">
                    <div class="flex items-center justify-center pt-4">
                        <span class="material-icons-round text-indigo-400 animate-spin text-xl">refresh</span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('bills.index') }}"
          class="flex flex-wrap gap-3 mb-6" x-data>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('messages.search') }}…"
               class="flex-1 min-w-40 bg-white dark:bg-slate-800 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
        <select name="category_id" @change="$el.form.submit()"
                class="bg-white dark:bg-slate-800 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
            <option value="">{{ __('messages.filter_all') }} {{ __('messages.categories') }}</option>
            @foreach(\App\Models\Category::orderBy('name')->get() as $cat)
                <option
                    value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select name="frequency" @change="$el.form.submit()"
                class="bg-white dark:bg-slate-800 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
            <option value="">{{ __('messages.filter_all') }} {{ __('messages.frequency') }}</option>
            @foreach([
                'once'      => __('messages.once'),
                'weekly'    => __('messages.weekly'),
                'biweekly'  => __('messages.biweekly'),
                'monthly'   => __('messages.monthly'),
                'quarterly' => __('messages.quarterly'),
                'yearly'    => __('messages.yearly'),
            ] as $f => $fl)
                <option value="{{ $f }}" {{ request('frequency')===$f ? 'selected' : '' }}>{{ $fl }}</option>
            @endforeach
        </select>
        <select name="status" @change="$el.form.submit()"
                class="bg-white dark:bg-slate-800 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
            <option value="">{{ __('messages.filter_all') }}</option>
            <option
                value="active" {{ request('status')==='active'   ? 'selected' : '' }}>{{ __('messages.filter_active') }}</option>
            <option
                value="overdue" {{ request('status')==='overdue'  ? 'selected' : '' }}>{{ __('messages.filter_overdue') }}</option>
            <option value="inactive" {{ request('status')==='inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        <button type="submit"
                class="inline-flex items-center gap-1.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
            <span class="material-icons-round text-base">search</span> {{ __('messages.search') }}
        </button>
        @if(request()->hasAny(['search','category_id','frequency','status']))
            <a href="{{ route('bills.index') }}"
               class="inline-flex items-center gap-1.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                <span class="material-icons-round text-base">close</span> Clear
            </a>
        @endif
    </form>

    {{-- Bills --}}
    <div
        class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
        @forelse($bills as $bill)
            @php
                $isOverdue    = $bill->next_due_date && $bill->next_due_date->isPast() && $bill->is_active;
                $daysUntil    = $bill->next_due_date ? (int) now()->diffInDays($bill->next_due_date, false) : null;
                $isSoon       = !$isOverdue && $daysUntil !== null && $daysUntil <= 7 && $bill->is_active;
                $isUpcoming   = !$isOverdue && !$isSoon && $daysUntil !== null && $daysUntil > 7 && $daysUntil <= 60 && $bill->is_active;
                $lastPayment  = $bill->payments->first();
                $isPaid       = (bool)$bill->last_paid_date;
                $color        = $bill->category?->color_hex ?? '#6366F1';
                $rowClass     = $isOverdue ? 'bg-red-50 dark:bg-red-900/10' : ($isSoon ? 'bg-orange-50 dark:bg-orange-900/10' : ($isUpcoming ? 'bg-blue-50/40 dark:bg-blue-900/5' : ($isPaid ? 'bg-green-50 dark:bg-green-900/10' : 'bg-white dark:bg-slate-800')));
                $amountClass  = $isOverdue ? 'text-red-600 font-bold' : ($isSoon ? 'text-orange-600 font-bold' : ($isUpcoming ? 'text-blue-600 dark:text-blue-400 font-bold' : ($isPaid ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-gray-900 dark:text-white font-bold')));
            @endphp
            <div x-data="{ paid: {{ $lastPayment ? 'true' : 'false' }} }"
                 class="flex items-center gap-3 sm:gap-4 px-4 py-4 {{ !$loop->last ? 'border-b border-gray-50 dark:border-slate-700' : '' }} {{ $rowClass }} hover:brightness-95 transition cursor-pointer"
                 @click.self="window.location='{{ route('bills.show', $bill) }}'">

                {{-- Category icon --}}
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                     style="background:{{ $color }}1a;" @click="window.location='{{ route('bills.show', $bill) }}'">
                    @if($bill->provider && $bill->provider->logo_url)
                        <img src="{{ $bill->provider->logo_url }}" alt="{{ $bill->provider->name }}"
                             class="w-8 h-8 object-contain rounded-lg bg-white dark:bg-slate-700 border border-gray-100 dark:border-slate-600">
                    @else
                        <span class="material-icons-round text-xl"
                              style="color:{{ $color }}">{{ $bill->category?->icon ?? 'receipt' }}</span>
                    @endif
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0" @click="window.location='{{ route('bills.show', $bill) }}'">
                    <div class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 truncate">
                        {{ $bill->name }}
                        @if($bill->is_shared)
                            <span class="material-icons-round text-gray-300 dark:text-slate-500"
                                  style="font-size:14px;">group</span>
                        @endif
                    </div>
                    <div
                        class="text-xs mt-0.5 {{ $isOverdue ? 'text-red-500' : ($isSoon ? 'text-orange-500' : ($isUpcoming ? 'text-blue-500 dark:text-blue-400' : 'text-gray-400 dark:text-slate-500')) }}">
                        {{ $bill->category?->name ?? '—' }}
                        @if($bill->provider)
                            · <span
                                class="font-medium text-gray-500 dark:text-slate-400">{{ $bill->provider->name }}</span>
                        @endif
                        ·
                        @if($isOverdue)
                            {{ __('messages.overdue') }} {{ abs($daysUntil) }}d
                        @elseif($daysUntil === 0)
                            Due today
                        @elseif($daysUntil !== null)
                            In {{ $daysUntil }}d
                        @else
                            —
                        @endif
                    </div>
                </div>

                {{-- Badges (hidden on mobile) --}}
                <span
                    class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300">
                {{ ucfirst($bill->frequency) }}
            </span>

                @if($isOverdue)
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">{{ __('messages.overdue') }}</span>
                @elseif($isSoon)
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300">Soon</span>
                @elseif($isUpcoming)
                    <span
                        class="hidden md:inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                        <span class="material-icons-round" style="font-size:11px">schedule</span>
                        In {{ $daysUntil }}d
                    </span>
                @elseif($isPaid)
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ __('messages.paid') }}</span>
                @else
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300">{{ __('messages.filter_active') }}</span>
                @endif

                {{-- Amount --}}
                <div class="text-right shrink-0" @click="window.location='{{ route('bills.show', $bill) }}'">
                    @if($bill->cost_varies)
                        <div class="text-sm text-gray-400 dark:text-slate-500 font-medium italic">varies</div>
                    @else
                        <div
                            class="text-sm {{ $amountClass }}">{{ $bill->currency_code }} {{ number_format($bill->amount, 2) }}</div>
                        <div
                            class="text-xs text-gray-400 dark:text-slate-500">{{ number_format($bill->monthlyEquivalent(), 2) }}
                            /mo
                        </div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-1.5 shrink-0" @click.stop>

                    {{-- Pay --}}
                    <button type="button" x-show="!paid" x-cloak
                            title="{{ __('messages.mark_paid') }}"
                            @click="$dispatch('open-pay-modal', {
                                billName:       '{{ addslashes($bill->name) }}',
                                amount:         '{{ number_format($bill->amount, 2) }}',
                                currency:       '{{ $bill->currency_code }}',
                                payRoute:       '{{ route('bills.pay', $bill) }}',
                                costVaries:     {{ $bill->cost_varies ? 'true' : 'false' }},
                                lastPaidAmount: '{{ $bill->cost_varies && $bill->payments->first() ? number_format((float)$bill->payments->first()->amount, 2, '.', '') : '' }}'
                            })"
                            class="w-8 h-8 flex items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 transition">
                        <span class="material-icons-round text-base">check_circle</span>
                    </button>

                    {{-- Unpay --}}
                    <form method="POST" action="{{ route('bills.unpay', $bill) }}" x-show="paid" x-cloak>
                        @csrf @method('DELETE')
                        <button type="submit" title="{{ __('messages.undo_payment') }}"
                                @click="if(!confirm('{{ __('messages.undo_payment') }}?')) $event.preventDefault()"
                                class="w-8 h-8 flex items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-900/20 text-orange-500 dark:text-orange-400 hover:bg-orange-100 transition">
                            <span class="material-icons-round text-base">undo</span>
                        </button>
                    </form>

                    <a href="{{ route('bills.edit', $bill) }}" title="{{ __('messages.edit') }}"
                       class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-50 dark:bg-slate-700 text-gray-500 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-600 transition">
                        <span class="material-icons-round text-base">edit</span>
                    </a>

                    <form method="POST" action="{{ route('bills.destroy', $bill) }}">
                        @csrf @method('DELETE')
                        <button type="submit" title="{{ __('messages.delete') }}"
                                @click="if(!confirm('{{ addslashes(__('messages.confirm_delete')) }}')) $event.preventDefault()"
                                class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-50 dark:bg-slate-700 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 transition">
                            <span class="material-icons-round text-base">delete</span>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-slate-500">
                <span class="material-icons-round text-6xl mb-3">receipt_long</span>
                <div class="text-base font-semibold">{{ __('messages.no_bills') }}</div>
                <div class="text-sm mt-1">
                    @if(request()->hasAny(['search','frequency','status']))
                        Try adjusting your filters
                    @else
                        <a href="{{ route('bills.create') }}"
                           class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('messages.add_bill') }}</a>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    @if($bills->hasPages())
        <div class="mt-6">{{ $bills->appends(request()->query())->links() }}</div>
    @endif

@endsection

@push('scripts')
    <script>
        function billsPageCal() {
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
                        cells.push({
                            day: d.getDate(),
                            date: this.dateStr(d),
                            currentMonth: false,
                            isToday: false,
                            events: []
                        });
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
                        cells.push({
                            day: d.getDate(),
                            date: this.dateStr(d),
                            currentMonth: false,
                            isToday: false,
                            events: []
                        });
                    }
                    return cells;
                },

                dateStr(d) {
                    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                },
            };
        }
    </script>
@endpush


