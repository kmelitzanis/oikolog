{{--
    Empty state. Two weights:
      full  — gradient tile + copy + CTA, when the whole page is empty
      quiet — muted tile, no CTA, for an empty section inside a populated page

    &lt;x-empty-state emoji="🍳" :title="__('messages.no_recipes')" :text="__('messages.no_recipes_hint')">
        &lt;x-btn :href="route('recipes.create')" icon="add">{{ __('messages.create_recipe') }}&lt;/x-btn>
    &lt;/x-empty-state>
--}}
@props([
    'icon'  => null,
    'emoji' => null,
    'title' => null,
    'text'  => null,
    'quiet' => false,
])
<div {{ $attributes->class(['text-center', $quiet ? 'py-10' : 'py-16 px-6']) }}>
    @if($emoji || $icon)
        <div @class([
            'mx-auto mb-4 flex items-center justify-center rounded-2xl',
            'w-16 h-16 bg-linear-to-br from-indigo-500 to-purple-500' => ! $quiet,
            'w-14 h-14 bg-indigo-50 dark:bg-indigo-900/30' => $quiet,
        ])>
            @if($emoji)
                <span class="text-3xl">{{ $emoji }}</span>
            @else
                <span @class([
                    'material-icons-round text-3xl',
                    'text-white' => ! $quiet,
                    'text-indigo-400' => $quiet,
                ])>{{ $icon }}</span>
            @endif
        </div>
    @endif

    @if($title)
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $title }}</h3>
    @endif
    @if($text)
        <p class="text-sm text-gray-400 dark:text-slate-500 mt-1.5 max-w-md mx-auto">{{ $text }}</p>
    @endif

    @if(trim($slot) !== '')
        <div class="mt-6 flex justify-center">{{ $slot }}</div>
    @endif
</div>
