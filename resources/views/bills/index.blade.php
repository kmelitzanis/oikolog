@extends('layouts.app')
@section('title', __('messages.bills'))

@push('head')
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/main.min.css' rel='stylesheet'/>
    <style>
        /* ── FullCalendar — project theme overrides ────────────────────────── */
        #bills-calendar {
            --fc-border-color: #e5e7eb;
            --fc-button-bg-color: #6366f1;
            --fc-button-active-bg-color: #4f46e5;
            --fc-button-hover-bg-color: #4f46e5;
            --fc-button-border-color: transparent;
            --fc-button-active-border-color: transparent;
            --fc-today-bg-color: rgba(99, 102, 241, .07);
            --fc-page-bg-color: transparent;
            --fc-event-border-color: transparent;
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
        }

        /* toolbar */
        #bills-calendar .fc-toolbar-title {
            font-size: 1rem;
            font-weight: 700;
            color: #111827;
        }

        #bills-calendar .fc-button {
            border-radius: .6rem !important;
            font-size: .72rem;
            font-weight: 600;
            padding: .3rem .75rem;
            text-transform: capitalize;
            box-shadow: none !important;
        }

        #bills-calendar .fc-button-group {
            gap: 3px;
        }

        /* day-header row */
        #bills-calendar .fc-col-header-cell-cushion {
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6b7280;
            text-decoration: none;
        }

        /* day numbers */
        #bills-calendar .fc-daygrid-day-number {
            font-size: .75rem;
            font-weight: 600;
            color: #374151;
            text-decoration: none;
        }

        #bills-calendar .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
            background: #6366f1;
            color: #fff;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* events */
        #bills-calendar .fc-daygrid-event {
            border-radius: .45rem;
            font-size: .7rem;
            font-weight: 600;
            padding: 1px 5px;
            margin-bottom: 2px;
            border: none;
        }

        #bills-calendar .fc-event-title {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* list view */
        #bills-calendar .fc-list-event-title a {
            text-decoration: none;
            font-weight: 600;
            font-size: .82rem;
        }

        #bills-calendar .fc-list-day-cushion {
            font-size: .72rem;
            font-weight: 700;
            background: #f1f5f9;
        }

        #bills-calendar .fc-list-event:hover td {
            background: #f8fafc;
        }

        /* ── Dark mode ─────────────────────────────────────────────────────── */
        .dark #bills-calendar {
            --fc-border-color: #334155;
            --fc-today-bg-color: rgba(99, 102, 241, .12);
            --fc-neutral-bg-color: #1e293b;
        }

        .dark #bills-calendar .fc-toolbar-title {
            color: #f1f5f9;
        }

        .dark #bills-calendar .fc-col-header-cell-cushion {
            color: #94a3b8;
        }

        .dark #bills-calendar .fc-daygrid-day-number {
            color: #cbd5e1;
        }

        .dark #bills-calendar .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
            background: #6366f1;
            color: #fff;
        }

        .dark #bills-calendar .fc-daygrid-day {
            background: transparent;
        }

        .dark #bills-calendar .fc-scrollgrid {
            border-color: #334155;
        }

        .dark #bills-calendar .fc-scrollgrid td,
        .dark #bills-calendar .fc-scrollgrid th {
            border-color: #334155;
        }

        .dark #bills-calendar .fc-list-day-cushion {
            background: #1e293b;
            color: #94a3b8;
        }

        .dark #bills-calendar .fc-list-event:hover td {
            background: #1e293b;
        }

        .dark #bills-calendar .fc-list-event-title a {
            color: #e2e8f0;
        }

        .dark #bills-calendar .fc-list-event-dot {
            border-color: currentColor;
        }

        .dark #bills-calendar .fc-list-empty {
            color: #94a3b8;
            background: transparent;
        }

        .dark #bills-calendar .fc-theme-standard td,
        .dark #bills-calendar .fc-theme-standard th {
            border-color: #334155;
        }
    </style>
@endpush

