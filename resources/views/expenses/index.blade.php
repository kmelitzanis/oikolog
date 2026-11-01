@extends('layouts.app')
@section('title', __('messages.expenses_menu'))

{{--
    Everyday purchases, one month at a time: the total and where it went on
    top, the purchases themselves below, grouped by day.
--}}

@section('content')
    @php
        $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
        $symbol  = $symbols[auth()->user()->currency_code ?? 'EUR'] ?? (auth()->user()->currency_code ?? 'EUR');
        $money   = fn ($v) => $symbol . number_format((float) $v, 2);
        $prev    = $month->copy()->subMonth()->format('Y-m');
        $next    = $month->copy()->addMonth()->format('Y-m');
    @endphp

    <div class="max-w-5xl">
        <x-page-header :title="__('messages.expenses_menu')" :subtitle="__('messages.expenses_subtitle')">
            <x-slot:actions>
                <x-btn :href="route('expenses.create')" icon="add">{{ __('messages.expense_add') }}</x-btn>
            </x-slot:actions>
        </x-page-header>

        {{-- Month switcher --}}
        <div class="flex items-center justify-between gap-3 mb-5">
            <a href="{{ route('expenses.index', ['month' => $prev]) }}"
               class="w-10 h-10 rounded-xl flex items-center justify-center bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 text-gray-500 hover:text-amber-600 transition"
               aria-label="{{ __('messages.previous_month') }}">
                <span class="material-icons-round">chevron_left</span>
            </a>
            <div class="text-lg font-extrabold text-gray-900 dark:text-white capitalize">{{ $month->translatedFormat('F Y') }}</div>
            @if($isCurrent)
                <span class="w-10 h-10"></span>
            @else
                <a href="{{ route('expenses.index', ['month' => $next]) }}"
                   class="w-10 h-10 rounded-xl flex items-center justify-center bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 text-gray-500 hover:text-amber-600 transition"
                   aria-label="{{ __('messages.next_month') }}">
                    <span class="material-icons-round">chevron_right</span>
                </a>
            @endif
        </div>

        <div class="grid lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

            {{-- Purchases, newest first --}}
            <x-card flush class="overflow-hidden">
                @forelse($days as $day => $items)
                    <div class="px-5 py-2 bg-gray-50 dark:bg-slate-900/50 flex items-center justify-between text-xs font-semibold text-gray-500 dark:text-slate-400">
                        <span>{{ \Carbon\Carbon::parse($day)->translatedFormat('l j F') }}</span>
                        <span class="tabular-nums">{{ $money($items->sum('amount')) }}</span>
                    </div>
                    @foreach($items as $expense)
                        <div class="flex items-center gap-3 px-5 py-3.5 border-t border-gray-100 dark:border-slate-700/60 first:border-t-0">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 bg-amber-500/15 text-amber-600 dark:text-amber-400">
                                <span class="material-icons-round text-lg">{{ $expense->icon() }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $expense->name }}</div>
                                <div class="text-[0.7rem] text-gray-400 dark:text-slate-500 mt-px truncate">
                                    {{ __('messages.expense_category_' . $expense->category) }}
                                    @if($expense->account) · {{ $expense->account->name }} @endif
                                    @if($expense->is_shared && $expense->created_by !== auth()->id()) · {{ $expense->creator?->name }} @endif
                                </div>
                            </div>
                            <div class="text-sm font-bold text-gray-900 dark:text-white tabular-nums shrink-0">{{ $money($expense->amount) }}</div>
                            <x-icon-btn :href="route('expenses.edit', $expense)" icon="edit" title="{{ __('messages.edit') }}" />
                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}"
                                  onsubmit="return confirm({{ Illuminate\Support\Js::from(__('messages.confirm_delete')) }})">
                                @csrf @method('DELETE')
                                <x-icon-btn tone="danger" icon="delete" type="submit" title="{{ __('messages.delete') }}" />
                            </form>
                        </div>
                    @endforeach
                @empty
                    <x-empty-state icon="shopping_bag" :title="__('messages.expenses_none')" :text="__('messages.expenses_none_hint')">
                        <x-btn :href="route('expenses.create')" icon="add">{{ __('messages.expense_add') }}</x-btn>
                    </x-empty-state>
                @endforelse
            </x-card>

            {{-- The month's total, by category --}}
            <x-card>
                <div class="text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-gray-500 dark:text-slate-400">
                    {{ __('messages.expenses_month_total') }}
                </div>
                <div class="text-[2rem] leading-none font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white mt-1.5 tabular-nums">
                    {{ $money($total) }}
                </div>
                <div class="text-xs text-gray-400 dark:text-slate-500 mt-1">
                    {{ trans_choice('messages.expenses_count', $expenses->count(), ['count' => $expenses->count()]) }}
                </div>

                @if($byCategory->isNotEmpty())
                    <div class="mt-4 space-y-2.5">
                        @foreach($byCategory as $category => $row)
                            @php $pct = $total > 0 ? round($row['amount'] / $total * 100) : 0; @endphp
                            <div>
                                <div class="flex items-center justify-between text-sm gap-3">
                                    <span class="flex items-center gap-2 font-semibold text-gray-800 dark:text-white min-w-0">
                                        <span class="material-icons-round text-base text-gray-400">{{ \App\Models\Expense::CATEGORIES[$category] ?? 'more_horiz' }}</span>
                                        <span class="truncate">{{ __('messages.expense_category_' . $category) }}</span>
                                    </span>
                                    <span class="font-bold text-gray-900 dark:text-white tabular-nums shrink-0">{{ $money($row['amount']) }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden">
                                    <div class="h-full rounded-full bg-amber-500" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
    </div>
@endsection
