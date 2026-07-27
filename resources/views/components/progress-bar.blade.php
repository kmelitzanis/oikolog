{{--
    Progress bar. Pass a server-rendered :percent, or an Alpine expression via
    `x-percent` for reactive contexts (shopping list cards).

    &lt;x-progress-bar :percent="$spentPercent" />
    &lt;x-progress-bar x-percent="pct(list)" />
--}}
@props([
    'percent' => null,
    'xPercent' => null,
    'tone' => 'brand',
])
@php
    $fillClass = match ($tone) {
        'success' => 'bg-emerald-500',
        'danger'  => 'bg-red-500',
        default   => 'bg-linear-to-r from-indigo-500 to-purple-500',
    };
    $isComplete = $percent !== null && (int) $percent >= 100;
@endphp
<div {{ $attributes->class(['h-2.5 rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden']) }}>
    @if($xPercent)
        <div class="h-full rounded-full transition-all duration-500"
             :class="{{ $xPercent }} >= 100 ? 'bg-emerald-500' : 'bg-linear-to-r from-indigo-500 to-purple-500'"
             :style="`width: ${{{ $xPercent }}}%`"></div>
    @else
        <div class="h-full rounded-full transition-all duration-500 {{ $isComplete ? 'bg-emerald-500' : $fillClass }}"
             style="width: {{ max(0, min(100, (int) $percent)) }}%"></div>
    @endif
</div>
