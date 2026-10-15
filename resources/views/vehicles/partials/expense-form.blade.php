{{--
    Expense fields, for both "add" and "edit".
    Expects: $action, optional $expense, $onCancel (Alpine expr).
--}}
@php $e = $expense ?? null; @endphp
<form method="POST" action="{{ $action }}" class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 p-3 rounded-2xl bg-gray-50 dark:bg-slate-900/60">
    @csrf
    @if($e) @method('PUT') @endif
    <x-input as="select" name="category">
        @foreach(\App\Models\VehicleExpense::CATEGORIES as $c)
            <option value="{{ $c }}" @selected(($e?->category ?? 'fuel') === $c)>{{ __('messages.vehicle_expense_' . $c) }}</option>
        @endforeach
    </x-input>
    <x-input type="number" name="amount" step="any" min="0" required placeholder="0.00" :value="$e?->amount" />
    <x-input type="date" name="spent_at" required :value="($e?->spent_at ?? now())->format('Y-m-d')" />
    <x-input type="number" name="odometer_km" min="0" :placeholder="__('messages.vehicle_odometer')" :value="$e?->odometer_km" />
    <x-input type="number" name="litres" step="any" min="0" :placeholder="__('messages.vehicle_litres')" :value="$e?->litres" />
    <x-input name="note" maxlength="255" :placeholder="__('messages.note')" :value="$e?->note" />
    <div class="col-span-2 sm:col-span-3 flex gap-2 justify-end">
        <x-btn type="button" variant="ghost" size="sm" x-on:click="{{ $onCancel }}">{{ __('messages.cancel') }}</x-btn>
        <x-btn type="submit" size="sm" icon="check">{{ __('messages.save') }}</x-btn>
    </div>
</form>
