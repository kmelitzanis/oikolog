{{--
    Oikolog wordmark — "beam stack". There is no separate icon mark: the
    wordmark *is* the logo, and the `l` of `log` is redrawn as four ledger
    beams of unequal length, left-aligned like a real ledger.

    Beam order runs short → long → medium → long from the top, with amber on
    the bottom beam (the design's `flipVertical` default). Ink is slate-900 on
    light and white on dark via `currentColor`; the accent beam stays amber-500
    in both.

    The mark is sized in `em`, so it tracks the wordmark at any font size —
    pass a size, or set your own `text-*` class on the element.

    &lt;x-logo />                 sidebar / topbar (sm)
    &lt;x-logo size="lg" />       auth screens, welcome hero
    &lt;x-logo mark />            square app-icon lockup (beams only, on slate)
--}}
@props([
    'size' => 'sm',
    'mark' => false,
])
@php
    // Literal class strings — Tailwind v4 scans this file, so never interpolate.
    $textClass = match ($size) {
        'lg' => 'text-4xl',
        'md' => 'text-2xl',
        default => 'text-lg',
    };
@endphp
@if($mark)
    <span {{ $attributes->class(['inline-flex items-center justify-center rounded-xl bg-slate-900 w-10 h-10']) }}>
        <svg width="20" height="28" viewBox="0 0 16 24" aria-hidden="true">
            <rect x="2" y="4" width="7" height="2.6" rx="1.3" fill="#fff"/>
            <rect x="2" y="8.6" width="12" height="2.6" rx="1.3" fill="#fff"/>
            <rect x="2" y="13.2" width="9" height="2.6" rx="1.3" fill="#fff"/>
            <rect x="2" y="17.8" width="12" height="2.6" rx="1.3" fill="#f59e0b"/>
        </svg>
    </span>
@else
    <span {{ $attributes->class([
        'inline-flex items-center font-extrabold tracking-[-0.03em] select-none',
        $textClass,
        'text-slate-900 dark:text-white',
    ]) }} role="img" aria-label="Oikolog">
        <span aria-hidden="true">oiko</span>
        {{-- Ratios taken from the design doc's 2.6rem lockup: 26/41.6 wide,
             40/41.6 tall, 2/41.6 side margin. --}}
        <svg viewBox="0 0 16 24" class="w-[0.625em] h-[0.962em] mx-[0.048em] shrink-0" aria-hidden="true">
            <rect x="2" y="4" width="7" height="2.6" rx="1.3" fill="currentColor"/>
            <rect x="2" y="8.6" width="12" height="2.6" rx="1.3" fill="currentColor"/>
            <rect x="2" y="13.2" width="9" height="2.6" rx="1.3" fill="currentColor"/>
            <rect x="2" y="17.8" width="12" height="2.6" rx="1.3" fill="#f59e0b"/>
        </svg>
        <span aria-hidden="true">og</span>
    </span>
@endif
