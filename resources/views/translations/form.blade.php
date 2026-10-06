@extends('layouts.app')
@section('title', isset($translation) ? __('messages.edit_translation') : __('messages.new_translation'))

@section('content')
    <div class="max-w-2xl">
        <x-page-header :title="isset($translation) ? __('messages.edit_translation') : __('messages.new_translation')" />

        <form method="POST"
              action="{{ isset($translation) ? route('translations.update', $translation) : route('translations.store') }}">
            @csrf
            @isset($translation)
                @method('PUT')
            @endisset

            <x-card class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field :label="__('messages.language')" name="locale" required>
                        <x-input as="select" name="locale" id="locale" :invalid="$errors->has('locale')">
                            @foreach(['el' => 'Ελληνικά', 'en' => 'English'] as $code => $label)
                                <option value="{{ $code }}" @selected(old('locale', $translation->locale ?? app()->getLocale()) === $code)>{{ $label }}</option>
                            @endforeach
                        </x-input>
                    </x-field>
                    <x-field :label="__('messages.translation_group')" name="group" required>
                        <x-input name="group" id="group" :invalid="$errors->has('group')"
                                 value="{{ old('group', $translation->group ?? 'messages') }}" />
                    </x-field>
                </div>

                <x-field :label="__('messages.translation_key')" name="key" required>
                    <x-input name="key" id="key" class="font-mono" :invalid="$errors->has('key')"
                             value="{{ old('key', $translation->key ?? '') }}" placeholder="dashboard" />
                </x-field>

                <x-field :label="__('messages.translation_value')" name="value" required>
                    <x-input as="textarea" name="value" id="value" rows="4" :invalid="$errors->has('value')">{{ old('value', $translation->value ?? '') }}</x-input>
                </x-field>

                <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                    <x-btn variant="ghost" :href="route('translations.index')">{{ __('messages.cancel') }}</x-btn>
                    <x-btn icon="save">{{ __('messages.save') }}</x-btn>
                </div>
            </x-card>
        </form>
    </div>
@endsection
