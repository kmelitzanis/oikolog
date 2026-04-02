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
               class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                <span class="material-icons-round text-lg">add</span>
                {{ __('messages.add_income') }}
            </a>
            <a href="{{ route('bills.create') }}"
               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
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
                        <form method="POST" action="{{ route('bills.pay', $bill) }}" class="mt-1">
                            @csrf
                            <button type="submit"
                                    class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline bg-transparent border-0 cursor-pointer p-0">
                                ✓ Pay
                            </button>
                        </form>
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
            <div class="relative h-48">
                <canvas id="chart-monthly"></canvas>
            </div>
        </div>
        <div class="flex flex-col gap-4">
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Spending vs Income</h3>
                <div class="relative h-32">
                    <canvas id="chart-income-spend"></canvas>
                </div>
            </div>
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-5">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3">{{ __('messages.by_category') }}</h3>
                <div class="relative h-32">
                    <canvas id="chart-category"></canvas>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        (async function () {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const headers = {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'};

            const xsrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf[1]);

            try {
                const [sRes, rRes] = await Promise.all([
                    fetch('/api/bills/stats', {credentials: 'same-origin', headers}),
                    fetch('/api/bills/series?months=12', {credentials: 'same-origin', headers}),
                ]);
                if (!sRes.ok || !rRes.ok) throw new Error('API error');
                const stats = await sRes.json();
                const series = await rRes.json();
                const cur = stats.currency_code ?? 'EUR';
                const fmt = v => `${cur} ${Number(v).toFixed(2)}`;

                const monthlyCtx = document.getElementById('chart-monthly')?.getContext('2d');
                if (monthlyCtx) new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        labels: series.months, datasets: [
                            {
                                label: 'Spending',
                                data: series.spending,
                                borderColor: '#ef4444',
                                backgroundColor: 'rgba(239,68,68,.07)',
                                tension: .35,
                                fill: true,
                                pointRadius: 3
                            },
                            {
                                label: 'Income',
                                data: series.income,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16,185,129,.07)',
                                tension: .35,
                                fill: true,
                                pointRadius: 3
                            },
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {tooltip: {callbacks: {label: c => `${c.dataset.label}: ${fmt(c.parsed.y)}`}}},
                        scales: {y: {beginAtZero: true, ticks: {callback: v => fmt(v)}}}
                    }
                });

                const isCtx = document.getElementById('chart-income-spend')?.getContext('2d');
                if (isCtx) {
                    const ts = series.spending.reduce((a, b) => a + b, 0);
                    const ti = series.income.reduce((a, b) => a + b, 0);
                    new Chart(isCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Spending', 'Income'],
                            datasets: [{data: [ts, ti], backgroundColor: ['#ef4444', '#10b981'], borderWidth: 0}]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '65%',
                            plugins: {tooltip: {callbacks: {label: c => `${c.label}: ${fmt(c.parsed)}`}}}
                        }
                    });
                }

                const catCtx = document.getElementById('chart-category')?.getContext('2d');
                if (catCtx && stats.by_category) {
                    const entries = Object.entries(stats.by_category).slice(0, 8);
                    const palette = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#f97316', '#ec4899'];
                    new Chart(catCtx, {
                        type: 'doughnut',
                        data: {
                            labels: entries.map(e => e[0]),
                            datasets: [{data: entries.map(e => e[1]), backgroundColor: palette, borderWidth: 0}]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '55%',
                            plugins: {tooltip: {callbacks: {label: c => `${c.label}: ${fmt(c.parsed)}`}}}
                        }
                    });
                }
            } catch (e) {
                console.warn('Charts unavailable:', e.message);
            }
        })();
    </script>
@endpush

