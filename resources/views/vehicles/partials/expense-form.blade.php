{{--
    Expense fields, for both "add" and "edit".
    Expects: $action, optional $expense, $onCancel (Alpine expr), $providers
    (vehicle cost => [{id, name}], from VehicleController::providersByCost()).

    Insurance, road tax and KTEO come round on a schedule. Ticking "recurring"
    when adding one makes it a bill (see VehicleController::recordAsBill()),
    so the bills list reminds you next time. Only offered when adding: an
    expense already on the car stays one.
--}}
@php
    $e = $expense ?? null;
    $providers = $providers ?? [];
    $withProvider = \App\Models\VehicleExpense::WITH_PROVIDER;
    $recurring = \App\Models\VehicleExpense::RECURRING;
@endphp
<div class="@container">
<form method="POST" action="{{ $action }}"
      x-data="{ cat: @js($e?->category ?? 'service'), recurring: false,
                withProvider: @js($withProvider), recurringCats: @js($recurring) }"
      class="grid grid-cols-1 @xs:grid-cols-2 @lg:grid-cols-3 gap-2.5 p-3 rounded-2xl bg-gray-50 dark:bg-slate-900/60">
    @csrf
    @if($e) @method('PUT') @endif
    <x-input as="select" name="category" x-model="cat" :aria-label="__('messages.category')">
        {{-- Fill-ups have a form of their own, the fuel log. --}}
        @foreach(array_diff(\App\Models\VehicleExpense::CATEGORIES, ['fuel']) as $c)
            <option value="{{ $c }}" @selected(($e?->category ?? 'service') === $c)>{{ __('messages.vehicle_expense_' . $c) }}</option>
        @endforeach
    </x-input>
    <x-input type="number" name="amount" step="any" min="0" required placeholder="0.00" :value="$e?->amount" :aria-label="__('messages.amount')" />
    <x-input type="date" name="spent_at" required :value="($e?->spent_at ?? now())->format('Y-m-d')" :aria-label="__('messages.date')" />

    {{-- One select per cost, so each lists only its own providers; the
         hidden ones are disabled and not sent. --}}
    @foreach($withProvider as $key)
        <div x-show="cat === '{{ $key }}'" x-cloak>
            <x-input as="select" name="provider_id" x-bind:disabled="cat !== '{{ $key }}'" :aria-label="__('messages.provider')">
                <option value="">{{ __('messages.provider') }} ({{ __('messages.optional') }})</option>
                @foreach($providers[$key] ?? [] as $p)
                    <option value="{{ $p['id'] }}" @selected($e?->provider_id === $p['id'])>{{ $p['name'] }}</option>
                @endforeach
            </x-input>
        </div>
    @endforeach

    <x-input type="number" name="odometer_km" min="0" :placeholder="__('messages.vehicle_odometer')" :value="$e?->odometer_km" :aria-label="__('messages.vehicle_odometer')" />
    <x-input name="note" maxlength="255" :placeholder="__('messages.note')" :value="$e?->note" :aria-label="__('messages.note')" />

    @unless($e)
        <div x-show="recurringCats.includes(cat)" x-cloak class="@xs:col-span-2 @lg:col-span-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-200">
                <input type="checkbox" name="recurring" value="1" x-model="recurring"
                       class="rounded border-gray-300 dark:border-slate-600 text-amber-500 focus:ring-amber-500">
                {{ __('messages.vehicle_recurring') }}
            </label>
            <div x-show="recurring" x-cloak class="min-w-[10rem]">
                <x-input as="select" name="frequency" x-bind:disabled="!recurring" x-bind:required="recurring" :aria-label="__('messages.frequency')">
                    @foreach(\App\Models\VehicleExpense::FREQUENCIES as $f)
                        <option value="{{ $f }}" @selected($f === 'yearly')>{{ __('messages.' . $f) }}</option>
                    @endforeach
                </x-input>
            </div>
            <p x-show="recurring" x-cloak class="w-full text-[0.7rem] text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_recurring_hint') }}</p>
        </div>
    @endunless

    <div class="@xs:col-span-2 @lg:col-span-3 flex gap-2 justify-end">
        <x-btn type="button" variant="ghost" size="sm" x-on:click="{{ $onCancel }}">{{ __('messages.cancel') }}</x-btn>
        <x-btn type="submit" size="sm" icon="check">{{ __('messages.save') }}</x-btn>
    </div>
</form>
</div>
