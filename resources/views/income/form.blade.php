@extends('layouts.app')
@section('title', isset($income) ? 'Edit Income' : 'Add Income')

@section('content')
    <div class="max-w-2xl">

        {{-- Back + title --}}
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('income.index') }}" class="text-gray-400 hover:text-gray-600 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <h1 class="text-xl font-extrabold text-gray-900">
                {{ isset($income) ? 'Edit Income' : 'Add Income Source' }}
            </h1>
        </div>

        <form method="POST"
              action="{{ isset($income) ? route('income.update', $income) : route('income.store') }}"
              x-data="{
              freq: '{{ old('frequency', isset($income) ? $income->frequency : 'monthly') }}',
              isRecurring() { return this.freq !== 'once'; }
          }">
            @csrf
            @if(isset($income))
                @method('PUT')
            @endif

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-6">

                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Income Name *</label>
                    <input type="text" name="name"
                           value="{{ old('name', $income->name ?? '') }}"
                           placeholder="e.g. Monthly Salary, Freelance Project, Rent Income" required
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition">
                </div>

                {{-- Source --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Source / Category <span
                            class="text-gray-400">(optional)</span></label>
                    <input type="text" name="source"
                           value="{{ old('source', $income->source ?? '') }}"
                           placeholder="e.g. Employer, Freelance, Rental, Dividends"
                           list="source-suggestions"
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition">
                    <datalist id="source-suggestions">
                        @foreach(['Salary','Freelance','Rental','Business','Dividends','Pension','Side income','Other'] as $s)
                            <option value="{{ $s }}">
                        @endforeach
                    </datalist>
                </div>

                {{-- Amount --}}
                <div>
                    @php
                        $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
                        $symbol  = $symbols[auth()->user()->currency_code] ?? auth()->user()->currency_code;
                    @endphp
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Amount *</label>
                    <div class="relative">
                        <span
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-emerald-600 font-semibold text-sm">{{ $symbol }}</span>
                        <input type="number" name="amount" step="0.01" min="0.01"
                               value="{{ old('amount', isset($income) ? $income->amount : '') }}"
                               placeholder="0.00" required
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition">
                    </div>
                </div>

                {{-- Frequency --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">Frequency *</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['once'=>'One-time','weekly'=>'Weekly','biweekly'=>'Bi-weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly'] as $val => $lbl)
                            <label class="cursor-pointer">
                                <input type="radio" name="frequency" value="{{ $val }}" x-model="freq" class="sr-only">
                                <span :class="freq==='{{ $val }}'
                                       ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                       : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                                      class="inline-block px-4 py-2 rounded-xl text-sm font-medium border transition select-none">
                                {{ $lbl }}
                            </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400 mt-2" x-show="!isRecurring()">
                        One-time income will be recorded as a single entry on the start date.
                    </p>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1.5"
                               x-text="isRecurring() ? 'Start / First Date *' : 'Date *'"></label>
                        <input type="date" name="start_date"
                               value="{{ old('start_date', isset($income) ? $income->start_date->format('Y-m-d') : now()->format('Y-m-d')) }}"
                               required
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition">
                    </div>
                    <div x-show="isRecurring()" x-cloak>
                        <label class="block text-sm font-medium text-gray-600 mb-1.5">End Date <span
                                class="text-gray-400">(optional)</span></label>
                        <input type="date" name="end_date"
                               value="{{ old('end_date', (isset($income) && $income->end_date) ? $income->end_date->format('Y-m-d') : '') }}"
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition">
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Notes <span class="text-gray-400">(optional)</span></label>
                    <textarea name="notes" rows="3" placeholder="Any additional notes…"
                              class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition resize-none">{{ old('notes', $income->notes ?? '') }}</textarea>
                </div>

                {{-- Active toggle (edit only) --}}
                @if(isset($income))
                    <div class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Active</div>
                            <div class="text-xs text-gray-400 mt-0.5">Inactive sources are excluded from totals</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="sr-only peer"
                                {{ old('is_active', $income->is_active) ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer
                                peer-checked:after:translate-x-full peer-checked:after:border-white
                                after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                after:bg-white after:border-gray-300 after:border after:rounded-full
                                after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                    </div>
                @endif

                {{-- Share with family --}}
                @if(auth()->user()->family_id)
                    <div class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Share with Family</div>
                            <div class="text-xs text-gray-400 mt-0.5">Visible to all family members</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="is_shared" value="0">
                            <input type="checkbox" name="is_shared" value="1" class="sr-only peer"
                                {{ old('is_shared', isset($income) && $income->is_shared) ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer
                                peer-checked:after:translate-x-full peer-checked:after:border-white
                                after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                after:bg-white after:border-gray-300 after:border after:rounded-full
                                after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                    </div>
                @endif

            </div>

            {{-- Submit --}}
            <div class="flex gap-3 mt-6">
                <button type="submit"
                        class="flex-1 sm:flex-none bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl px-6 py-3 transition">
                    {{ isset($income) ? 'Save Changes' : 'Add Income' }}
                </button>
                <a href="{{ route('income.index') }}"
                   class="flex-1 sm:flex-none text-center bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl px-6 py-3 transition">
                    Cancel
                </a>
            </div>
        </form>

    </div>
@endsection

