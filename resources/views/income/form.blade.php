@extends('layouts.app')
@section('title', isset($income) ? __('messages.edit_income') : __('messages.add_income'))

{{--
    Income create / edit.

    Built like the bill form: three titled sections (x-form-section) made of
    x-field / x-input, so the two forms in the app that describe recurring money
    look and behave the same and the control styling lives in one place.
--}}

@section('content')
    @php
        $editing = isset($income);
        $curCode = $editing ? $income->currency_code : auth()->user()->currency_code;
        $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
        $symbol  = $symbols[$curCode] ?? $curCode;
    @endphp

    <div class="max-w-3xl">

        <div class="flex items-center gap-3 mb-6">
            <a href="{{ $editing ? route('income.show', $income) : route('income.index') }}"
               class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <div class="min-w-0">
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white truncate">
                    {{ $editing ? __('messages.edit_income') : __('messages.add_income') }}
                </h1>
                @if($editing)
                    <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5 truncate">{{ $income->name }}</p>
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
              action="{{ $editing ? route('income.update', $income) : route('income.store') }}"
              class="space-y-4"
              x-data="{
                  freq: '{{ old('frequency', $editing ? $income->frequency : 'monthly') }}',
                  get isRecurring() { return this.freq !== 'once'; },
              }">
            @csrf
            @if($editing) @method('PUT') @endif

            {{-- ── Basics ───────────────────────────────────────────────── --}}
            <x-form-section icon="trending_up"
                            :title="__('messages.section_basics')"
                            :hint="__('messages.section_income_basics_hint')">

                <x-field :label="__('messages.income_name')" name="name" required>
                    <x-input name="name" id="name" required maxlength="120"
                             :invalid="$errors->has('name')"
                             value="{{ old('name', $editing ? $income->name : '') }}"
                             placeholder="{{ __('messages.income_name_ph') }}" />
                </x-field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-field :label="__('messages.amount')" name="amount" required>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 font-semibold text-sm pointer-events-none">{{ $symbol }}</span>
                            <x-input type="number" name="amount" id="amount" step="0.01" min="0.01" required
                                     :invalid="$errors->has('amount')"
                                     class="pl-10" placeholder="0.00"
                                     value="{{ old('amount', $editing ? $income->amount : '') }}" />
                        </div>
                    </x-field>

                    <x-field :label="__('messages.source')" name="source" optional
                             :hint="__('messages.source_hint')">
                        <x-input name="source" id="source" maxlength="80" list="source-suggestions"
                                 :invalid="$errors->has('source')"
                                 value="{{ old('source', $editing ? $income->source : '') }}"
                                 placeholder="{{ __('messages.source_ph') }}" />
                        <datalist id="source-suggestions">
                            @foreach(['salary','freelance','rental','business','dividends','pension','side_income','other'] as $s)
                                <option value="{{ __('messages.source_' . $s) }}"></option>
                            @endforeach
                        </datalist>
                    </x-field>
                </div>

                {{-- Where the money lands. Without it, marking the income as
                     received moves no balance, so say so rather than let it
                     silently do nothing. --}}
                <x-field :label="__('messages.deposit_account')" name="account_id"
                         :hint="isset($accounts) && $accounts->count() > 0 ? __('messages.deposit_account_hint') : null">
                    @if(isset($accounts) && $accounts->count() > 0)
                        <x-input as="select" name="account_id" id="account_id" :invalid="$errors->has('account_id')">
                            <option value="">{{ __('messages.no_deposit_account') }}</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}"
                                    {{ (string) old('account_id', $income->account_id ?? '') === (string) $acc->id ? 'selected' : '' }}>
                                    {{ $acc->name }}
                                </option>
                            @endforeach
                        </x-input>
                    @else
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 px-4 py-3">
                            <span class="text-[0.8rem] text-amber-800 dark:text-amber-300 min-w-0">
                                {{ __('messages.no_accounts_for_income') }}
                            </span>
                            <a href="{{ route('accounts.create') }}"
                               class="shrink-0 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 dark:text-amber-400 hover:underline">
                                <span class="material-icons-round text-base">add</span>{{ __('messages.add_account') }}
                            </a>
                        </div>
                    @endif
                </x-field>
            </x-form-section>

            {{-- ── Schedule ─────────────────────────────────────────────── --}}
            <x-form-section icon="event_repeat"
                            :title="__('messages.section_schedule')"
                            :hint="__('messages.section_income_schedule_hint')">

                <x-field :label="__('messages.frequency')" name="frequency" required>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['once','weekly','biweekly','monthly','quarterly','yearly'] as $val)
                            <label class="cursor-pointer">
                                <input type="radio" name="frequency" value="{{ $val }}" x-model="freq" class="sr-only peer">
                                <span :class="freq === '{{ $val }}'
                                          ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                          : 'border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-600 dark:text-slate-300 hover:border-gray-300'"
                                      class="inline-block px-4 py-2 rounded-xl text-sm font-medium border transition select-none peer-focus:ring-2 peer-focus:ring-amber-200">
                                    {{ __('messages.' . $val) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p x-show="!isRecurring" x-cloak
                       class="mt-2 text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1">
                        <span class="material-icons-round text-sm">info</span>
                        {{ __('messages.income_once_hint') }}
                    </p>
                </x-field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- The label depends on the frequency, so it is written
                         out rather than passed to x-field as a static string. --}}
                    <div class="min-w-0">
                        <label for="start_date" class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                            <span x-text="isRecurring ? '{{ __('messages.first_payment_date') }}' : '{{ __('messages.date') }}'"></span>
                            <span class="text-amber-500">*</span>
                        </label>
                        <x-input type="date" name="start_date" id="start_date" required
                                 :invalid="$errors->has('start_date')"
                                 value="{{ old('start_date', $editing ? $income->start_date->format('Y-m-d') : now()->format('Y-m-d')) }}" />
                        @error('start_date')
                            <p class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
                                <span class="material-icons-round text-sm">error_outline</span>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div x-show="isRecurring" x-cloak>
                        <x-field :label="__('messages.end_date')" name="end_date" optional
                                 :hint="__('messages.income_end_date_hint')">
                            <x-input type="date" name="end_date" id="end_date"
                                     :invalid="$errors->has('end_date')"
                                     value="{{ old('end_date', ($editing && $income->end_date) ? $income->end_date->format('Y-m-d') : '') }}" />
                        </x-field>
                    </div>
                </div>
            </x-form-section>

            {{-- ── Settings ─────────────────────────────────────────────── --}}
            <x-form-section icon="tune"
                            :title="__('messages.section_settings')"
                            :hint="__('messages.section_income_settings_hint')">

                @if($editing)
                    <div class="flex items-center justify-between gap-4 bg-gray-50 dark:bg-slate-700/50 rounded-xl px-4 py-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('messages.active') }}</div>
                            <div class="text-xs text-gray-400 dark:text-slate-400 mt-0.5">{{ __('messages.active_income_hint') }}</div>
                        </div>
                        <input type="hidden" name="is_active" value="0">
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input type="checkbox" name="is_active" value="1" class="sr-only peer"
                                   {{ old('is_active', $income->is_active) ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 dark:bg-slate-600 rounded-full peer transition
                                        peer-focus:ring-2 peer-focus:ring-emerald-200 peer-checked:bg-emerald-500
                                        after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                        after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                                        peer-checked:after:translate-x-full"></div>
                        </label>
                    </div>
                @endif

                <x-field :label="__('messages.notes')" name="notes" optional>
                    <x-input as="textarea" name="notes" id="notes" rows="3"
                             placeholder="{{ __('messages.notes') }}…">{{ old('notes', $editing ? $income->notes : '') }}</x-input>
                </x-field>
            </x-form-section>

            {{-- ── Submit ───────────────────────────────────────────────── --}}
            <div class="flex gap-3 pt-1">
                <x-btn type="submit" size="lg" class="flex-1" :icon="$editing ? 'save' : 'add'">
                    {{ $editing ? __('messages.save_changes') : __('messages.add_income') }}
                </x-btn>
                <x-btn variant="ghost" size="lg" :href="$editing ? route('income.show', $income) : route('income.index')">
                    {{ __('messages.cancel') }}
                </x-btn>
            </div>
        </form>
    </div>
@endsection
