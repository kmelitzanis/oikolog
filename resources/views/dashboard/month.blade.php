@extends('layouts.app')
@section('title', __('messages.month_overview'))

{{--
    Month overview — a build of mockup 2b: the month as a countdown line.

    Designed phone-first (it is reached by tapping the dashboard hero), but it
    reads correctly at any width — the content simply centres in a phone-width
    column rather than stretching across the desktop canvas.
--}}

@section('content')

    @php
        $currency = auth()->user()->currency_code;
        $allClear = $attention->isEmpty();
    @endphp

    <div class="max-w-[520px] mx-auto">

        {{-- Header --}}
        <div class="flex items-center gap-3 mb-[18px]">
            <a href="{{ route('dashboard') }}"
               class="w-[38px] h-[38px] rounded-[13px] shrink-0 flex items-center justify-center border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-500 dark:text-slate-300 transition hover:bg-gray-50 dark:hover:bg-slate-700">
                <span class="material-icons-round text-lg">chevron_left</span>
            </a>
            <div class="flex-1 min-w-0">
                <div class="text-[0.66rem] font-bold uppercase tracking-[0.09em] text-gray-400 dark:text-slate-500">
                    {{ __('messages.day_x_of_y', ['day' => $month['day'], 'days' => $month['days']]) }}
                </div>
                <div class="text-[1.3rem] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white mt-0.5">
                    {{ $allClear ? __('messages.clear_until_next') : __('messages.needs_attention') }}
                </div>
            </div>
        </div>

        {{-- Countdown line --}}
        <div class="rounded-[26px] border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 px-[18px] pt-5 pb-4 mb-3.5">
            <div class="flex items-end justify-between gap-4 mb-4">
                <div class="min-w-0">
                    <div class="text-[0.7rem] text-gray-400 dark:text-slate-500">{{ __('messages.left_to_pay_label') }}</div>
                    <div class="text-[2.3rem] leading-[1.1] font-extrabold tracking-[-0.03em] text-gray-900 dark:text-white">
                        {{ $currency }} {{ number_format($month['outstanding'], 2) }}
                    </div>
                </div>
                <div class="text-right shrink-0 whitespace-nowrap">
                    <div class="text-[0.7rem] text-gray-400 dark:text-slate-500">
                        {{ __('messages.of_amount', ['amount' => $currency . ' ' . number_format($month['load'], 2)]) }}
                    </div>
                    <div class="text-base font-bold text-emerald-500">
                        {{ __('messages.pct_done', ['pct' => $month['pct']]) }}
                    </div>
                </div>
            </div>

            {{-- The line: paid progress in amber, today's position marked on it --}}
            <div class="relative h-11">
                <div class="absolute left-0 right-0 top-[19px] h-1.5 rounded-full bg-gray-100 dark:bg-slate-700"></div>
                <div class="absolute left-0 top-[19px] h-1.5 rounded-full bg-linear-to-r from-amber-500 to-amber-700 transition-[width] duration-[400ms] ease-out"
                     style="width: {{ $month['pct'] }}%"></div>
                <div class="absolute top-2 w-7 h-7 rounded-full border-[3px] border-white dark:border-slate-800 flex items-center justify-center transition-[left] duration-[400ms] ease-out
                            {{ $allClear ? 'bg-emerald-500' : 'bg-amber-500' }}"
                     style="left: calc({{ $month['day_pct'] }}% - 14px)">
                    <span class="material-icons-round text-slate-900" style="font-size:15px">
                        {{ $allClear ? 'check' : 'schedule' }}
                    </span>
                </div>
            </div>
            <div class="flex justify-between text-[0.62rem] font-semibold text-gray-400 dark:text-slate-600 mt-0.5">
                <span>{{ now()->startOfMonth()->translatedFormat('j M') }}</span>
                <span>{{ __('messages.today') }}</span>
                <span>{{ now()->endOfMonth()->translatedFormat('j M') }}</span>
            </div>
        </div>

        {{-- What needs paying --}}
        @forelse($attention as $bill)
            @php
                $isOverdue = $bill->isOverdue();
                $daysUntil = (int) $bill->daysUntilDue();
                $accent    = $isOverdue ? '#ef4444' : '#f97316';
            @endphp
            <div class="flex items-center gap-3 rounded-[22px] px-4 py-[15px] mb-2.5"
                 style="background: {{ $accent }}0f; border: 1px solid {{ $accent }}33;">
                <div class="w-10 h-10 rounded-[13px] shrink-0 flex items-center justify-center"
                     style="background: {{ $accent }}22; color: {{ $accent }};">
                    <span class="material-icons-round text-lg">{{ $bill->category?->icon ?? 'receipt' }}</span>
                </div>
                <a href="{{ route('bills.show', $bill) }}" class="flex-1 min-w-0">
                    <div class="text-[0.93rem] font-semibold text-gray-900 dark:text-white truncate">{{ $bill->name }}</div>
                    <div class="text-[0.74rem] mt-0.5" style="color: {{ $accent }}">
                        @if($isOverdue)
                            {{ __('messages.overdue_by', ['days' => abs($daysUntil)]) }}
                        @elseif($daysUntil === 0)
                            {{ __('messages.due_today') }}
                        @else
                            {{ __('messages.in_days', ['days' => $daysUntil]) }}
                        @endif
                    </div>
                </a>
                <div class="text-[1.05rem] font-extrabold text-gray-900 dark:text-white shrink-0">
                    {{ $currency }} {{ number_format($bill->amount, 2) }}
                </div>
            </div>
        @empty
            <div class="rounded-[22px] border border-emerald-500/[0.28] bg-emerald-500/10 p-[18px] text-center mb-[18px]">
                <div class="text-[0.98rem] font-bold text-gray-900 dark:text-white">{{ __('messages.month_closed_clear') }}</div>
                <div class="text-[0.76rem] text-gray-500 dark:text-slate-400 mt-0.5">{{ __('messages.nothing_overdue') }}</div>
            </div>
        @endforelse

        @if($attention->isNotEmpty())
            <a href="{{ route('bills.index', ['status' => 'overdue']) }}"
               class="w-full h-[52px] rounded-[17px] bg-amber-500 hover:bg-amber-400 text-slate-900 text-[0.98rem] font-bold flex items-center justify-center transition my-1 mb-[18px] shadow-[0_8px_22px_rgba(245,158,11,0.35)]">
                {{ __('messages.record_payment') }}
            </a>
        @endif

        {{-- Then, nothing until --}}
        <div class="text-[0.62rem] font-bold uppercase tracking-[0.09em] text-gray-400 dark:text-slate-500 mb-2.5">
            {{ __('messages.then_nothing_until') }}
        </div>
        <div class="rounded-[22px] border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 px-[18px] py-1">
            @forelse($later as $bill)
                <a href="{{ route('bills.show', $bill) }}"
                   class="flex items-center gap-3 py-3 {{ $loop->first ? '' : 'border-t border-gray-100 dark:border-slate-700/60' }}">
                    <span class="w-[52px] shrink-0 text-[0.84rem] font-extrabold text-amber-600 dark:text-amber-400">
                        {{ $bill->next_due_date->translatedFormat('j M') }}
                    </span>
                    <span class="flex-1 min-w-0 text-[0.86rem] font-semibold text-gray-900 dark:text-white truncate">{{ $bill->name }}</span>
                    <span class="text-[0.86rem] font-bold text-gray-600 dark:text-slate-300 shrink-0">
                        {{ $currency }} {{ number_format($bill->amount, 2) }}
                    </span>
                </a>
            @empty
                <p class="py-6 text-center text-sm text-gray-400 dark:text-slate-500">{{ __('messages.nothing_later') }}</p>
            @endforelse
        </div>
    </div>

@endsection