@section('content')

    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap"
         x-data="{ calOpen: false }"
         x-effect="if (calOpen) $nextTick(() => window.initBillsCalendar && window.initBillsCalendar())">
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
        <div x-show="calOpen"
             x-transition:enter="transition duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             x-cloak
             class="w-full mt-2">
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-5">
                {{-- Legend --}}
                <div class="flex items-center gap-4 mb-4">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-slate-400 font-medium">
                        <span class="inline-block w-3 h-3 rounded-full bg-indigo-500"></span> Bills
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-slate-400 font-medium">
                        <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span> Overdue
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-slate-400 font-medium">
                        <span class="inline-block w-3 h-3 rounded-full bg-emerald-500"></span> Income
                    </div>
                </div>
                <div id="bills-calendar"></div>
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
                $isOverdue  = $bill->next_due_date && $bill->next_due_date->isPast() && $bill->is_active;
                $daysUntil  = $bill->next_due_date ? (int) now()->diffInDays($bill->next_due_date, false) : null;
                $isSoon     = !$isOverdue && $daysUntil !== null && $daysUntil <= 7 && $bill->is_active;
                $lastPayment = $bill->payments->first();
                $isPaid     = (bool)$bill->last_paid_date;
                $color      = $bill->category?->color_hex ?? '#6366F1';
                $rowClass   = $isOverdue ? 'bg-red-50 dark:bg-red-900/10' : ($isSoon ? 'bg-orange-50 dark:bg-orange-900/10' : ($isPaid ? 'bg-green-50 dark:bg-green-900/10' : 'bg-white dark:bg-slate-800'));
                $amountClass = $isOverdue ? 'text-red-600 font-bold' : ($isSoon ? 'text-orange-600 font-bold' : ($isPaid ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-gray-900 dark:text-white font-bold'));
            @endphp
            <div x-data="{ paid: {{ $lastPayment ? 'true' : 'false' }} }"
                 class="flex items-center gap-3 sm:gap-4 px-4 py-4 {{ !$loop->last ? 'border-b border-gray-50 dark:border-slate-700' : '' }} {{ $rowClass }} hover:brightness-95 transition cursor-pointer"
                 @click.self="window.location='{{ route('bills.show', $bill) }}'">

                {{-- Category icon --}}
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                     style="background:{{ $color }}1a;" @click="window.location='{{ route('bills.show', $bill) }}'">
                    <span class="material-icons-round text-xl"
                          style="color:{{ $color }}">{{ $bill->category?->icon ?? 'receipt' }}</span>
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
                        class="text-xs mt-0.5 {{ $isOverdue ? 'text-red-500' : ($isSoon ? 'text-orange-500' : 'text-gray-400 dark:text-slate-500') }}">
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
                @elseif($isPaid)
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ __('messages.paid') }}</span>
                @else
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300">{{ __('messages.filter_active') }}</span>
                @endif

                {{-- Amount --}}
                <div class="text-right shrink-0" @click="window.location='{{ route('bills.show', $bill) }}'">
                    <div
                        class="text-sm {{ $amountClass }}">{{ $bill->currency_code }} {{ number_format($bill->amount, 2) }}</div>
                    <div
                        class="text-xs text-gray-400 dark:text-slate-500">{{ number_format($bill->monthlyEquivalent(), 2) }}
                        /mo
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-1.5 shrink-0" @click.stop>

                    {{-- Pay --}}
                    <form method="POST" action="{{ route('bills.pay', $bill) }}" x-show="!paid">
                        @csrf
                        <button type="submit" title="{{ __('messages.mark_paid') }}"
                                class="w-8 h-8 flex items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 transition">
                            <span class="material-icons-round text-base">check_circle</span>
                        </button>
                    </form>

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
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/index.global.min.js'></script>
    <script>
        (function () {
            let cal = null;

            window.initBillsCalendar = function () {
                const el = document.getElementById('bills-calendar');
                if (!el) return;

                if (cal) {
                    cal.updateSize();
                    return;
                }

                cal = new FullCalendar.Calendar(el, {
                    initialView: 'dayGridMonth',
                    height: 'auto',
                    firstDay: 1,               // Monday first
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,listMonth'
                    },
                    buttonText: {today: 'Today', month: 'Month', list: 'List'},
                    events: {
                        url: '{{ route('bills.events') }}',
                        method: 'GET',
                        failure: function () {
                            console.error('Calendar events failed to load');
                        }
                    },
                    // Custom event pill rendering
                    eventContent: function (arg) {
                        const type = arg.event.extendedProps.type;      // 'bill' | 'income'
                        const amount = arg.event.extendedProps.amount ?? '';
                        const dot = type === 'income'
                            ? '<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;opacity:.85;margin-right:4px;flex-shrink:0;vertical-align:middle"></span>'
                            : '<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;opacity:.85;margin-right:4px;flex-shrink:0;vertical-align:middle"></span>';

                        return {
                            html: `<div style="display:flex;align-items:center;gap:2px;padding:1px 4px;overflow:hidden">
                                       ${dot}
                                       <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1">${arg.event.title}</span>
                                       <span style="opacity:.7;font-size:.65rem;flex-shrink:0;margin-left:3px">${amount}</span>
                                   </div>`
                        };
                    },
                    eventClick: function (info) {
                        if (info.event.url) {
                            info.jsEvent.preventDefault();
                            window.location = info.event.url;
                        }
                    },
                    // Tooltip on hover via title attr
                    eventDidMount: function (info) {
                        const p = info.event.extendedProps;
                        info.el.title = (p.type === 'bill' ? '📄 Bill' : '💰 Income')
                            + ': ' + info.event.title
                            + (p.amount ? ' — ' + p.amount : '')
                            + (p.overdue ? ' (overdue)' : '');
                    }
                });

                cal.render();
            };
        })();
    </script>
@endpush
