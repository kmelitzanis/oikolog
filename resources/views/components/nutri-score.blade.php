{{--
    Nutri-Score badge, A (best) to E (worst).

    A product with no grade still renders, as a muted "—": an empty space would
    read as "nothing known" the same way a missing badge does, but shifts the
    layout of every row around it.

    &lt;x-nutri-score :grade="$product->grade()" />
    &lt;x-nutri-score :grade="$product->grade()" size="lg" :label="true" />
--}}
@props([
    'grade' => null,
    'size'  => 'sm',
    'label' => false,
])
@php
    $g = strtolower((string) $grade);
    $known = in_array($g, ['a','b','c','d','e'], true);

    // The official Nutri-Score ramp: dark green through to red.
    $tones = [
        'a' => 'bg-[#038141] text-white',
        'b' => 'bg-[#85bb2f] text-white',
        'c' => 'bg-[#fecb02] text-slate-900',
        'd' => 'bg-[#ee8100] text-white',
        'e' => 'bg-[#e63e11] text-white',
    ];
    $sizes = [
        'sm' => 'w-6 h-6 text-[0.72rem] rounded-md',
        'md' => 'w-8 h-8 text-sm rounded-lg',
        'lg' => 'w-12 h-12 text-xl rounded-xl',
    ];
    $tone = $known ? $tones[$g] : 'bg-gray-100 dark:bg-slate-700 text-gray-400 dark:text-slate-500';
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-2']) }}>
    <span class="{{ $sizes[$size] ?? $sizes['sm'] }} {{ $tone }} inline-flex items-center justify-center font-extrabold shrink-0 uppercase"
          title="{{ $known ? __('messages.nutri_score') . ' ' . strtoupper($g) : __('messages.nutri_score_unknown') }}">
        {{ $known ? strtoupper($g) : '—' }}
    </span>
    @if($label)
        <span class="text-xs text-gray-500 dark:text-slate-400">
            {{ $known ? __('messages.nutri_score_' . $g) : __('messages.nutri_score_unknown') }}
        </span>
    @endif
</span>
