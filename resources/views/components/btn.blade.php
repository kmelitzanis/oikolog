{{--
    Button. Renders an <a> when `href` is present, otherwise a <button>.

    &lt;x-btn href="/bills/create" icon="add">{{ __('messages.add_bill') }}&lt;/x-btn>
    &lt;x-btn variant="success" block icon="check_circle">Mark as Paid&lt;/x-btn>
    &lt;x-btn variant="ghost" type="button" @click="open = false">Cancel&lt;/x-btn>
--}}
@props([
    'variant' => 'primary',
    'size'    => 'md',
    'href'    => null,
    'icon'    => null,
    'block'   => false,
    'type'    => 'submit',
])
@php
    $variantClass = match ($variant) {
        'success' => 'bg-emerald-600 hover:bg-emerald-700 text-white border border-transparent',
        'danger'  => 'bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 border border-transparent',
        'ghost'   => 'bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 border border-transparent',
        'outline' => 'bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-200 border border-gray-200 dark:border-slate-700',
        // Amber carries slate ink, never white — #f59e0b is far too light for
        // white text to clear contrast.
        default   => 'bg-amber-500 hover:bg-amber-400 text-slate-900 border border-transparent shadow-sm shadow-amber-500/30',
    };

    $sizeClass = match ($size) {
        'sm' => 'px-3 py-1.5 text-xs rounded-lg gap-1',
        'lg' => 'px-5 py-3 text-sm rounded-xl gap-2',
        default => 'px-4 py-2.5 text-sm rounded-xl gap-2',
    };

    $classes = [
        'inline-flex items-center justify-center font-semibold transition cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed disabled:pointer-events-none',
        $variantClass,
        $sizeClass,
        'w-full' => $block,
    ];
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if($icon)<span class="material-icons-round text-lg">{{ $icon }}</span>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if($icon)<span class="material-icons-round text-lg">{{ $icon }}</span>@endif
        {{ $slot }}
    </button>
@endif
