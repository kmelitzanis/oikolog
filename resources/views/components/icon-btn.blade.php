{{--
    32×32 icon action — the row-action vocabulary (pay, undo, edit, delete).

    &lt;x-icon-btn tone="pay" icon="check_circle" title="…" />
    &lt;x-icon-btn tone="undo" icon="undo" type="submit" />
    &lt;x-icon-btn tone="neutral" icon="edit" :href="route('bills.edit', $bill)" />
--}}
@props([
    'tone' => 'neutral',
    'icon' => 'more_horiz',
    'href' => null,
    'type' => 'submit',
])
@php
    $toneClass = match ($tone) {
        'pay'    => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/40',
        'undo'   => 'bg-orange-50 dark:bg-orange-900/20 text-orange-500 dark:text-orange-400 hover:bg-orange-100 dark:hover:bg-orange-900/40',
        'danger' => 'bg-gray-50 dark:bg-slate-700 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600',
        default  => 'bg-gray-50 dark:bg-slate-700 text-gray-500 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-600',
    };

    $classes = [
        'w-8 h-8 flex items-center justify-center rounded-xl transition cursor-pointer',
        $toneClass,
    ];
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        <span class="material-icons-round text-base">{{ $icon }}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        <span class="material-icons-round text-base">{{ $icon }}</span>
    </button>
@endif
