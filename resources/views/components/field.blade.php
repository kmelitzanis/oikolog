{{--
    Label chrome for one form control — label, required star / "(optional)" hint,
    validation error and help text. The control itself goes in the slot.

    &lt;x-field :label="__('messages.bill_name')" name="name" required>
        &lt;x-input name="name" :value="old('name')" />
    &lt;/x-field>
--}}
@props([
    'label'    => null,
    'name'     => null,
    'required' => false,
    'optional' => false,
    'hint'     => null,
])
<div {{ $attributes->class(['min-w-0']) }}>
    @if($label)
        <label @if($name) for="{{ $name }}" @endif
               class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
            {{ $label }}
            @if($required)<span class="text-amber-500">*</span>@endif
            @if($optional)<span class="text-gray-400 dark:text-slate-500 font-normal">({{ __('messages.optional') }})</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if($hint)
        <p class="mt-1.5 text-xs text-gray-400 dark:text-slate-500">{{ $hint }}</p>
    @endif

    @if($name)
        @error($name)
            <p class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
                <span class="material-icons-round text-sm">error_outline</span>{{ $message }}
            </p>
        @enderror
    @endif
</div>
