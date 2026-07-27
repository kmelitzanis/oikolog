{{--
    Surface card. The single source of truth for card chrome.

    Radius is normalised here — previously 32 files hand-wrote `rounded-2xl` and
    10 wrote `rounded-3xl`. Change it once here, not in 63 places.

    &lt;x-card>…&lt;/x-card>                    default padding (p-5)
    &lt;x-card flush>…&lt;/x-card>              no padding — caller supplies its own
    &lt;x-card radius="2xl">…&lt;/x-card>       nested/compact surfaces
--}}
@props([
    'flush' => false,
    'radius' => '3xl',
])
@php
    // Literal class strings — Tailwind v4 scans these files, so never interpolate.
    $radiusClass = match ($radius) {
        'xl'  => 'rounded-xl',
        '2xl' => 'rounded-2xl',
        default => 'rounded-3xl',
    };
@endphp
<div {{ $attributes->class([
    'bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 shadow-sm',
    $radiusClass,
    'p-5' => ! $flush,
]) }}>
    {{ $slot }}
</div>
