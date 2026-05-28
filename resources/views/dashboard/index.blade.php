@extends('layouts.app')
@section('title', __('messages.dashboard'))

@section('content')

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
                {{ __('messages.hello') }}, {{ explode(' ', auth()->user()->name)[0] }} 👋
            </h1>
            <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5">{{ now()->format('l, F j, Y') }}</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('income.create') }}"
               class="inline-flex flex-1 items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                <span class="material-icons-round text-lg">add</span>
                {{ __('messages.add_income') }}
            </a>
            <a href="{{ route('bills.create') }}"
               class="inline-flex flex-1 items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                <span class="material-icons-round text-lg">add</span>
                {{ __('messages.add_bill') }}
            </a>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        {{-- Monthly Spending --}}
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-2xl p-5 text-white">
            <div class="flex items-center gap-1.5 text-xs text-indigo-200 font-medium mb-2">
                <span
                    class="material-icons-round text-base">account_balance_wallet</span> {{ __('messages.monthly_spend') }}
            </div>
            <div class="text-2xl font-extrabold tracking-tight leading-none">
                {{ auth()->user()->currency_code }} {{ number_format($stats['monthly_total'], 2) }}
            </div>
            <div class="text-xs text-indigo-300 mt-1">
                {{ auth()->user()->currency_code }} {{ number_format($stats['yearly_total'], 2) }} {{ __('messages.per_year') }}
            </div>
        </div>

        {{-- Monthly Income --}}
        <div class="bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-2xl p-5 text-white">
            <div class="flex items-center gap-1.5 text-xs text-emerald-200 font-medium mb-2">
                <span class="material-icons-round text-base">trending_up</span> {{ __('messages.monthly_income') }}
            </div>
            <div class="text-2xl font-extrabold tracking-tight leading-none">
                {{ auth()->user()->currency_code }} {{ number_format($stats['monthly_income'], 2) }}
            </div>
            <div class="text-xs text-emerald-300 mt-1">
                {{ auth()->user()->currency_code }} {{ number_format($stats['yearly_income'], 2) }} {{ __('messages.per_year') }}
            </div>
        </div>

        {{-- Net Balance --}}
        @php $netPositive = $stats['monthly_net'] >= 0; @endphp
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-5">
            <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-slate-400 font-medium mb-2">
                <span
                    class="material-icons-round text-base {{ $netPositive ? 'text-emerald-500' : 'text-red-500' }}">{{ $netPositive ? 'savings' : 'trending_down' }}</span>
                {{ __('messages.net_per_month') }}
            </div>
            <div class="text-2xl font-extrabold {{ $netPositive ? 'text-emerald-600' : 'text-red-600' }}">
                {{ $netPositive ? '+' : '' }}{{ auth()->user()->currency_code }} {{ number_format($stats['monthly_net'], 2) }}
            </div>
            <div class="text-xs text-gray-400 dark:text-slate-500 mt-1">{{ __('messages.income_minus_exp') }}</div>
        </div>

        @foreach([
            ['icon'=>'warning', 'color'=>'text-red-500', 'value'=>$stats['overdue_count'], 'label'=>__('messages.overdue')],
        ] as $stat)
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-5 flex flex-col gap-1">
                <span class="material-icons-round {{ $stat['color'] }} text-2xl">{{ $stat['icon'] }}</span>
                <div class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $stat['value'] }}</div>
                <div class="text-sm text-gray-400 dark:text-slate-400 font-medium">{{ $stat['label'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Main content --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Upcoming Bills --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ __('messages.upcoming_bills') }}</h2>
                <a href="{{ route('bills.index') }}"
                   class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">{{ __('messages.view_all') }}
                    →</a>
            </div>

            @forelse($upcoming as $bill)
                @php
                    $isOverdue = $bill->next_due_date && $bill->next_due_date->isPast() && $bill->is_active;
                    $daysUntil = $bill->next_due_date ? (int) now()->diffInDays($bill->next_due_date, false) : null;
                    $isSoon    = !$isOverdue && $daysUntil !== null && $daysUntil <= 7;
                    $color     = $bill->category?->color_hex ?? '#6366F1';
                    $amountClass = $isOverdue ? 'text-red-600' : ($isSoon ? 'text-orange-500' : 'text-gray-900 dark:text-white');
                    $metaClass   = $isOverdue ? 'text-red-500' : ($isSoon ? 'text-orange-500' : 'text-gray-400 dark:text-slate-500');
                @endphp
                <div
                    class="flex items-center gap-3 py-3 {{ !$loop->last ? 'border-b border-gray-50 dark:border-slate-700' : '' }}">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                         style="background:{{ $color }}1a;">
                        <span class="material-icons-round text-xl"
                              style="color:{{ $color }}">{{ $bill->category?->icon ?? 'receipt' }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div
                            class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $bill->name }}</div>
                        <div class="text-xs {{ $metaClass }} mt-0.5">
                            @if($isOverdue)
                                {{ __('messages.overdue') }} {{ abs($daysUntil) }}d
                            @elseif($daysUntil === 0)
                                Due today
                            @elseif($daysUntil !== null)
                                In {{ $daysUntil }} day{{ $daysUntil !== 1 ? 's' : '' }}
                            @endif
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div
                            class="text-sm font-bold {{ $amountClass }}">{{ auth()->user()->currency_code }} {{ number_format($bill->amount, 2) }}</div>
                        <button type="button" x-data
                                @click="$dispatch('open-pay-modal', {
                                    billName:   '{{ addslashes($bill->name) }}',
                                    amount:     '{{ number_format($bill->amount, 2) }}',
                                    currency:   '{{ auth()->user()->currency_code }}',
                                    payRoute:   '{{ route('bills.pay', $bill) }}',
                                    costVaries: {{ $bill->cost_varies ? 'true' : 'false' }}
                                })"
                                class="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline bg-transparent border-0 cursor-pointer p-0">
                            ✓ Pay
                        </button>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-slate-500">
                    <span class="material-icons-round text-5xl mb-2">celebration</span>
                    <span class="text-sm">{{ __('messages.no_upcoming') }}</span>
                </div>
            @endforelse
        </div>

        {{-- Upcoming Income --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ __('messages.upcoming_income') }}</h2>
                <a href="{{ route('income.index') }}"
                   class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">{{ __('messages.view_all') }}
                    →</a>
            </div>

            @forelse($upcomingIncomes as $income)
                @php
                    $daysUntil = $income->daysUntilNext();
                    $metaClass = ($daysUntil !== null && $daysUntil <= 3) ? 'text-emerald-500 font-semibold' : 'text-gray-400 dark:text-slate-500';
                @endphp
                <div
                    class="flex items-center gap-3 py-3 {{ !$loop->last ? 'border-b border-gray-50 dark:border-slate-700' : '' }}">
                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-emerald-50 dark:bg-emerald-900/20">
                        <span class="material-icons-round text-xl text-emerald-600 dark:text-emerald-400">repeat</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div
                            class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $income->name }}</div>
                        <div class="text-xs {{ $metaClass }} mt-0.5">
                            {{ $income->source ?? $income->frequencyLabel() }} ·
                            @if($daysUntil === 0)
                                Today
                            @elseif($daysUntil !== null && $daysUntil > 0)
                                In {{ $daysUntil }}d
                            @elseif($daysUntil !== null && $daysUntil < 0)
                                {{ abs($daysUntil) }}d ago
                            @endif
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                            +{{ auth()->user()->currency_code }} {{ number_format($income->amount, 2) }}</div>
                        <div
                            class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ $income->frequencyLabel() }}</div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-slate-500">
                    <span class="material-icons-round text-5xl mb-2">savings</span>
                    <span class="text-sm">{{ __('messages.no_incomes') }}</span>
                    <a href="{{ route('income.create') }}"
                       class="mt-3 text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">+ {{ __('messages.add_income') }}</a>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Category breakdown + Analytics --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- By Category --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-white mb-5">{{ __('messages.by_category') }}</h2>
            @php $total = $byCategory->sum(); @endphp
            @forelse($byCategory->take(10) as $name => $amount)
                @php $pct = $total > 0 ? ($amount / $total * 100) : 0; @endphp
                <div class="mb-4">
                    <div class="flex justify-between mb-1">
                        <span class="text-sm text-gray-500 dark:text-slate-400 font-medium">{{ $name }}</span>
                        <span
                            class="text-sm text-gray-900 dark:text-white font-semibold">{{ number_format($amount, 2) }}</span>
                    </div>
                    <div class="bg-gray-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-indigo-500 h-full rounded-full transition-all" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 dark:text-slate-500 text-center py-8">{{ __('messages.no_bills') }}</p>
            @endforelse
        </div>

        {{-- Income by source --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-white mb-5">{{ __('messages.monthly_income') }}</h2>
            @php
                $incomeBySource = $upcomingIncomes->isEmpty()
                    ? collect()
                    : \App\Models\Income::forUser(auth()->user())->active()->get()
                        ->groupBy(fn($i) => $i->source ?: 'Other')
                        ->map(fn($g) => round($g->sum(fn($i) => $i->monthlyEquivalent()), 2))
                        ->sortDesc();
                $totalIncome = $incomeBySource->sum();
            @endphp
            @forelse($incomeBySource->take(8) as $srcName => $srcAmount)
                @php $pct = $totalIncome > 0 ? ($srcAmount / $totalIncome * 100) : 0; @endphp
                <div class="mb-4">
                    <div class="flex justify-between mb-1">
                        <span class="text-sm text-gray-500 dark:text-slate-400 font-medium">{{ $srcName }}</span>
                        <span class="text-sm text-emerald-600 dark:text-emerald-400 font-semibold">+{{ number_format($srcAmount, 2) }}/mo</span>
                    </div>
                    <div class="bg-gray-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-emerald-500 h-full rounded-full transition-all" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 dark:text-slate-500 text-center py-8">{{ __('messages.no_incomes') }}</p>
            @endforelse
        </div>
    </div>

    {{-- Analytics Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div
            class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Monthly Overview</h3>
                <span class="text-xs text-gray-400 dark:text-slate-500">Last 12 months</span>
            </div>
            <div class="relative h-48 overflow-hidden">
                <canvas id="chart-monthly" class="w-full h-full"
                        style="display:block;width:100% !important;height:100% !important;"></canvas>
            </div>
        </div>
        <div class="flex flex-col gap-4">
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Spending vs Income</h3>
                <div class="relative h-32 overflow-hidden">
                    <canvas id="chart-income-spend" class="w-full h-full"
                            style="display:block;width:100% !important;height:100% !important;"></canvas>
                </div>
            </div>
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3">{{ __('messages.by_category') }}</h3>
                <div class="relative h-32 overflow-hidden">
                    <canvas id="chart-category" class="w-full h-full"
                            style="display:block;width:100% !important;height:100% !important;"></canvas>
                </div>
            </div>
        </div>
    </div>

@endsection

<div id="dashboard-chart-data" data-chart='@json($chartData)'></div>

