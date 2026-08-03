@extends('layouts.app')
@section('title', isset($account) ? __('messages.edit_account') : __('messages.add_account'))

{{--
    Account create / edit. The app ships no predefined accounts — the user names
    their own (salary, savings, cash, whatever they keep money in) and decides
    per account whether the family sees it.
--}}

@section('content')
    @php
        $editing = isset($account);
        $curCode = $editing ? $account->currency_code : auth()->user()->currency_code;
        $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
        $symbol  = $symbols[$curCode] ?? $curCode;

        // A small palette of material icons that read as "somewhere money sits".
        $icons = ['account_balance','savings','payments','credit_card','wallet',
                  'account_balance_wallet','currency_exchange','real_estate_agent'];
        $colors = ['#10b981','#3b82f6','#f59e0b','#8b5cf6','#ef4444','#14b8a6','#ec4899','#64748b'];
    @endphp

    <div class="max-w-3xl">

        <div class="flex items-center gap-3 mb-6">
            <a href="{{ $editing ? route('accounts.show', $account) : route('accounts.index') }}"
               class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <div class="min-w-0">
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white truncate">
                    {{ $editing ? __('messages.edit_account') : __('messages.add_account') }}
                </h1>
                @if($editing)
                    <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5 truncate">{{ $account->name }}</p>
                @endif
            </div>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-2xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 px-4 py-3">
                <div class="flex items-center gap-2 text-sm font-semibold text-red-700 dark:text-red-300">
                    <span class="material-icons-round text-base">error_outline</span>
                    {{ __('messages.validation_failed') }}
                </div>
            </div>
        @endif

        <form method="POST"
              action="{{ $editing ? route('accounts.update', $account) : route('accounts.store') }}"
              class="space-y-4"
              x-data="{
                  icon: '{{ old('icon', $editing ? $account->icon : 'account_balance') }}',
                  color: '{{ old('color_hex', $editing ? $account->color_hex : '#10b981') }}',
              }">
            @csrf
            @if($editing) @method('PUT') @endif

            <x-form-section icon="account_balance"
                            :title="__('messages.section_account_basics')"
                            :hint="__('messages.section_account_basics_hint')">

                <x-field :label="__('messages.account_name')" name="name" required>
                    <x-input name="name" id="name" required maxlength="120"
                             placeholder="{{ __('messages.account_name_placeholder') }}"
                             :value="old('name', $editing ? $account->name : '')" />
                </x-field>

                <x-field :label="__('messages.opening_balance')" name="opening_balance"
                         :hint="__('messages.opening_balance_hint')">
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-sm">{{ $symbol }}</span>
                        <x-input name="opening_balance" id="opening_balance" type="number" step="0.01"
                                 class="!pl-9"
                                 :value="old('opening_balance', $editing ? $account->opening_balance : '0.00')" />
                    </div>
                </x-field>

                <x-field :label="__('messages.icon')" name="icon">
                    <input type="hidden" name="icon" :value="icon">
                    <div class="flex flex-wrap gap-2">
                        @foreach($icons as $ic)
                            <button type="button" @click="icon = '{{ $ic }}'"
                                    :class="icon === '{{ $ic }}'
                                        ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                        : 'border-gray-200 dark:border-slate-600 text-gray-400 dark:text-slate-500'"
                                    class="w-11 h-11 rounded-xl border flex items-center justify-center transition">
                                <span class="material-icons-round">{{ $ic }}</span>
                            </button>
                        @endforeach
                    </div>
                </x-field>

                <x-field :label="__('messages.color')" name="color_hex">
                    <input type="hidden" name="color_hex" :value="color">
                    <div class="flex flex-wrap gap-2">
                        @foreach($colors as $c)
                            <button type="button" @click="color = '{{ $c }}'"
                                    :class="color === '{{ $c }}' ? 'ring-2 ring-offset-2 ring-gray-400 dark:ring-offset-slate-800' : ''"
                                    class="w-9 h-9 rounded-full transition"
                                    style="background-color: {{ $c }}"></button>
                        @endforeach
                    </div>
                </x-field>
            </x-form-section>

            <x-form-section icon="tune"
                            :title="__('messages.section_settings')"
                            :hint="__('messages.section_account_settings_hint')">

                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="is_shared" value="1" class="mt-1 accent-amber-500 w-4 h-4"
                           {{ old('is_shared', $editing ? $account->is_shared : false) ? 'checked' : '' }}>
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-gray-700 dark:text-slate-200">{{ __('messages.share_with_family') }}</span>
                        <span class="block text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ __('messages.share_account_hint') }}</span>
                    </span>
                </label>

                @if($editing)
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" class="mt-1 accent-amber-500 w-4 h-4"
                               {{ old('is_active', $account->is_active) ? 'checked' : '' }}>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-gray-700 dark:text-slate-200">{{ __('messages.active') }}</span>
                            <span class="block text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ __('messages.active_account_hint') }}</span>
                        </span>
                    </label>
                @endif

                <x-field :label="__('messages.notes')" name="notes" optional>
                    <x-input as="textarea" name="notes" id="notes" rows="3">{{ old('notes', $editing ? $account->notes : '') }}</x-input>
                </x-field>
            </x-form-section>

            <div class="flex gap-3">
                <button type="submit"
                        class="h-11 px-5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-sm font-bold transition shadow-[0_6px_18px_rgba(245,158,11,0.32)]">
                    {{ $editing ? __('messages.save') : __('messages.add_account') }}
                </button>
                <a href="{{ $editing ? route('accounts.show', $account) : route('accounts.index') }}"
                   class="h-11 px-5 rounded-xl border border-gray-200 dark:border-slate-700 text-gray-500 dark:text-slate-400 text-sm font-semibold flex items-center hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                    {{ __('messages.cancel') }}
                </a>
            </div>
        </form>
    </div>
@endsection
