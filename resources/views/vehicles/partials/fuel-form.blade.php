{{--
    A fill-up, for both "add" and "edit". It posts to the expense routes as a
    `fuel` expense, so it counts in the vehicle's totals like any other.
    Expects: $action, optional $expense, $onCancel (Alpine expr).

    Litres and the price per litre are what the pump shows; the total is
    worked out from them unless it is typed in. The odometer is what turns a
    fill-up into a consumption figure, so it is asked for up front.
--}}
@php
    $e = $expense ?? null;
    $price = $e?->pricePerLitre();
@endphp
{{-- Sized by the column it sits in, not the screen: beside the side
     column on a laptop the history is narrower than a phone. --}}
<div class="@container">
<form method="POST" action="{{ $action }}"
      x-data="{ litres: @js($e?->litres !== null ? (float) $e->litres : ''), price: @js($price ?? ''), amount: @js($e ? (float) $e->amount : '') }"
      class="grid grid-cols-1 @xs:grid-cols-2 @lg:grid-cols-3 gap-2.5 p-3 rounded-2xl bg-gray-50 dark:bg-slate-900/60">
    @csrf
    @if($e) @method('PUT') @endif
    <input type="hidden" name="category" value="fuel">

    <label class="block">
        <span class="block text-[0.7rem] font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.vehicle_service_date') }}</span>
        <x-input type="date" name="spent_at" required :value="($e?->spent_at ?? now())->format('Y-m-d')" />
    </label>
    <label class="block">
        <span class="block text-[0.7rem] font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.vehicle_odometer') }}</span>
        <x-input type="number" name="odometer_km" min="0" step="1" :value="$e?->odometer_km" />
    </label>
    <label class="block">
        <span class="block text-[0.7rem] font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.vehicle_litres') }}</span>
        <x-input type="number" name="litres" step="0.01" min="0" required x-model="litres" />
    </label>
    <label class="block">
        <span class="block text-[0.7rem] font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.vehicle_fuel_price') }}</span>
        <x-input type="number" name="price_per_litre" step="0.001" min="0" x-model="price" />
    </label>
    <label class="block">
        <span class="block text-[0.7rem] font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.amount') }}</span>
        {{-- Left empty, the server works it out from litres × price. --}}
        <x-input type="number" name="amount" step="0.01" min="0" x-model="amount"
                 x-bind:placeholder="litres && price ? (litres * price).toFixed(2) : '0.00'" />
    </label>
    <label class="block">
        <span class="block text-[0.7rem] font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.vehicle_fuel_type') }}</span>
        <x-input as="select" name="fuel_type">
            <option value="">—</option>
            @foreach(\App\Models\VehicleExpense::FUEL_TYPES as $type)
                <option value="{{ $type }}" @selected($e?->fuel_type === $type)>{{ __('messages.vehicle_fuel_' . $type) }}</option>
            @endforeach
        </x-input>
    </label>
    <label class="block @xs:col-span-2 @lg:col-span-1">
        <span class="block text-[0.7rem] font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.vehicle_fuel_station') }}</span>
        <x-input name="station" maxlength="120" :value="$e?->station" />
    </label>
    <label class="block @xs:col-span-2">
        <span class="block text-[0.7rem] font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ __('messages.note') }}</span>
        <x-input name="note" maxlength="255" :value="$e?->note" />
    </label>

    <label class="@xs:col-span-2 @lg:col-span-3 flex items-start gap-2 text-sm text-gray-700 dark:text-slate-200">
        {{-- Unchecked sends 0 rather than nothing: nothing means "a full tank". --}}
        <input type="hidden" name="is_full_tank" value="0">
        <input type="checkbox" name="is_full_tank" value="1" @checked($e?->is_full_tank ?? true)
               class="mt-0.5 rounded border-gray-300 dark:border-slate-600 text-amber-500 focus:ring-amber-500">
        <span>
            {{ __('messages.vehicle_fuel_full_tank') }}
            <span class="block text-[0.7rem] text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_fuel_full_tank_hint') }}</span>
        </span>
    </label>

    <div class="@xs:col-span-2 @lg:col-span-3 flex gap-2 justify-end">
        <x-btn type="button" variant="ghost" size="sm" x-on:click="{{ $onCancel }}">{{ __('messages.cancel') }}</x-btn>
        <x-btn type="submit" size="sm" icon="check">{{ __('messages.save') }}</x-btn>
    </div>
</form>
</div>
