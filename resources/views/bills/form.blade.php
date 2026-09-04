@extends('layouts.app')
@section('title', isset($bill) ? __('messages.edit_bill') : __('messages.add_new_bill'))

{{--
    Bill create / edit.

    Was a single 300-line column of 14 undifferentiated inputs with the same
    control classes hand-copied ten times. Now grouped into four titled sections
    (x-form-section) built from x-field / x-input, so the styling lives in one
    place and the form can be scanned rather than read top to bottom.
--}}

@section('content')
    @php
        $editing = isset($bill);
        $curCode = $editing ? $bill->currency_code : auth()->user()->currency_code;
        $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
        $symbol  = $symbols[$curCode] ?? $curCode;
    @endphp

    <div class="max-w-3xl">

        <div class="flex items-center gap-3 mb-6">
            <a href="{{ $editing ? route('bills.show', $bill) : route('bills.index') }}"
               class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <div class="min-w-0">
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white truncate">
                    {{ $editing ? __('messages.edit_bill') : __('messages.add_new_bill') }}
                </h1>
                @if($editing)
                    <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5 truncate">{{ $bill->name }}</p>
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

        {{-- Kept out of x-data: the JSON contains quotes that would break the
             attribute if inlined. --}}
        <script>window.__providers = @json($providers ?? []);</script>

        <form method="POST" enctype="multipart/form-data"
              action="{{ $editing ? route('bills.update', $bill) : route('bills.store') }}"
              class="space-y-4"
              x-data="{
                  freq: '{{ old('frequency', $editing ? $bill->frequency : 'monthly') }}',
                  notify: {{ old('notify_enabled', $editing ? ($bill->notify_enabled ? 1 : 0) : 1) ? 'true' : 'false' }},
                  notifyDays: {{ (int) old('notify_days_before', $editing ? $bill->notify_days_before : 3) }},
                  categoryId: '{{ old('category_id', $editing ? $bill->category_id : '') }}',
                  providerId: '{{ old('provider_id', $editing ? $bill->provider_id : '') }}',
                  costVaries: {{ old('cost_varies', $editing ? ($bill->cost_varies ? '1' : '0') : '0') ? 'true' : 'false' }},
                  allProviders: window.__providers || [],
                  get providers() {
                      return this.categoryId
                          ? this.allProviders.filter(p => !Array.isArray(p.category_ids) || p.category_ids.length === 0 || p.category_ids.includes(this.categoryId))
                          : this.allProviders;
                  }
              }">
            @csrf
            @if($editing) @method('PUT') @endif

            {{-- ── Basics ───────────────────────────────────────────────── --}}
            <x-form-section icon="receipt_long"
                            :title="__('messages.section_basics')"
                            :hint="__('messages.section_basics_hint')">

                <x-field :label="__('messages.bill_name')" name="name" required>
                    <x-input name="name" id="name" required
                             :invalid="$errors->has('name')"
                             value="{{ old('name', $editing ? $bill->name : '') }}"
                             placeholder="{{ __('messages.bill_name_ph') }}" />
                </x-field>

                {{-- Amount, with the "cost varies" switch on the label row so the
                     two read as one decision. --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5 gap-3">
                        <label for="amount" class="text-sm font-medium text-gray-600 dark:text-slate-300">
                            {{ __('messages.amount') }}
                            <span x-show="!costVaries" class="text-amber-500">*</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="hidden" name="cost_varies" value="0">
                            <input type="checkbox" name="cost_varies" value="1" x-model="costVaries" class="sr-only peer">
                            <div class="relative w-9 h-5 bg-gray-200 dark:bg-slate-600 rounded-full peer-focus:ring-2 peer-focus:ring-amber-200
                                        peer-checked:bg-amber-500 transition
                                        after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                        after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all
                                        peer-checked:after:translate-x-full"></div>
                            <span class="text-xs font-medium text-gray-500 dark:text-slate-400">{{ __('messages.cost_varies') }}</span>
                        </label>
                    </div>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 font-semibold text-sm pointer-events-none">{{ $symbol }}</span>
                        <x-input type="number" name="amount" id="amount" step="0.01" min="0"
                                 :invalid="$errors->has('amount')"
                                 class="pl-10"
                                 value="{{ old('amount', $editing ? $bill->amount : '') }}"
                                 ::placeholder="costVaries ? '{{ __('messages.estimated_optional') }}' : '0.00'"
                                 ::required="!costVaries" />
                    </div>
                    @error('amount')
                        <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                    <p x-show="costVaries" x-cloak
                       class="mt-1.5 text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1">
                        <span class="material-icons-round text-sm">info</span>
                        {{ __('messages.cost_varies_hint') }}
                    </p>
                </div>

                {{-- A loan or a card: the whole outstanding total, which each
                     payment chips away at until the bill retires itself. --}}
                <x-field :label="__('messages.debt_remaining')" name="debt_remaining" optional>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 font-semibold text-sm pointer-events-none">{{ $symbol }}</span>
                        <x-input type="number" name="debt_remaining" id="debt_remaining" step="0.01" min="0"
                                 :invalid="$errors->has('debt_remaining')"
                                 class="pl-10"
                                 value="{{ old('debt_remaining', $editing && $bill->debt_remaining !== null ? $bill->debt_remaining : '') }}"
                                 placeholder="0.00" />
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-slate-400">
                        {{ __('messages.debt_remaining_hint') }}
                    </p>
                </x-field>

                <x-field :label="__('messages.category')" name="category_id" required>
                    <x-input as="select" name="category_id" id="category_id" required
                             :invalid="$errors->has('category_id')"
                             x-model="categoryId" @change="providerId = ''">
                        <option value="">{{ __('messages.select_category') }}</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}"
                                {{ (string) old('category_id', $editing ? $bill->category_id : '') === (string) $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </x-input>
                </x-field>

                <div x-show="allProviders.length > 0" x-cloak>
                    <x-field :label="__('messages.provider')" name="provider_id" optional>
                        <x-input as="select" name="provider_id" id="provider_id" x-model="providerId">
                            <option value="">{{ __('messages.no_provider') }}</option>
                            <template x-for="p in providers" :key="p.id">
                                <option :value="p.id" :selected="p.id === providerId" x-text="p.name"></option>
                            </template>
                        </x-input>
                    </x-field>
                    <template x-if="providerId">
                        <div class="mt-2 flex items-center gap-2"
                             x-show="allProviders.find(p => p.id === providerId)?.logo_url" x-cloak>
                            <img :src="allProviders.find(p => p.id === providerId)?.logo_url" alt=""
                                 class="w-8 h-8 object-contain rounded-lg border border-gray-100 dark:border-slate-600 bg-white dark:bg-slate-700 p-0.5">
                            <span class="text-xs text-gray-400 dark:text-slate-500"
                                  x-text="allProviders.find(p => p.id === providerId)?.name"></span>
                        </div>
                    </template>
                </div>

                @isset($accounts)
                    @if($accounts->count() > 0)
                        <x-field :label="__('messages.default_account')" name="default_account_id" optional
                                 :hint="__('messages.default_account_hint')">
                            <x-input as="select" name="default_account_id" id="default_account_id">
                                <option value="">{{ __('messages.no_default_account') }}</option>
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}"
                                        {{ (string) old('default_account_id', $bill->default_account_id ?? '') === (string) $acc->id ? 'selected' : '' }}>
                                        {{ $acc->name }}
                                    </option>
                                @endforeach
                            </x-input>
                        </x-field>
                    @endif
                @endisset
            </x-form-section>

            {{-- ── Schedule ─────────────────────────────────────────────── --}}
            <x-form-section icon="event_repeat"
                            :title="__('messages.section_schedule')"
                            :hint="__('messages.section_schedule_hint')">

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
                </x-field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-field :label="__('messages.start_date')" name="start_date" required>
                        <x-input type="date" name="start_date" id="start_date" required
                                 :invalid="$errors->has('start_date')"
                                 value="{{ old('start_date', $editing ? $bill->start_date->format('Y-m-d') : now()->format('Y-m-d')) }}" />
                    </x-field>
                    <x-field :label="__('messages.end_date')" name="end_date" optional>
                        <x-input type="date" name="end_date" id="end_date"
                                 :invalid="$errors->has('end_date')"
                                 value="{{ old('end_date', ($editing && $bill->end_date) ? $bill->end_date->format('Y-m-d') : '') }}" />
                    </x-field>
                </div>
            </x-form-section>

            {{-- ── Extras ───────────────────────────────────────────────── --}}
            <x-form-section icon="attach_file"
                            :title="__('messages.section_extras')"
                            :hint="__('messages.section_extras_hint')">

                <x-field :label="__('messages.service_url')" name="url" optional>
                    <x-input type="url" name="url" id="url"
                             :invalid="$errors->has('url')"
                             value="{{ old('url', $editing ? $bill->url : '') }}"
                             placeholder="https://example.com" />
                </x-field>

                <x-field :label="__('messages.notes')" name="notes" optional>
                    <x-input as="textarea" name="notes" id="notes" rows="3"
                             placeholder="{{ __('messages.notes') }}…">{{ old('notes', $editing ? $bill->notes : '') }}</x-input>
                </x-field>

                <x-field :label="__('messages.receipts')" optional>
                    <div id="receipts-drop-area"
                         class="w-full flex flex-col items-center justify-center border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-xl p-5 bg-gray-50 dark:bg-slate-700 cursor-pointer transition hover:border-amber-400">
                        <span class="material-icons-round text-3xl text-gray-300 dark:text-slate-500 mb-1">cloud_upload</span>
                        <span id="receipts-drop-text" class="text-gray-400 dark:text-slate-500 text-xs text-center">
                            {{ __('messages.receipts_drop_hint') }}
                        </span>
                        <input id="receipts-input" type="file" name="receipts[]" accept="image/*,application/pdf"
                               class="hidden" multiple>
                        <div id="receipts-preview" class="flex flex-wrap gap-2 mt-2"></div>
                    </div>
                    @if($editing && method_exists($bill, 'receiptUrls') && !empty($bill->receiptUrls()))
                        <div class="mt-3 flex gap-2 flex-wrap">
                            @foreach($bill->receiptUrls() as $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                   class="w-20 h-20 overflow-hidden rounded-lg border border-gray-100 dark:border-slate-600">
                                    <img src="{{ $url }}" class="w-full h-full object-cover" alt="{{ __('messages.receipts') }}">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </x-field>
            </x-form-section>

            {{-- ── Settings ─────────────────────────────────────────────── --}}
            <x-form-section icon="tune"
                            :title="__('messages.section_settings')"
                            :hint="__('messages.section_settings_hint')">

                @if(auth()->user()->family_id)
                    <div class="flex items-center justify-between gap-4 bg-gray-50 dark:bg-slate-700/50 rounded-xl px-4 py-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('messages.share_family') }}</div>
                            <div class="text-xs text-gray-400 dark:text-slate-400 mt-0.5">{{ __('messages.share_family_hint') }}</div>
                        </div>
                        <input type="hidden" name="is_shared" value="0">
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input type="checkbox" name="is_shared" value="1" class="sr-only peer"
                                   {{ old('is_shared', $editing ? $bill->is_shared : false) ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 dark:bg-slate-600 rounded-full peer transition
                                        peer-focus:ring-2 peer-focus:ring-emerald-200 peer-checked:bg-emerald-500
                                        after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                        after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                                        peer-checked:after:translate-x-full"></div>
                        </label>
                    </div>
                @endif

                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl px-4 py-4">
                    <div class="flex items-center justify-between gap-4 mb-1">
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('messages.due_reminder') }}</div>
                        <input type="hidden" name="notify_enabled" value="0">
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input type="checkbox" name="notify_enabled" value="1" x-model="notify" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 dark:bg-slate-600 rounded-full peer transition
                                        peer-focus:ring-2 peer-focus:ring-emerald-200 peer-checked:bg-emerald-500
                                        after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                        after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                                        peer-checked:after:translate-x-full"></div>
                        </label>
                    </div>
                    <div x-show="notify" x-collapse x-cloak>
                        <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-2 mt-3">{{ __('messages.remind_before') }}</label>
                        <div class="flex gap-2 flex-wrap">
                            @foreach([1,3,7,14] as $d)
                                <label class="cursor-pointer">
                                    <input type="radio" name="notify_days_before" value="{{ $d }}"
                                           x-model.number="notifyDays" class="sr-only peer">
                                    <span :class="notifyDays === {{ $d }}
                                              ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                              : 'border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-600 dark:text-slate-300'"
                                          class="inline-block px-4 py-2 rounded-xl text-sm font-medium border transition select-none peer-focus:ring-2 peer-focus:ring-amber-200">{{ $d }}d</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-form-section>

            {{-- ── Submit ───────────────────────────────────────────────── --}}
            <div class="flex gap-3 pt-1">
                <x-btn type="submit" size="lg" class="flex-1" :icon="$editing ? 'save' : 'add'">
                    {{ $editing ? __('messages.save_changes') : __('messages.add_bill') }}
                </x-btn>
                <x-btn variant="ghost" size="lg" :href="$editing ? route('bills.show', $bill) : route('bills.index')">
                    {{ __('messages.cancel') }}
                </x-btn>
            </div>
        </form>
    </div>
@endsection
