@extends('layouts.app')
@section('title', $expense ? __('messages.expense_edit') : __('messages.expense_add'))

@section('content')
    @php
        $editing = (bool) $expense;
        $today = now()->toDateString();
        $defaultAccount = auth()->user()->default_account_id ?? null;
    @endphp

    <div class="max-w-2xl">
        <x-page-header :title="$editing ? __('messages.expense_edit') : __('messages.expense_add')" />

        <form method="POST" action="{{ $editing ? route('expenses.update', $expense) : route('expenses.store') }}" class="space-y-4">
            @csrf
            @if($editing) @method('PUT') @endif

            <x-card class="sm:p-6 space-y-4">
                <x-field :label="__('messages.expense_what')" name="name" required>
                    <x-input name="name" id="name" required maxlength="160" :invalid="$errors->has('name')"
                             value="{{ old('name', $expense?->name) }}" :placeholder="__('messages.expense_what_ph')" />
                </x-field>

                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('messages.amount')" name="amount" required>
                        <x-input name="amount" id="amount" type="number" step="0.01" min="0.01" required
                                 :invalid="$errors->has('amount')" value="{{ old('amount', $expense?->amount) }}" placeholder="0.00" />
                    </x-field>
                    <x-field :label="__('messages.date')" name="spent_at" required>
                        <x-input name="spent_at" id="spent_at" type="date" required max="{{ $today }}"
                                 :invalid="$errors->has('spent_at')" value="{{ old('spent_at', $expense?->spent_at?->toDateString() ?? $today) }}" />
                    </x-field>
                </div>

                {{-- Category as tiles: a tap on a phone at the till. --}}
                <fieldset>
                    <legend class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.category') }}</legend>
                    @php $current = old('category', $expense?->category ?? 'groceries'); @endphp
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                        @foreach(\App\Models\Expense::CATEGORIES as $category => $icon)
                            <label class="cursor-pointer">
                                <input type="radio" name="category" value="{{ $category }}" class="peer sr-only" @checked($current === $category)>
                                <span class="flex flex-col items-center gap-1 rounded-xl border border-gray-200 dark:border-slate-600 px-2 py-2.5 text-center
                                             text-gray-600 dark:text-slate-300 transition
                                             peer-checked:border-amber-500 peer-checked:bg-amber-500/10 peer-checked:text-amber-700 dark:peer-checked:text-amber-400
                                             peer-focus-visible:ring-2 peer-focus-visible:ring-amber-500">
                                    <span class="material-icons-round text-xl">{{ $icon }}</span>
                                    <span class="text-[0.7rem] font-semibold leading-tight">{{ __('messages.expense_category_' . $category) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('category') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </fieldset>

                @if($accounts->isNotEmpty())
                    <x-field :label="__('messages.expense_paid_from')" name="account_id" required>
                        <x-input as="select" name="account_id" id="account_id" required :invalid="$errors->has('account_id')">
                            @php $selected = old('account_id', $expense?->account_id ?? $defaultAccount); @endphp
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @selected($selected === $account->id)>{{ $account->name }}</option>
                            @endforeach
                        </x-input>
                    </x-field>
                @endif

                <x-field :label="__('messages.notes')" name="notes" optional>
                    <x-input as="textarea" name="notes" id="notes" rows="2" maxlength="1000">{{ old('notes', $expense?->notes) }}</x-input>
                </x-field>

                @if(auth()->user()->family_id)
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-200">
                        <input type="hidden" name="is_shared" value="0">
                        <input type="checkbox" name="is_shared" value="1" @checked(old('is_shared', $expense?->is_shared))
                               class="rounded border-gray-300 dark:border-slate-600 text-amber-500 focus:ring-amber-500">
                        {{ __('messages.expense_share') }}
                    </label>
                @endif
            </x-card>

            <div class="flex gap-3">
                <x-btn type="submit" icon="check">{{ __('messages.save') }}</x-btn>
                <x-btn variant="ghost" :href="route('expenses.index')">{{ __('messages.cancel') }}</x-btn>
            </div>
        </form>
    </div>
@endsection
