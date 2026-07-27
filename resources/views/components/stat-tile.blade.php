{{--
    Compact label/value tile — dashboard stats and the income allocation row.

    &lt;x-stat-tile :label="__('messages.income_spent')" value="€50.00" tone="danger" />
--}}
@props([
    'label' => '',
    'value' => '',
    'tone'  => 'neutral',
    'hint'  => null,
])
@php
    [$bgClass, $labelClass, $valueClass] = match ($tone) {
        'danger'  => ['bg-red-50 dark:bg-red-900/15', 'text-red-400', 'text-red-500 dark:text-red-400'],
        'success' => ['bg-emerald-50 dark:bg-emerald-900/15', 'text-emerald-500', 'text-emerald-600 dark:text-emerald-400'],
        'brand'   => ['bg-indigo-50 dark:bg-indigo-900/20', 'text-indigo-400', 'text-indigo-600 dark:text-indigo-300'],
        default   => ['bg-gray-50 dark:bg-slate-700/40', 'text-gray-400 dark:text-slate-500', 'text-gray-900 dark:text-white'],
    };
@endphp
<div {{ $attributes->class(['rounded-xl py-3 px-3 text-center', $bgClass]) }}>
    <div class="text-[11px] font-semibold uppercase tracking-wide {{ $labelClass }}">{{ $label }}</div>
    <div class="text-base font-extrabold mt-0.5 {{ $valueClass }}">{{ $value }}</div>
    @if($hint)
        <div class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">{{ $hint }}</div>
    @endif
</div>
