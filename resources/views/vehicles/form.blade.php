@extends('layouts.app')
@section('title', $editing ? $vehicle->name : __('messages.add_vehicle'))

@section('content')
    <div class="max-w-3xl">
        <x-page-header :title="$editing ? $vehicle->name : __('messages.add_vehicle')" />

        @if($errors->any())
            <div class="mb-4 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-500/30 p-4 text-sm text-red-700 dark:text-red-300">
                {{ __('messages.validation_failed') }}
            </div>
        @endif

        {{-- enctype matters: the photo is part of this form, not a second step. --}}
        <form method="POST" enctype="multipart/form-data"
              action="{{ $editing ? route('vehicles.update', $vehicle) : route('vehicles.store') }}"
              class="space-y-4">
            @csrf
            @if($editing) @method('PUT') @endif

            <x-form-section icon="directions_car" :title="__('messages.section_basics')">
                <x-field :label="__('messages.bill_name')" name="name" required>
                    <x-input name="name" id="name" required :invalid="$errors->has('name')"
                             value="{{ old('name', $vehicle->name) }}" placeholder="Toyota Yaris" />
                </x-field>

                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('messages.vehicle_type')" name="type" required>
                        <x-input as="select" name="type" id="type" required>
                            @foreach(\App\Models\Vehicle::TYPES as $t)
                                <option value="{{ $t }}" {{ old('type', $vehicle->type ?? 'car') === $t ? 'selected' : '' }}>
                                    {{ __('messages.vehicle_type_' . $t) }}
                                </option>
                            @endforeach
                        </x-input>
                    </x-field>
                    <x-field :label="__('messages.vehicle_plate')" name="plate" optional>
                        <x-input name="plate" id="plate" maxlength="20"
                                 value="{{ old('plate', $vehicle->plate) }}" placeholder="ΙΚΑ 4821" />
                    </x-field>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('messages.vehicle_brand')" name="brand" optional>
                        <x-input name="brand" id="brand" value="{{ old('brand', $vehicle->brand) }}" />
                    </x-field>
                    <x-field :label="__('messages.vehicle_model')" name="model" optional>
                        <x-input name="model" id="model" value="{{ old('model', $vehicle->model) }}" />
                    </x-field>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('messages.vehicle_year')" name="year" optional>
                        <x-input type="number" name="year" id="year" min="1900" max="{{ date('Y') + 1 }}"
                                 value="{{ old('year', $vehicle->year) }}" />
                    </x-field>
                    <x-field :label="__('messages.vehicle_variant')" name="variant" optional>
                        <x-input name="variant" id="variant" :placeholder="__('messages.vehicle_variant_ph')"
                                 value="{{ old('variant', $vehicle->variant) }}" />
                    </x-field>
                </div>

                <x-field :label="__('messages.vehicle_photo')" name="photo" optional>
                    @if($editing && $vehicle->photoUrl())
                        <img src="{{ $vehicle->photoUrl() }}" alt=""
                             class="w-full max-w-xs h-32 object-cover rounded-2xl mb-2">
                    @endif
                    <input type="file" name="photo" accept="image/*"
                           class="block w-full text-sm text-gray-600 dark:text-slate-300
                                  file:me-3 file:py-2 file:px-4 file:rounded-xl file:border-0
                                  file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700
                                  dark:file:bg-amber-500/15 dark:file:text-amber-400">
                </x-field>
            </x-form-section>

            <x-form-section icon="speed" :title="__('messages.vehicle_odometer')">
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('messages.vehicle_odometer')" name="odometer_km" optional>
                        <x-input type="number" name="odometer_km" id="odometer_km" min="0" step="1"
                                 value="{{ old('odometer_km', $vehicle->odometer_km ?: '') }}" placeholder="0" />
                    </x-field>
                    <x-field :label="__('messages.vehicle_purchase_km')" name="purchase_km" optional
                             :hint="__('messages.vehicle_cost_not_measured')">
                        <x-input type="number" name="purchase_km" id="purchase_km" min="0" step="1"
                                 value="{{ old('purchase_km', $vehicle->purchase_km) }}" />
                    </x-field>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('messages.vehicle_purchase_date')" name="purchase_date" optional>
                        <x-input type="date" name="purchase_date" id="purchase_date"
                                 value="{{ old('purchase_date', $vehicle->purchase_date?->format('Y-m-d')) }}" />
                    </x-field>
                    <x-field :label="__('messages.vehicle_insurance_due')" name="insurance_due" optional>
                        <x-input type="date" name="insurance_due" id="insurance_due"
                                 value="{{ old('insurance_due', $vehicle->insurance_due?->format('Y-m-d')) }}" />
                    </x-field>
                </div>

                <x-field :label="__('messages.vehicle_insurer')" name="insurer" optional>
                    <x-input name="insurer" id="insurer" value="{{ old('insurer', $vehicle->insurer) }}" />
                </x-field>
            </x-form-section>

            <x-form-section icon="notes" :title="__('messages.notes')">
                <x-field :label="__('messages.notes')" name="notes" optional>
                    <x-input as="textarea" name="notes" id="notes" rows="3">{{ old('notes', $vehicle->notes) }}</x-input>
                </x-field>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="is_shared" value="0">
                    <input type="checkbox" name="is_shared" value="1" class="rounded"
                           {{ old('is_shared', $vehicle->is_shared) ? 'checked' : '' }}>
                    <span class="text-sm text-gray-600 dark:text-slate-300">{{ __('messages.shared') }}</span>
                </label>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded"
                           {{ old('is_active', $editing ? $vehicle->is_active : true) ? 'checked' : '' }}>
                    <span class="text-sm text-gray-600 dark:text-slate-300">{{ __('messages.active') }}</span>
                </label>
            </x-form-section>

            <div class="flex gap-3">
                <x-btn type="submit" icon="check">{{ __('messages.save') }}</x-btn>
                <x-btn variant="ghost" :href="$editing ? route('vehicles.show', $vehicle) : route('vehicles.index')">
                    {{ __('messages.cancel') }}
                </x-btn>
            </div>
        </form>
    </div>
@endsection
