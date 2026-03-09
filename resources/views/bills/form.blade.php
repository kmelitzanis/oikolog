@extends('layouts.app')
@section('title', isset($bill) ? 'Edit Bill' : 'Add Bill')

@section('content')
    <div class="max-w-2xl">

        {{-- Back + title --}}
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('bills.index') }}" class="text-gray-400 hover:text-gray-600 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <h1 class="text-xl font-extrabold text-gray-900">
                {{ isset($bill) ? 'Edit Bill' : 'Add New Bill' }}
            </h1>
        </div>

        <form method="POST" enctype="multipart/form-data"
              action="{{ isset($bill) ? route('bills.update', $bill) : route('bills.store') }}"
              x-data="{
              freq: '{{ old('frequency', isset($bill) ? $bill->frequency : 'monthly') }}',
              notify: {{ old('notify_enabled', isset($bill) ? ($bill->notify_enabled ? 1:0) : 1) ? 'true' : 'false' }},
              notifyDays: {{ (int) old('notify_days_before', isset($bill) ? $bill->notify_days_before : 3) }}
          }">
            @csrf
            @if(isset($bill))
                @method('PUT')
            @endif

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-6">

                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Bill Name *</label>
                    <input type="text" name="name"
                           value="{{ old('name', isset($bill) ? $bill->name : '') }}"
                           placeholder="e.g. Netflix, Electricity bill" required
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                </div>

                {{-- Amount --}}
                <div>
                    @php
                        $curCode = isset($bill) ? $bill->currency_code : auth()->user()->currency_code;
                        $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
                        $symbol  = $symbols[$curCode] ?? $curCode;
                    @endphp
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Amount *</label>
                    <div class="relative">
                        <span
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-semibold text-sm">{{ $symbol }}</span>
                        <input type="number" name="amount" step="0.01" min="0.01"
                               value="{{ old('amount', isset($bill) ? $bill->amount : '') }}"
                               placeholder="0.00" required
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                    </div>
                </div>

                {{-- Category --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Category *</label>
                    <select name="category_id" required
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                        <option value="">Select category…</option>
                        @foreach($categories as $cat)
                            @php $sel = old('category_id', isset($bill) ? $bill->category_id : ''); @endphp
                            <option
                                value="{{ $cat->id }}" {{ $sel == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Frequency --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">Frequency *</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['once'=>'One-time','weekly'=>'Weekly','biweekly'=>'Bi-weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly'] as $val => $lbl)
                            <label class="cursor-pointer">
                                <input type="radio" name="frequency" value="{{ $val }}" x-model="freq" class="sr-only">
                                <span :class="freq==='{{ $val }}'
                                       ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                       : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                                      class="inline-block px-4 py-2 rounded-xl text-sm font-medium border transition select-none">
                                {{ $lbl }}
                            </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1.5">Start / First Due *</label>
                        <input type="date" name="start_date"
                               value="{{ old('start_date', isset($bill) ? $bill->start_date->format('Y-m-d') : now()->format('Y-m-d')) }}"
                               required
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1.5">End Date <span
                                class="text-gray-400">(optional)</span></label>
                        <input type="date" name="end_date"
                               value="{{ old('end_date', (isset($bill) && $bill->end_date) ? $bill->end_date->format('Y-m-d') : '') }}"
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                    </div>
                </div>

                {{-- URL --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Service URL <span
                            class="text-gray-400">(optional)</span></label>
                    <input type="url" name="url"
                           value="{{ old('url', isset($bill) ? $bill->url : '') }}"
                           placeholder="https://example.com"
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Notes <span class="text-gray-400">(optional)</span></label>
                    <textarea name="notes" rows="3" placeholder="Any additional notes…"
                              class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition resize-none">{{ old('notes', isset($bill) ? $bill->notes : '') }}</textarea>
                </div>

                {{-- Receipts / attachments --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Receipts / Attachments <span
                            class="text-gray-400">(optional)</span></label>
                    <input type="file" name="receipts[]" accept="image/*" multiple
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100">
                    @if(isset($bill) && method_exists($bill, 'receiptUrls'))
                        <div class="mt-3 flex gap-2 flex-wrap">
                            @foreach($bill->receiptUrls() as $url)
                                <a href="{{ $url }}" target="_blank"
                                   class="w-20 h-20 overflow-hidden rounded-lg border border-gray-100">
                                    <img src="{{ $url }}" class="w-full h-full object-cover" alt="receipt">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Share with family --}}
                @if(auth()->user()->family_id)
                    <div class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Share with Family</div>
                            <div class="text-xs text-gray-400 mt-0.5">Visible to all family members</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="is_shared" value="0">
                            <input type="checkbox" name="is_shared" value="1"
                                   {{ old('is_shared', isset($bill) ? $bill->is_shared : false) ? 'checked' : '' }}
                                   class="w-5 h-5 rounded accent-indigo-600">
                        </label>
                    </div>
                @endif

                {{-- Notifications --}}
                <div class="bg-gray-50 rounded-xl px-4 py-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-sm font-semibold text-gray-900">Due Date Reminder</div>
                        <input type="hidden" name="notify_enabled" value="0">
                        <input type="checkbox" name="notify_enabled" value="1"
                               x-model="notify"
                               class="w-5 h-5 rounded accent-indigo-600">
                    </div>
                    <div x-show="notify" x-collapse>
                        <label class="block text-sm font-medium text-gray-600 mb-2">Remind me before due date</label>
                        <div class="flex gap-2 flex-wrap">
                            @foreach([1,3,7,14] as $d)
                                <label class="cursor-pointer">
                                    <input type="radio" name="notify_days_before" value="{{ $d }}"
                                           x-model.number="notifyDays" class="sr-only">
                                    <span :class="notifyDays==={{ $d }}
                                           ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                           : 'border-gray-200 bg-white text-gray-600'"
                                          class="inline-block px-4 py-2 rounded-xl text-sm font-medium border transition select-none">{{ $d }}d</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="flex-1 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl py-3 text-sm transition">
                        <span class="material-icons-round text-lg">{{ isset($bill) ? 'save' : 'add' }}</span>
                        {{ isset($bill) ? 'Save Changes' : 'Add Bill' }}
                    </button>
                    <a href="{{ route('bills.index') }}"
                       class="flex items-center justify-center px-5 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-sm transition">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
@endsection
