{{--
    The amount a varying bill costs this cycle, editable in place.

    A bill whose cost varies reads "varies" until someone knows the figure.
    Double-click (or tap the pencil) to type it; Enter or blur saves, Escape
    cancels. Saving PATCHes bills.amount and leaves the rest of the row alone —
    the point is to record what is owed days before anyone pays it.

    &lt;x-editable-amount :bill="$bill" />
    &lt;x-editable-amount :bill="$bill" size="lg" />
--}}
@props([
    'bill',
    'size' => 'sm',
])
@php
    $text = $size === 'lg' ? 'text-[2.4rem] leading-none font-extrabold' : 'text-sm font-bold';
    $field = $size === 'lg' ? 'w-44 text-2xl py-2' : 'w-24 text-sm py-1';
@endphp
<div x-data="editableAmount({
        url: '{{ route('bills.amount', $bill) }}',
        currency: '{{ $bill->currency_code }}',
        amount: {{ $bill->current_amount !== null ? (float) $bill->current_amount : 'null' }},
     })"
     @dblclick.stop.prevent="edit()"
     class="group/amt inline-flex items-center gap-1.5 justify-end"
     {{ $attributes }}>

    {{-- Reading --}}
    <template x-if="!editing">
        <span class="inline-flex items-center gap-1.5 cursor-text"
              :class="saving ? 'opacity-50' : ''"
              title="{{ __('messages.set_amount_hint') }}">
            <span x-show="amount === null" x-cloak
                  class="text-sm text-gray-400 dark:text-slate-500 font-medium italic">{{ __('messages.varies') }}</span>
            <span x-show="amount !== null" x-cloak
                  class="{{ $text }} text-gray-900 dark:text-white"
                  x-text="currency + ' ' + format(amount)"></span>
            {{-- Touch devices have no double-click, so the affordance is also a button. --}}
            <button type="button" @click.stop.prevent="edit()"
                    class="opacity-0 group-hover/amt:opacity-100 focus:opacity-100 text-gray-400 hover:text-amber-500 transition"
                    aria-label="{{ __('messages.set_amount') }}">
                <span class="material-icons-round" style="font-size:15px;">edit</span>
            </button>
        </span>
    </template>

    {{-- Editing --}}
    <template x-if="editing">
        <span class="inline-flex items-center gap-1" @click.stop>
            <input type="number" step="0.01" min="0" x-ref="input" x-model="draft"
                   @keydown.enter.prevent="save()"
                   @keydown.escape.prevent="cancel()"
                   @blur="save()"
                   class="{{ $field }} bg-white dark:bg-slate-900 border border-amber-500 rounded-lg px-2
                          text-right font-bold text-gray-900 dark:text-white outline-none
                          focus:ring-2 focus:ring-amber-200 dark:focus:ring-amber-500/30">
        </span>
    </template>
</div>
