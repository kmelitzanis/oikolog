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
                    :class="calOpen ? 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-600' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 border rounded-xl px-4 py-2.5 text-sm font-medium transition">
                <span class="material-icons-round text-base">calendar_month</span>
                <span x-text="calOpen ? 'Hide Calendar' : 'Show Calendar'"></span>
            </button>
            <a href="{{ route('bills.create') }}"
               class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-slate-900 text-sm font-semibold rounded-xl px-4 py-2.5 transition">
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
            <x-card flush class="p-4 sm:p-5">
                {{-- Calendar toolbar --}}
                <div
                    class="flex flex-col gap-3 border-b border-gray-100 dark:border-slate-700 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <button @click="prevMonth()"
                                class="w-8 h-8 flex items-center justify-center rounded-2xl bg-amber-500 hover:bg-amber-500 text-slate-900 transition">
                            <span class="material-icons-round text-sm">chevron_left</span>
                        </button>
                        <button @click="nextMonth()"
                                class="w-8 h-8 flex items-center justify-center rounded-2xl bg-amber-500 hover:bg-amber-500 text-slate-900 transition">
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
                                <span class="inline-block w-2 h-2 rounded-full bg-amber-500 shrink-0"></span> Upcoming
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
                        {{-- Keyed by index: 'T' and 'S' each appear twice, and
                             keying by the letter made Alpine drop duplicates. --}}
                        <template x-for="(d, i) in ['M','T','W','T','F','S','S']" :key="i">
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
                                            'bg-amber-500 text-slate-900 shadow-sm': cell.isToday,
                                            'text-gray-700 dark:text-slate-300': !cell.isToday
                                        }"
                                        class="w-7 h-7 flex items-center justify-center rounded-full text-[11px] font-bold"
                                        x-text="cell.day"
                                    ></span>
                                </div>
                                <div class="mt-1.5 flex items-center justify-center gap-1 min-h-[12px]">
                                    {{-- Show aggregated status indicators (overdue / soon / paid / upcoming) like the full calendar --}}
                                    <template x-if="cell.events.some(e => e.extendedProps?.overdue)">
                                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500 shrink-0"
                                              title="Overdue"></span>
                                    </template>
                                    <template
                                            x-if="cell.events.some(e => e.extendedProps?.soon && !e.extendedProps?.paid)">
                                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-orange-500 shrink-0"
                                              title="Due soon"></span>
                                    </template>
                                    <template x-if="cell.events.some(e => e.extendedProps?.paid)">
                                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"
                                              title="Paid"></span>
                                    </template>
                                    <template
                                            x-if="cell.events.some(e => !e.extendedProps?.paid && !e.extendedProps?.overdue && !e.extendedProps?.soon)">
                                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-500 shrink-0"
                                              title="Upcoming"></span>
                                    </template>
                                    {{-- Fallback: show count when many events --}}
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
                        <span class="material-icons-round text-amber-400 animate-spin text-xl">refresh</span>
                    </div>
                </template>
            </x-card>
        </div>
    </div>

    {{-- Status pills — the design's primary cut of the list. Each is a link so
         the current filter survives a reload and stays shareable; the selects
         below narrow further. --}}
    @php
        $statusPills = [
            ''           => [__('messages.filter_all'),      $billCounts['all']],
            'overdue'    => [__('messages.overdue'),         $billCounts['overdue']],
            'this_month' => [__('messages.this_month'),      $billCounts['this_month']],
            'shared'     => [__('messages.shared'),          $billCounts['shared']],
        ];
    @endphp
    <div class="flex flex-wrap items-center gap-2 mb-4">
        @foreach($statusPills as $value => [$label, $count])
            @php $isActive = (string) request('status') === (string) $value; @endphp
            <a href="{{ route('bills.index', array_merge(request()->except('status', 'page'), $value === '' ? [] : ['status' => $value])) }}"
               class="px-3.5 py-2 rounded-full text-[0.78rem] font-semibold transition
                      {{ $isActive
                          ? 'bg-amber-500 text-slate-900'
                          : 'bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700' }}">
                {{ $label }} · {{ $count }}
            </a>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('bills.index') }}"
          class="flex flex-wrap gap-3 mb-6" x-data>
        @if(request('status'))
            <input type="hidden" name="status" value="{{ request('status') }}">
        @endif
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('messages.search') }}…"
               class="flex-1 min-w-40 bg-white dark:bg-slate-800 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition">
        <select name="category_id" @change="$el.form.submit()"
                class="bg-white dark:bg-slate-800 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500 transition">
            <option value="">{{ __('messages.filter_all') }} {{ __('messages.categories') }}</option>
            @foreach(\App\Models\Category::orderBy('name')->get() as $cat)
                <option
                    value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select name="frequency" @change="$el.form.submit()"
                class="bg-white dark:bg-slate-800 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500 transition">
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

    {{-- Bills.
         From lg up this is the design's six-column table; below that the same
         markup collapses to the stacked row the phone mockup uses, with the
         column values folded into a meta line under the name. --}}
    @php
        $billGrid = 'lg:grid lg:grid-cols-[minmax(0,2.2fr)_1fr_1fr_1fr_0.9fr_150px] lg:gap-4 lg:items-center';
    @endphp
    <x-card flush class="overflow-hidden">
        <div class="{{ $billGrid }} hidden px-5 py-3 bg-gray-50 dark:bg-slate-900/50 text-[0.64rem] font-bold uppercase tracking-[0.07em] text-gray-400 dark:text-slate-500">
            <div>{{ __('messages.bill_name') }}</div>
            <div>{{ __('messages.category') }}</div>
            <div>{{ __('messages.frequency') }}</div>
            <div>{{ __('messages.next_date') }}</div>
            <div class="text-right">{{ __('messages.amount') }}</div>
            <div></div>
        </div>
        @forelse($bills as $bill)
            @php
                $isOverdue    = $bill->next_due_date && $bill->next_due_date->isPast() && $bill->is_active;
                $daysUntil    = $bill->next_due_date ? (int) now()->diffInDays($bill->next_due_date, false) : null;
                $isSoon       = !$isOverdue && $daysUntil !== null && $daysUntil <= 7 && $bill->is_active;
                $isUpcoming   = !$isOverdue && !$isSoon && $daysUntil !== null && $daysUntil > 7 && $daysUntil <= 60 && $bill->is_active;
                $lastPayment  = $bill->payments->first();
                $isPaid       = (bool)$bill->last_paid_date;
                // "Current cycle paid": for recurring bills the tick returns once
                // the next occurrence is due again (next_due in the past/today).
                $isRecurring       = $bill->frequency !== 'once';
                $currentCyclePaid  = (bool)$bill->last_paid_date
                    && (!$isRecurring || ($bill->next_due_date && $bill->next_due_date->isFuture()));
                $color        = $bill->category?->color_hex ?? '#f59e0b';
                $rowClass     = $isOverdue ? 'bg-red-50 dark:bg-red-900/10' : ($isSoon ? 'bg-orange-50 dark:bg-orange-900/10' : ($isUpcoming ? 'bg-blue-50/40 dark:bg-blue-900/5' : ($isPaid ? 'bg-green-50 dark:bg-green-900/10' : 'bg-white dark:bg-slate-800')));
                $amountClass  = $isOverdue ? 'text-red-600 font-bold' : ($isSoon ? 'text-orange-600 font-bold' : ($isUpcoming ? 'text-blue-600 dark:text-blue-400 font-bold' : ($isPaid ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-gray-900 dark:text-white font-bold')));
            @endphp
            <div x-data="{ paid: {{ $currentCyclePaid ? 'true' : 'false' }}, hasPayments: {{ $lastPayment ? 'true' : 'false' }} }"
                 class="flex items-center gap-3 sm:gap-4 {{ $billGrid }} px-4 lg:px-5 py-4 lg:py-3 border-t border-gray-50 dark:border-slate-700 {{ $rowClass }} hover:brightness-95 transition cursor-pointer"
                 @click.self="window.location='{{ route('bills.show', $bill) }}'">

                @php
                    $dueClass = $isOverdue ? 'text-red-500'
                        : ($isSoon ? 'text-orange-500'
                        : ($isUpcoming ? 'text-blue-500 dark:text-blue-400' : 'text-gray-400 dark:text-slate-500'));
                    $dueRelative = $isOverdue
                        ? __('messages.overdue_by', ['days' => abs($daysUntil)])
                        : ($daysUntil === 0
                            ? __('messages.due_today')
                            : ($daysUntil !== null ? __('messages.in_days', ['days' => $daysUntil]) : '—'));
                @endphp

                {{-- 1 · Bill: icon + name (the meta line only exists below lg,
                     where the columns to its right are hidden) --}}
                <div class="flex items-center gap-3 flex-1 min-w-0 lg:flex-none"
                     @click="window.location='{{ route('bills.show', $bill) }}'">
                    <div class="w-10 h-10 lg:w-9 lg:h-9 rounded-xl flex items-center justify-center shrink-0"
                         style="background:{{ $color }}1a;">
                        @if($bill->provider && $bill->provider->logo_url)
                            <img src="{{ $bill->provider->logo_url }}" alt="{{ $bill->provider->name }}"
                                 class="w-8 h-8 object-contain rounded-lg bg-white dark:bg-slate-700 border border-gray-100 dark:border-slate-600">
                        @else
                            <span class="material-icons-round text-xl"
                                  style="color:{{ $color }}">{{ $bill->category?->icon ?? 'receipt' }}</span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 truncate">
                            {{ $bill->name }}
                            @if($bill->is_shared)
                                <span class="material-icons-round text-gray-300 dark:text-slate-500"
                                      style="font-size:14px;">group</span>
                            @endif
                        </div>
                        {{-- below lg: the columns folded into one line --}}
                        <div class="lg:hidden text-xs mt-0.5 {{ $dueClass }}">
                            {{ $bill->category?->name ?? '—' }}
                            @if($bill->provider)
                                · <span class="font-medium text-gray-500 dark:text-slate-400">{{ $bill->provider->name }}</span>
                            @endif
                            · {{ $dueRelative }}
                        </div>
                        {{-- from lg: the monthly equivalent, as in the mockup --}}
                        @unless($bill->cost_varies)
                            <div class="hidden lg:block text-[0.7rem] text-gray-400 dark:text-slate-500 mt-px">
                                {{ number_format($bill->monthlyEquivalent(), 2) }}/mo
                            </div>
                        @endunless
                    </div>
                </div>

                {{-- 2 · Category --}}
                <div class="hidden lg:block text-sm font-medium text-gray-600 dark:text-slate-300 truncate">
                    {{ $bill->category?->name ?? '—' }}
                </div>

                {{-- 3 · Frequency — plain muted text, as in the mockup's bills
                     table; the amber pill is the income table's treatment. --}}
                <div class="hidden lg:block text-sm font-medium text-gray-500 dark:text-slate-400">
                    {{ __('messages.' . $bill->frequency) }}
                </div>

                {{-- 4 · Next due --}}
                <div class="hidden lg:block">
                    <div class="text-sm font-semibold {{ $dueClass }}">
                        {{ $bill->next_due_date?->translatedFormat('j M Y') ?? '—' }}
                    </div>
                    <div class="text-[0.68rem] text-gray-400 dark:text-slate-500 mt-px">{{ $dueRelative }}</div>
                </div>

                {{-- 5 · Amount --}}
                <div class="text-right shrink-0" @click="window.location='{{ route('bills.show', $bill) }}'">
                    @if($bill->cost_varies)
                        <div class="text-sm text-gray-400 dark:text-slate-500 font-medium italic">varies</div>
                    @else
                        <div class="text-sm {{ $amountClass }}">{{ $bill->currency_code }} {{ number_format($bill->amount, 2) }}</div>
                        {{-- the /mo line moved into the name cell from lg up --}}
                        <div class="lg:hidden text-xs text-gray-400 dark:text-slate-500">
                            {{ number_format($bill->monthlyEquivalent(), 2) }}/mo
                        </div>
                    @endif
                </div>

                {{-- 6 · Actions --}}
                <div class="flex items-center justify-end gap-1.5 shrink-0" @click.stop>

                    {{-- Pay --}}
                    <button type="button" x-show="!paid" x-cloak
                            title="{{ __('messages.mark_paid') }}"
                            @click="$dispatch('open-pay-modal', {
                                billName:       '{{ addslashes($bill->name) }}',
                                amount:         '{{ number_format($bill->amount, 2) }}',
                                currency:       '{{ $bill->currency_code }}',
                                payRoute:       '{{ route('bills.pay', $bill) }}',
                                costVaries:     {{ $bill->cost_varies ? 'true' : 'false' }},
                                defaultIncomeId: '{{ $bill->default_income_id }}',
                                lastPaidAmount: '{{ $bill->cost_varies && $bill->payments->first() ? number_format((float)$bill->payments->first()->amount, 2, '.', '') : '' }}'
                            })"
                            class="w-8 h-8 flex items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 transition">
                        <span class="material-icons-round text-base">check_circle</span>
                    </button>

                    {{-- Unpay (reverses the most recent payment) --}}
                    <form method="POST" action="{{ route('bills.unpay', $bill) }}" x-show="hasPayments" x-cloak>
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
                           class="text-amber-700 dark:text-amber-400 hover:underline">{{ __('messages.add_bill') }}</a>
                    @endif
                </div>
            </div>
        @endforelse
    </x-card>

    @if($bills->hasPages())
        <div class="mt-6">{{ $bills->appends(request()->query())->links() }}</div>
    @endif

@endsection

@push('scripts')
    {{-- Bills page scripts moved to resources/js/pages/bills.js --}}
@endpush


