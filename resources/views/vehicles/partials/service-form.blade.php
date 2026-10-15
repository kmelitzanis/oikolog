{{--
    Service (workshop receipt) fields, for both "add" and "edit".
    Expects: $action, optional $service, $onCancel (Alpine expr).
    Receipt lines are added client-side: a bill has as many rows as it has.
--}}
@php
    $sv = $service ?? null;
    $lines = collect($sv?->lines ?? [])->map(fn($l) => ['what' => $l['what'] ?? '', 'cost' => isset($l['cost']) && (float) $l['cost'] > 0 ? $l['cost'] : ''])->values()->all() ?: [['what' => '', 'cost' => '']];
@endphp
<form method="POST" action="{{ $action }}" x-data="{ lines: @js($lines) }"
      class="p-3 rounded-2xl bg-gray-50 dark:bg-slate-900/60 space-y-2.5">
    @csrf
    @if($sv) @method('PUT') @endif
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
        <x-input type="date" name="performed_at" required :value="($sv?->performed_at ?? now())->format('Y-m-d')" />
        <x-input type="number" name="odometer_km" min="0" :placeholder="__('messages.vehicle_odometer')" :value="$sv?->odometer_km" />
        <x-input name="shop" maxlength="120" :placeholder="__('messages.vehicle_service_shop')" :value="$sv?->shop" />
        <x-input type="number" name="total" step="any" min="0" :placeholder="__('messages.vehicle_service_total')"
                 :value="$sv && (float) $sv->total > 0 ? $sv->total : null" />
    </div>

    <template x-for="(line, i) in lines" :key="i">
        <div class="grid grid-cols-[1fr_110px_32px] gap-2">
            <input type="text" :name="`lines[${i}][what]`" x-model="line.what" maxlength="120"
                   placeholder="{{ __('messages.vehicle_service_line') }}"
                   class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm outline-none focus:border-amber-500 dark:text-white">
            <input type="number" step="any" min="0" :name="`lines[${i}][cost]`" x-model="line.cost"
                   placeholder="{{ __('messages.vehicle_service_cost') }}"
                   class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm outline-none focus:border-amber-500 dark:text-white">
            <button type="button" @click="lines.splice(i, 1)" x-show="lines.length > 1"
                    class="text-gray-300 hover:text-red-500 transition" aria-label="{{ __('messages.delete') }}">
                <span class="material-icons-round text-base">close</span>
            </button>
        </div>
    </template>

    <div class="flex items-center gap-2">
        <button type="button" @click="lines.push({ what: '', cost: '' })"
                class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline">
            + {{ __('messages.vehicle_service_line') }}
        </button>
        <x-btn type="button" variant="ghost" size="sm" class="ms-auto" x-on:click="{{ $onCancel }}">{{ __('messages.cancel') }}</x-btn>
        <x-btn type="submit" size="sm" icon="check">{{ __('messages.save') }}</x-btn>
    </div>
</form>
