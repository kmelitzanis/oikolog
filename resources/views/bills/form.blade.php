@extends('layouts.app')
@section('title', isset($bill) ? __('messages.edit_bill') : __('messages.add_new_bill'))
@section('content')
    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('bills.index') }}"
               class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <h1 class="text-xl font-extrabold text-gray-900 dark:text-white">
                {{ isset($bill) ? __('messages.edit_bill') : __('messages.add_new_bill') }}
            </h1>
        </div>
        {{-- Move providers JSON outside x-data to avoid HTML attribute quote conflict --}}
        <script>window.__providers = {echo "done:$?" json_encode($providers ?? [], JSON_HEX_QUOT | JSON_HEX_APOS
            )
            !!
            }
            ;</script>
        <form method="POST" enctype="multipart/form-data"
              action="{{ isset($bill) ? route('bills.update', $bill) : route('bills.store') }}"
              x-data="{
              freq: '{{ old('frequency', isset($bill) ? $bill->frequency : 'monthly') }}',
              notify: {{ old('notify_enabled', isset($bill) ? ($bill->notify_enabled ? 1 : 0) : 1) ? 'true' : 'false' }},
              notifyDays: {{ (int) old('notify_days_before', isset($bill) ? $bill->notify_days_before : 3) }},
              categoryId: '{{ old('category_id', isset($bill) ? $bill->category_id : '') }}',
              providerId: '{{ old('provider_id', isset($bill) ? $bill->provider_id : '') }}',
              allProviders: window.__providers || [],
              get providers() { return this.allProviders.filter(p => Array.isArray(p.category_ids) && p.category_ids.includes(this.categoryId)); }
          }">
            @csrf
            @if(isset($bill))
                @method('PUT')
            @endif
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6 space-y-6">
                {{-- Name --}}
                <div>
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.bill_name') }}
                        *</label>
                    <input type="text" name="name"
                           value="{{ old('name', isset($bill) ? $bill->name : '') }}"
                           placeholder="{{ __('messages.bill_name_ph') }}" required
                           class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                </div>
                {{-- Amount --}}
                <div>
                    @php
                        $curCode = isset($bill) ? $bill->currency_code : auth()->user()->currency_code;
                        $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
                        $symbol  = $symbols[$curCode] ?? $curCode;
                    @endphp
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.amount') }}
                        *</label>
                    <div class="relative">
                        <span
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 font-semibold text-sm">{{ $symbol }}</span>
                        <input type="number" name="amount" step="0.01" min="0.01"
                               value="{{ old('amount', isset($bill) ? $bill->amount : '') }}"
                               placeholder="0.00" required
                               class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl pl-10 pr-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                    </div>
                </div>
                {{-- Category --}}
                <div>
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.category') }}
                        *</label>
                    <select name="category_id" required x-model="categoryId" @change="providerId = ''"
                            class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                        <option value="">{{ __('messages.select_category') }}</option>
                        @foreach($categories as $cat)
                            <option
                                value="{{ $cat->id }}" {{ old('category_id', isset($bill) ? $bill->category_id : '') === $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Provider --}}
                <div x-show="providers.length > 0" x-cloak>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                        {{ __('messages.provider') }} <span class="text-gray-400 dark:text-slate-500">({{ __('messages.optional') }})</span>
                    </label>
                    <select name="provider_id" x-model="providerId"
                            class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                        <option value="">{{ __('messages.no_provider') }}</option>
                        <template x-for="p in providers" :key="p.id">
                            <option :value="p.id" :selected="p.id === providerId" x-text="p.name"></option>
                        </template>
                    </select>
                </div>
                {{-- Frequency --}}
                <div>
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-2">{{ __('messages.frequency') }}
                        *</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach([
                            'once'      => __('messages.once'),
                            'weekly'    => __('messages.weekly'),
                            'biweekly'  => __('messages.biweekly'),
                            'monthly'   => __('messages.monthly'),
                            'quarterly' => __('messages.quarterly'),
                            'yearly'    => __('messages.yearly'),
                        ] as $val => $lbl)
                            <label class="cursor-pointer">
                                <input type="radio" name="frequency" value="{{ $val }}" x-model="freq"
                                       class="sr-only peer">
                                <span :class="freq==='{{ $val }}'
                                       ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300'
                                       : 'border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-600 dark:text-slate-300 hover:border-gray-300'"
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
                        <label
                            class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.start_date') }}
                            *</label>
                        <input type="date" name="start_date"
                               value="{{ old('start_date', isset($bill) ? $bill->start_date->format('Y-m-d') : now()->format('Y-m-d')) }}"
                               required
                               class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                            {{ __('messages.end_date') }} <span class="text-gray-400 dark:text-slate-500">({{ __('messages.optional') }})</span>
                        </label>
                        <input type="date" name="end_date"
                               value="{{ old('end_date', (isset($bill) && $bill->end_date) ? $bill->end_date->format('Y-m-d') : '') }}"
                               class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                    </div>
                </div>
                {{-- URL --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                        {{ __('messages.service_url') }} <span class="text-gray-400 dark:text-slate-500">({{ __('messages.optional') }})</span>
                    </label>
                    <input type="url" name="url"
                           value="{{ old('url', isset($bill) ? $bill->url : '') }}"
                           placeholder="https://example.com"
                           class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                </div>
                {{-- Notes --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                        {{ __('messages.notes') }} <span class="text-gray-400 dark:text-slate-500">({{ __('messages.optional') }})</span>
                    </label>
                    <textarea name="notes" rows="3" placeholder="{{ __('messages.notes') }}…"
                              class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition resize-none">{{ old('notes', isset($bill) ? $bill->notes : '') }}</textarea>
                </div>
                {{-- Receipts — FilePond --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                        {{ __('messages.receipts') }} <span class="text-gray-400 dark:text-slate-500">({{ __('messages.optional') }})</span>
                    </label>
                    <input type="file" name="receipts[]" data-filepond="receipts" accept="image/*,application/pdf"
                           multiple>
                    @if(isset($bill) && method_exists($bill, 'receiptUrls'))
                        <div class="mt-3 flex gap-2 flex-wrap">
                            @foreach($bill->receiptUrls() as $url)
                                <a href="{{ $url }}" target="_blank"
                                   class="w-20 h-20 overflow-hidden rounded-lg border border-gray-100 dark:border-slate-600">
                                    <img src="{{ $url }}" class="w-full h-full object-cover" alt="receipt">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
                {{-- Share with family --}}
                @if(auth()->user()->family_id)
                    <div class="flex items-center justify-between bg-gray-50 dark:bg-slate-700/50 rounded-xl px-4 py-3">
                        <div>
                            <div
                                class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('messages.share_family') }}</div>
                            <div
                                class="text-xs text-gray-400 dark:text-slate-400 mt-0.5">{{ __('messages.share_family_hint') }}</div>
                        </div>
                        <input type="hidden" name="is_shared" value="0">
                        <input type="checkbox" name="is_shared" value="1"
                               {{ old('is_shared', isset($bill) ? $bill->is_shared : false) ? 'checked' : '' }}
                               class="w-5 h-5 rounded accent-indigo-600">
                    </div>
                @endif
                {{-- Notifications --}}
                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl px-4 py-4">
                    <div class="flex items-center justify-between mb-3">
                        <div
                            class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('messages.due_reminder') }}</div>
                        <input type="hidden" name="notify_enabled" value="0">
                        <input type="checkbox" name="notify_enabled" value="1" x-model="notify"
                               class="w-5 h-5 rounded accent-indigo-600">
                    </div>
                    <div x-show="notify" x-collapse>
                        <label
                            class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-2">{{ __('messages.remind_before') }}</label>
                        <div class="flex gap-2 flex-wrap">
                            @foreach([1,3,7,14] as $d)
                                <label class="cursor-pointer">
                                    <input type="radio" name="notify_days_before" value="{{ $d }}"
                                           x-model.number="notifyDays" class="sr-only">
                                    <span :class="notifyDays==={{ $d }}
                                       ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300'
                                       : 'border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-600 dark:text-slate-300'"
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
                        {{ isset($bill) ? __('messages.save_changes') : __('messages.add_bill') }}
                    </button>
                    <a href="{{ route('bills.index') }}"
                       class="flex items-center justify-center px-5 py-3 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-200 font-semibold rounded-xl text-sm transition">
                        {{ __('messages.cancel') }}
                    </a>
                </div>
            </div>
        </form>
    </div>
@endsection
