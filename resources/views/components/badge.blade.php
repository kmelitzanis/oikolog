{{--
    Status pill. Tones map 1:1 to the app's status vocabulary — do not invent a
    new tone; if you need another meaning, add an icon instead.

    &lt;x-badge tone="overdue">{{ __('messages.overdue') }}&lt;/x-badge>
--}}
@props(['tone' => 'neutral'])
@php
    // Only supply a display utility when the caller hasn't. Several badges are
    // `hidden md:inline-flex`; baking in `inline-flex` would collide at equal
    // specificity and the winner would depend on stylesheet order.
    $incoming = (string) $attributes->get('class');
    $callerSetsDisplay = (bool) preg_match('/(?:^|\s)(hidden|inline-flex|flex|block|inline-block|inline|grid)(?:\s|$)/', $incoming);

    $toneClass = match ($tone) {
        // Brand is the solid amber chip; `partial` keeps the soft amber tint, so
        // the two stay distinguishable now that amber is the brand colour.
        'brand'    => 'bg-amber-500 text-slate-900',
        'overdue'  => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
        'soon'     => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
        'upcoming' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
        'paid'     => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
        'partial'  => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
        default    => 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300',
    };
@endphp
<span {{ $attributes->class([
    'items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap',
    'inline-flex' => ! $callerSetsDisplay,
    $toneClass,
]) }}>
    {{ $slot }}
</span>
