@extends('layouts.app')
@section('title', __('messages.dashboard'))

{{--
    Dashboard — a strict build of mockup 3a.

    The page is exactly three blocks: header, hero + attention queue, and the
    three-up row. Sizes below are the mockup's own values (1.6rem title, 3rem
    net, 24px radii, 150px chart, 7px progress bar) rather than the nearest
    Tailwind step, so the layout matches the design at pixel level.

    Everything on the amber surface takes slate ink — amber-500 is far too
    light to carry white text.
--}}

@section('content')

    @php
        $currency    = auth()->user()->currency_code;
        $netPositive = $stats['monthly_net'] >= 0;
        // 700 · .62rem · .09em tracking — the mockup's section label.
        $sectionLabel = 'text-[0.62rem] font-bold uppercase tracking-[0.09em] text-gray-400 dark:text-slate-500';
        $panel = 'bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-[24px]';
    @endphp

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="flex items-end justify-between gap-5 mb-[22px] flex-wrap">
        <div>
            <div class="text-[1.6rem] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white leading-tight">
                {{ __('messages.hello') }}, {{ explode(' ', auth()->user()->name)[0] }} 👋
            </div>
            <div class="text-[0.82rem] text-gray-400 dark:text-slate-500 mt-[3px]">
                {{ now()->translatedFormat('l, j F Y') }}
            </div>
        </div>
        <div class="flex gap-[9px] shrink-0">
            {{-- secondary: outline --}}
            <a href="{{ route('income.create') }}"
               class="h-10 px-[15px] rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 text-[0.82rem] font-semibold whitespace-nowrap flex items-center gap-2 transition hover:bg-gray-50 dark:hover:bg-slate-700">
                <span class="material-icons-round text-base">add</span>{{ __('messages.add_income') }}
            </a>
            {{-- primary: amber --}}
            <a href="{{ route('bills.create') }}"
               class="h-10 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-[0.82rem] font-bold whitespace-nowrap flex items-center gap-2 transition shadow-[0_6px_18px_rgba(245,158,11,0.32)]">
                <span class="material-icons-round text-base">add</span>{{ __('messages.add_bill') }}
            </a>
        </div>
    </div>

    {{-- ── Hero + attention queue (1.15fr / 1fr) ──────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-[1.15fr_1fr] gap-[18px] mb-[18px] items-start">

        {{-- Hero — tapping it opens the month overview (mockup 2b) --}}
        <a href="{{ route('dashboard.month') }}"
           class="block rounded-[24px] bg-amber-500 px-[26px] pt-6 pb-[22px] shadow-[0_14px_34px_rgba(245,158,11,0.26)] transition hover:brightness-[1.03]">
            <div class="text-[0.68rem] font-semibold uppercase tracking-[0.09em] text-slate-900/70">
                {{ __('messages.net_this_month') }}
            </div>
            {{-- Type scale switches between the two mockups: the phone (2a)
                 uses 2.5rem / .9rem tiles, the desktop (3a) 3rem / 1.05rem. --}}
            <div class="text-[2.5rem] sm:text-[3rem] leading-[1.05] font-extrabold tracking-[-0.03em] text-slate-900 mt-1.5">
                {{ $netPositive ? '+' : '' }}{{ $currency }} {{ number_format($stats['monthly_net'], 2) }}
            </div>

            <div class="flex gap-2 sm:gap-2.5 mt-4 sm:mt-5">
                @foreach([
                    [__('messages.income_singular'), $stats['monthly_income']],
                    [__('messages.expenses'), $stats['monthly_total']],
                    [__('messages.yearly'),   $stats['yearly_total']],
                ] as [$tileLabel, $tileValue])
                    <div class="flex-1 min-w-0 bg-slate-900/10 rounded-[14px] sm:rounded-2xl px-[11px] py-[9px] sm:px-3.5 sm:py-3">
                        <div class="text-[0.66rem] sm:text-[0.7rem] text-slate-900/70 truncate">{{ $tileLabel }}</div>
                        {{-- no `truncate` here: a clipped "EUR 1…" is useless,
                             so a long amount wraps instead. --}}
                        <div class="text-[0.9rem] sm:text-[1.05rem] font-bold text-slate-900 mt-0.5 sm:mt-[3px] leading-tight">
                            {{ $currency }} {{ number_format($tileValue, 2) }}
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5">
                <div class="flex justify-between gap-3 text-[0.72rem] font-semibold text-slate-900/70">
                    <span>{{ __('messages.month_paid', ['percent' => $stats['month_paid_pct']]) }}</span>
                    <span>{{ __('messages.left_to_pay', ['amount' => $currency . ' ' . number_format($stats['month_outstanding'], 2)]) }}</span>
                </div>
                <div class="h-[7px] rounded-full bg-slate-900/[0.16] mt-[7px] overflow-hidden">
                    <div class="h-full rounded-full bg-slate-900 transition-[width] duration-[400ms] ease-out"
                         style="width: {{ $stats['month_paid_pct'] }}%"></div>
                </div>
            </div>
        </a>

        {{-- Needs attention --}}
        <div class="{{ $panel }} overflow-hidden">
            <div class="flex items-center justify-between px-5 pt-4 pb-2.5">
                <div class="text-base font-bold text-gray-900 dark:text-white">{{ __('messages.needs_attention') }}</div>
                @if($stats['overdue_count'] > 0)
                    <span class="text-[0.72rem] font-bold text-red-400">
                        {{ $stats['overdue_count'] }} {{ __('messages.overdue') }}
                    </span>
                @endif
            </div>

            @forelse($attention as $bill)
                @php
                    $isOverdue = $bill->isOverdue();
                    $daysUntil = (int) $bill->daysUntilDue();
                    $accent    = $isOverdue ? '#ef4444' : '#f97316';
                @endphp
                <div class="flex items-center gap-[13px] px-5 py-[13px] border-t border-gray-100 dark:border-slate-700/70 border-l-[3px]"
                     style="border-left-color: {{ $accent }}; background: {{ $accent }}0f;">
                    <div class="w-[38px] h-[38px] rounded-xl flex items-center justify-center shrink-0"
                         style="background: {{ $accent }}22; color: {{ $accent }};">
                        <span class="material-icons-round text-lg">{{ $bill->category?->icon ?? 'receipt' }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[0.9rem] font-semibold text-gray-900 dark:text-white truncate">{{ $bill->name }}</div>
                        <div class="text-[0.74rem] mt-0.5" style="color: {{ $accent }}">
                            @if($isOverdue)
                                {{ __('messages.overdue_by', ['days' => abs($daysUntil)]) }}
                            @elseif($daysUntil === 0)
                                {{ __('messages.due_today') }}
                            @else
                                {{ __('messages.in_days', ['days' => $daysUntil]) }}
                            @endif
                        </div>
                    </div>
                    <div class="text-base font-extrabold text-gray-900 dark:text-white shrink-0">
                        {{ $currency }} {{ number_format($bill->amount, 2) }}
                    </div>
                    <button type="button" x-data
                            @click="$dispatch('open-pay-modal', {
                                billName:   '{{ addslashes($bill->name) }}',
                                amount:     '{{ number_format($bill->amount, 2) }}',
                                currency:   '{{ $currency }}',
                                payRoute:   '{{ route('bills.pay', $bill) }}',
                                costVaries: {{ $bill->cost_varies ? 'true' : 'false' }},
                                defaultIncomeId: '{{ $bill->default_income_id }}'
                            })"
                            class="shrink-0 h-[34px] px-3 rounded-xl bg-emerald-500/[0.14] border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-[0.76rem] font-bold flex items-center gap-1.5 cursor-pointer transition hover:bg-emerald-500/25">
                        <span class="material-icons-round text-sm">check</span>
                        {{ __('messages.pay') }}
                    </button>
                </div>
            @empty
                <div class="border-t border-gray-100 dark:border-slate-700/70 px-5 py-[26px] text-center">
                    <div class="text-[0.95rem] font-bold text-gray-900 dark:text-white">{{ __('messages.all_paid') }}</div>
                    <div class="text-[0.78rem] text-gray-400 dark:text-slate-500 mt-[3px]">{{ __('messages.nothing_overdue') }}</div>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ── Three-up: next 30 days · last 6 months · by category ─────────
         No `items-start`: the mockup's grid stretches, so the three cards
         share a height regardless of how much each has to show. --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-[18px]">

        {{-- Next 30 days --}}
        <div class="{{ $panel }} px-5 py-[18px]">
            <div class="flex items-center justify-between mb-3.5">
                <div class="{{ $sectionLabel }}">{{ __('messages.next_30_days') }}</div>
                <a href="{{ route('bills.index') }}"
                   class="text-[0.72rem] font-bold text-amber-600 dark:text-amber-400 hover:underline">{{ __('messages.view_all') }}
                    →</a>
            </div>

            @forelse($upcoming as $bill)
                @php $daysUntil = $bill->next_due_date ? (int) $bill->daysUntilDue() : null; @endphp
                <div class="flex items-center gap-[11px] py-2.5 border-t border-gray-100 dark:border-slate-700/60">
                    <div class="w-[30px] h-[30px] rounded-lg flex items-center justify-center shrink-0 bg-amber-500/[0.13] text-amber-600 dark:text-amber-400">
                        <span class="material-icons-round text-base">{{ $bill->category?->icon ?? 'receipt' }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[0.84rem] font-semibold text-gray-900 dark:text-white truncate">{{ $bill->name }}</div>
                        <div class="text-[0.7rem] text-gray-400 dark:text-slate-500 mt-px">
                            @if($bill->isOverdue())
                                {{ __('messages.overdue_by', ['days' => abs($daysUntil)]) }}
                            @elseif($daysUntil === 0)
                                {{ __('messages.due_today') }}
                            @elseif($daysUntil !== null)
                                {{ __('messages.in_days', ['days' => $daysUntil]) }}
                            @endif
                        </div>
                    </div>
                    <div class="text-[0.84rem] font-bold text-gray-600 dark:text-slate-300 shrink-0">
                        {{ $currency }} {{ number_format($bill->amount, 2) }}
                    </div>
                </div>
            @empty
                <div class="border-t border-gray-100 dark:border-slate-700/60 py-8 text-center text-sm text-gray-400 dark:text-slate-500">
                    {{ __('messages.no_upcoming') }}
                </div>
            @endforelse
        </div>

        {{-- Last 6 months — paired bars, amber spend against emerald income.
             Plain divs, not Chart.js: at six points the canvas costs more than
             it explains, and the mockup draws it exactly this way. --}}
        @php
            $barMonths = array_slice($chartData['months'], -6);
            $barSpend  = array_slice($chartData['spending'], -6);
            $barIncome = array_slice($chartData['income'], -6);
            $barMax    = max(array_merge($barSpend, $barIncome)) ?: 1;
            $lastBar   = count($barMonths) - 1;
        @endphp
        <div class="{{ $panel }} px-5 py-[18px]">
            <div class="{{ $sectionLabel }} mb-3.5">{{ __('messages.last_6_months') }}</div>
            <div class="flex items-end gap-2.5 h-[150px] border-b border-gray-100 dark:border-slate-700 pb-0.5">
                @foreach($barMonths as $i => $m)
                    <div class="flex-1 flex items-end gap-[3px] h-full">
                        {{-- the current month is drawn a step darker --}}
                        <div class="flex-1 rounded-t {{ $i === $lastBar ? 'bg-amber-600' : 'bg-amber-500' }}"
                             style="height: {{ max(1, $barSpend[$i] / $barMax * 100) }}%"
                             title="{{ $m }} · {{ __('messages.expenses') }} {{ $currency }} {{ number_format($barSpend[$i], 2) }}"></div>
                        <div class="flex-1 rounded-t {{ $i === $lastBar ? 'bg-emerald-400' : 'bg-emerald-500' }}"
                             style="height: {{ max(1, $barIncome[$i] / $barMax * 100) }}%"
                             title="{{ $m }} · {{ __('messages.income') }} {{ $currency }} {{ number_format($barIncome[$i], 2) }}"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-2.5 mt-[7px]">
                @foreach($barMonths as $i => $m)
                    <div class="flex-1 text-center text-[0.64rem] {{ $i === $lastBar ? 'font-bold text-amber-500 dark:text-amber-300' : 'font-medium text-gray-400 dark:text-slate-600' }}">
                        {{ \Illuminate\Support\Str::before($m, ' ') }}
                    </div>
                @endforeach
            </div>
            <div class="flex gap-4 justify-center mt-3">
                <span class="flex items-center gap-1.5 text-[0.68rem] font-medium text-gray-500 dark:text-slate-400">
                    <span class="w-2 h-2 rounded-[2px] bg-amber-500"></span>{{ __('messages.expenses') }}
                </span>
                <span class="flex items-center gap-1.5 text-[0.68rem] font-medium text-gray-500 dark:text-slate-400">
                    <span class="w-2 h-2 rounded-[2px] bg-emerald-500"></span>{{ __('messages.income') }}
                </span>
            </div>
        </div>

        {{-- By category --}}
        @php
            $catTotal  = $byCategory->sum();
            // The mockup's own rotation, not each category's stored colour:
            // this panel ranks magnitudes, so the bars want a consistent ramp.
            $catColors = ['#f59e0b', '#3b82f6', '#d97706', '#34d399', '#f59e0b'];
        @endphp
        <div class="{{ $panel }} px-5 py-[18px]">
            <div class="{{ $sectionLabel }} mb-3">{{ __('messages.by_category') }}</div>
            @forelse($byCategory->take(5) as $name => $amount)
                <div class="{{ $loop->first ? 'mt-2.5' : 'mt-3' }}">
                    <div class="flex justify-between gap-3 text-[0.8rem] font-medium">
                        <span class="text-gray-600 dark:text-slate-300 truncate">{{ $name }}</span>
                        <span class="text-gray-900 dark:text-white shrink-0">{{ $currency }} {{ number_format($amount, 2) }}</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-gray-100 dark:bg-slate-700 mt-1.5 overflow-hidden">
                        <div class="h-full rounded-full transition-all"
                             style="width: {{ $catTotal > 0 ? ($amount / $catTotal * 100) : 0 }}%; background: {{ $catColors[$loop->index % count($catColors)] }}"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 dark:text-slate-500 text-center py-8">{{ __('messages.no_bills') }}</p>
            @endforelse
        </div>
    </div>

@endsection
