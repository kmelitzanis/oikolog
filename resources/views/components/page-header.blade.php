{{--
    Page header — title, optional sub-line, and a right-aligned actions slot.

    &lt;x-page-header :title="__('messages.recipes')" :subtitle="$count">
        &lt;x-slot:actions>
            &lt;x-btn :href="route('recipes.create')" icon="add">{{ __('messages.add_recipe') }}&lt;/x-btn>
        &lt;/x-slot:actions>
    &lt;/x-page-header>
--}}
@props([
    'title' => '',
    'subtitle' => null,
])
<div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6']) }}>
    <div class="min-w-0">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white truncate">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2 shrink-0">{{ $actions }}</div>
    @endisset
</div>
