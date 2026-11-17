@extends('layouts.app')
@section('title', __('messages.reports'))

@section('content')
    @php
        $money = fn ($v) => $currency . ' ' . number_format($v, 2);
        $months = collect($monthly);
        $monthLabels = $months->map(fn ($m) => $m['month']->translatedFormat('M y'))->values()->all();
        $col = fn ($key) => $months->pluck($key)->values()->all();
        $sectionLabel = 'text-[0.68rem] font-bold uppercase tracking-[0.09em] text-gray-400 dark:text-slate-500';

        // The donut keeps the six largest categories and folds the rest.
        $topCats = $categories->take(6);
        $restCats = round($categories->slice(6)->sum('amount'), 2);
        $catLabels = $topCats->pluck('label')->all();
        $catValues = $topCats->pluck('amount')->all();
        if ($restCats > 0) {
            $catLabels[] = __('messages.expense_category_other') . ' (+' . $categories->slice(6)->count() . ')';
            $catValues[] = $restCats;
        }
    @endphp

    <x-page-header :title="__('messages.reports')" :subtitle="__('messages.reports_subtitle')">
        <x-slot:actions>
            <x-btn :href="route('reports.export', $report->query())" variant="outline" icon="download">
                {{ __('messages.reports_export') }}
            </x-btn>
        </x-slot:actions>
    </x-page-header>

    {{-- Period: the presets, or any two days. --}}
    @php
        $pill = 'shrink-0 h-9 px-4 rounded-full text-[0.8rem] font-semibold flex items-center gap-1.5 transition cursor-pointer';
        $pillOn = 'bg-amber-500 text-slate-900 shadow-[0_4px_14px_rgba(245,158,11,0.3)]';
        $pillOff = 'bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700';
        $isCustom = $report->period === 'custom';
    @endphp
    <div x-data="{ custom: @js($isCustom) }" class="mb-5">
        <nav aria-label="{{ __('messages.reports_period') }}" class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 [scrollbar-width:none]">
            @foreach($periods as $key => $label)
                <a href="{{ route('reports.index', ['period' => $key]) }}"
                   @if($key === $report->period) aria-current="page" @endif
                   class="{{ $pill }} {{ $key === $report->period ? $pillOn : $pillOff }}">
                    {{ $label }}
                </a>
            @endforeach
            <button type="button" @click="custom = !custom" :aria-expanded="custom"
                    class="{{ $pill }} {{ $isCustom ? $pillOn : $pillOff }}">
                <span class="material-icons-round text-base">date_range</span>
                {{ $isCustom ? $report->label() : __('messages.reports_custom') }}
            </button>
        </nav>

        <form x-show="custom" x-cloak method="GET" action="{{ route('reports.index') }}"
              class="mt-3 flex flex-wrap items-end gap-3 rounded-2xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 p-4">
            <input type="hidden" name="period" value="custom">
            <label class="flex-1 min-w-[9rem]">
                <span class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.reports_from') }}</span>
                <x-input type="date" name="from" required max="{{ now()->toDateString() }}"
                         value="{{ $report->from->toDateString() }}" />
            </label>
            <label class="flex-1 min-w-[9rem]">
                <span class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.reports_to') }}</span>
                <x-input type="date" name="to" required
                         value="{{ $report->to->toDateString() }}" />
            </label>
            <x-btn icon="check">{{ __('messages.reports_apply') }}</x-btn>
        </form>
    </div>

    {{-- Totals --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-stat-tile :label="__('messages.income')" :value="$money($totals['income'])" tone="success" />
        <x-stat-tile :label="__('messages.reports_spent')" :value="$money($totals['spent'])" tone="brand" />
        <x-stat-tile :label="__('messages.reports_net')" :value="$money($totals['net'])"
                     :tone="$totals['net'] < 0 ? 'danger' : 'success'"
                     :hint="$totals['savings_rate'] !== null && $totals['savings_rate'] > 0 ? __('messages.reports_savings_rate', ['percent' => $totals['savings_rate']]) : null" />
        <x-stat-tile :label="__('messages.reports_average')" :value="$money($totals['average'])" />
    </div>

    {{-- Income against spending --}}
    <x-card class="mb-5">
        <div class="flex items-baseline justify-between gap-3 mb-2">
            <h2 class="{{ $sectionLabel }}">{{ __('messages.reports_income_vs_spending') }}</h2>
            <span class="text-[0.7rem] text-gray-400 dark:text-slate-500">{{ __('messages.reports_income_note') }}</span>
        </div>
        <x-chart type="area" :height="300" :currency="$currency" :categories="$monthLabels"
                 :colors="['#10b981', '#f59e0b']"
                 :series="[
                    ['name' => __('messages.income'), 'data' => $col('income')],
                    ['name' => __('messages.reports_spent'), 'data' => $col('spent')],
                 ]" />
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-[1.6fr_1fr] gap-5 mb-5">
        <x-card>
            <h2 class="{{ $sectionLabel }} mb-2">{{ __('messages.reports_spending_by_month') }}</h2>
            <x-chart type="bar" stacked :height="300" :currency="$currency" :categories="$monthLabels"
                     :colors="['#f59e0b', '#3b82f6', '#8b5cf6']"
                     :series="[
                        ['name' => __('messages.bills'), 'data' => $col('bills')],
                        ['name' => __('messages.expenses_menu'), 'data' => $col('purchases')],
                        ['name' => __('messages.vehicles'), 'data' => $col('vehicles')],
                     ]" />
        </x-card>
        <x-card>
            <h2 class="{{ $sectionLabel }} mb-2">{{ __('messages.reports_by_category') }}</h2>
            <x-chart type="donut" :height="300" :currency="$currency"
                     :labels="$catLabels" :series="$catValues" :total-label="__('messages.reports_total')" />
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1.6fr_1fr] gap-5 mb-5">
        <x-card>
            <h2 class="{{ $sectionLabel }} mb-2">{{ __('messages.reports_top_bills') }}</h2>
            <x-chart type="bar" horizontal distributed :legend="false" :currency="$currency"
                     :height="max(220, $topBills->count() * 42)"
                     :categories="$topBills->keys()->all()"
                     :series="[['name' => __('messages.bills'), 'data' => $topBills->values()->all()]]" />
        </x-card>
        <x-card>
            <h2 class="{{ $sectionLabel }} mb-2">{{ __('messages.reports_income_sources') }}</h2>
            <x-chart type="donut" :height="300" :currency="$currency"
                     :colors="['#10b981', '#14b8a6', '#3b82f6', '#8b5cf6', '#f59e0b', '#64748b']"
                     :labels="$incomeSources->keys()->all()" :series="$incomeSources->values()->all()"
                     :total-label="__('messages.reports_total')" />
        </x-card>
    </div>

    {{-- The same figures as a table: readable without the charts, and checkable. --}}
    <x-card flush class="overflow-hidden">
        <h2 class="{{ $sectionLabel }} px-5 pt-4 pb-3">{{ __('messages.reports_monthly_table') }}</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="text-left text-[0.7rem] uppercase tracking-wide text-gray-400 dark:text-slate-500 border-y border-gray-100 dark:border-slate-700">
                        <th class="px-5 py-2.5 font-semibold">{{ __('messages.reports_month') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('messages.bills') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('messages.expenses_menu') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('messages.vehicles') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('messages.reports_spent') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('messages.income') }}</th>
                        <th class="px-5 py-2.5 font-semibold text-right">{{ __('messages.reports_net') }}</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 dark:text-slate-300">
                    @foreach($months->reverse() as $m)
                        <tr class="border-b border-gray-100 dark:border-slate-700/60">
                            <td class="px-5 py-2.5 font-medium text-gray-900 dark:text-white">{{ $m['month']->translatedFormat('F Y') }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($m['bills'], 2) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($m['purchases'], 2) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format($m['vehicles'], 2) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ number_format($m['spent'], 2) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-emerald-600 dark:text-emerald-400">{{ number_format($m['income'], 2) }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums font-semibold {{ $m['net'] < 0 ? 'text-red-500 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ number_format($m['net'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-bold text-gray-900 dark:text-white bg-gray-50 dark:bg-slate-700/30">
                        <td class="px-5 py-3">{{ __('messages.reports_total') }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($totals['bills'], 2) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($totals['purchases'], 2) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($totals['vehicles'], 2) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($totals['spent'], 2) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($totals['income'], 2) }}</td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ number_format($totals['net'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
@endsection
