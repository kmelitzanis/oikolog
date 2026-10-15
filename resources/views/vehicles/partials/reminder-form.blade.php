{{--
    Reminder fields, for both "add" and "edit".
    Expects: $action (url), optional $reminder (editing), $onCancel (Alpine expr).
--}}
@php $r = $reminder ?? null; @endphp
<form method="POST" action="{{ $action }}" class="grid grid-cols-2 gap-2.5 p-3 rounded-2xl bg-gray-50 dark:bg-slate-900/60">
    @csrf
    @if($r) @method('PUT') @endif
    <div class="col-span-2">
        <x-input name="label" required maxlength="120" :value="$r?->label"
                 :placeholder="__('messages.vehicle_reminder_label_ph')" />
    </div>
    <label class="text-[0.66rem] text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_due_date') }}
        <x-input type="date" name="due_date" :value="$r?->due_date?->format('Y-m-d')" class="mt-1" />
    </label>
    <label class="text-[0.66rem] text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_due_km') }}
        <x-input type="number" name="due_km" min="0" :value="$r?->due_km" class="mt-1" />
    </label>
    <label class="text-[0.66rem] text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_repeat_months') }}
        <x-input type="number" name="interval_months" min="1" :value="$r?->interval_months" class="mt-1" />
    </label>
    <label class="text-[0.66rem] text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_repeat_km') }}
        <x-input type="number" name="interval_km" min="1" :value="$r?->interval_km" class="mt-1" />
    </label>
    <div class="col-span-2 flex gap-2 justify-end">
        <x-btn type="button" variant="ghost" size="sm" x-on:click="{{ $onCancel }}">{{ __('messages.cancel') }}</x-btn>
        <x-btn type="submit" size="sm" icon="check">{{ __('messages.save') }}</x-btn>
    </div>
</form>
