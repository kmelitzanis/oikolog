{{--
    Text-ish form control. `as` picks the element so input / textarea / select all
    share one set of classes — this styling was hand-copied into ~10 places in the
    bill form alone, and had already drifted between them.

    &lt;x-input name="amount" type="number" step="0.01" />
    &lt;x-input as="textarea" name="notes" rows="3" />
    &lt;x-input as="select" name="category_id">…options…&lt;/x-input>
--}}
@props([
    'as'      => 'input',
    'type'    => 'text',
    'invalid' => false,
])
@php
    $base = 'w-full bg-gray-50 dark:bg-slate-700 dark:text-white text-gray-900 border rounded-xl px-4 py-3 text-sm outline-none transition';
    $ring = $invalid
        ? 'border-red-300 dark:border-red-700 focus:border-red-500 focus:ring-2 focus:ring-red-100 dark:focus:ring-red-500/30'
        : 'border-gray-200 dark:border-slate-600 focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30';
@endphp
@if($as === 'textarea')
    <textarea {{ $attributes->class([$base, $ring, 'resize-none']) }}>{{ $slot }}</textarea>
@elseif($as === 'select')
    <select {{ $attributes->class([$base, $ring]) }}>{{ $slot }}</select>
@else
    <input type="{{ $type }}" {{ $attributes->class([$base, $ring]) }}>
@endif
