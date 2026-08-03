{{--
    One titled group of fields inside a form. The bill form used to be a single
    300-line column of 14 undifferentiated inputs; grouping is what makes it
    scannable.

    &lt;x-form-section :title="__('messages.section_basics')" icon="receipt"
                    :hint="__('messages.section_basics_hint')"> … &lt;/x-form-section>
--}}
@props([
    'title' => null,
    'hint'  => null,
    'icon'  => null,
])
<x-card class="space-y-5">
    @if($title)
        <div class="flex items-start gap-3 pb-1">
            @if($icon)
                <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-500/15 flex items-center justify-center shrink-0">
                    <span class="material-icons-round text-amber-500 text-lg">{{ $icon }}</span>
                </div>
            @endif
            <div class="min-w-0">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $title }}</h2>
                @if($hint)
                    <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ $hint }}</p>
                @endif
            </div>
        </div>
    @endif
    {{ $slot }}
</x-card>
