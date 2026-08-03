@extends('layouts.app')
@section('title', $account->name)

{{--
    One account: its balance, the two things you can do to it by hand (move
    money to another account, record a movement), and the ledger that explains
    the balance. Every row here is why the number above it is what it is.
--}}

@section('content')
    @php
        $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
        $symbol  = $symbols[$account->currency_code] ?? $account->currency_code;
        $today   = now()->toDateString();
    @endphp

    <div x-data="{ transferOpen: false, movementOpen: false }">

        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('income.index') }}"
               class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white truncate">{{ $account->name }}</h1>
            </div>
            <x-icon-btn tone="neutral" icon="edit" :href="route('accounts.edit', $account)" title="{{ __('messages.edit') }}" />
            <form method="POST" action="{{ route('accounts.destroy', $account) }}"
                  onsubmit="return confirm('{{ addslashes(__('messages.confirm_delete')) }}')">
                @csrf @method('DELETE')
                <x-icon-btn tone="danger" icon="delete" type="submit" title="{{ __('messages.delete') }}" />
            </form>
        </div>

        {{-- Hero --}}
        <div class="rounded-2xl p-6 text-white mb-4"
             style="background: linear-gradient(135deg, {{ $account->color_hex }}, {{ $account->color_hex }}bb)">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-sm/none opacity-80 mb-2">{{ __('messages.balance') }}</div>
                    <div class="text-4xl font-extrabold tracking-tight">{{ $symbol }}{{ number_format($balance, 2) }}</div>
                    <div class="text-sm opacity-90 mt-2">
                        +{{ $symbol }}{{ number_format($movements['in'], 2) }}
                        · −{{ $symbol }}{{ number_format($movements['out'], 2) }}
                        · {{ __('messages.this_month') }}
                    </div>
                    @if($account->is_shared)
                        <div class="text-xs opacity-80 mt-2 flex items-center gap-1">
                            <span class="material-icons-round" style="font-size:14px;">group</span>
                            {{ __('messages.shared_with_family') }}
                        </div>
                    @endif
                </div>
                <div class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center shrink-0">
                    <span class="material-icons-round text-3xl">{{ $account->icon }}</span>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-wrap gap-2.5 mb-5">
            @if($targets->count() > 0)
                <button type="button" @click="transferOpen = !transferOpen; movementOpen = false"
                        class="h-11 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-sm font-bold flex items-center gap-2 transition shadow-[0_6px_18px_rgba(245,158,11,0.32)]">
                    <span class="material-icons-round text-lg">swap_horiz</span>{{ __('messages.transfer') }}
                </button>
            @endif
            <button type="button" @click="movementOpen = !movementOpen; transferOpen = false"
                    class="h-11 px-4 rounded-xl border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 text-sm font-semibold flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                <span class="material-icons-round text-lg">add</span>{{ __('messages.add_movement') }}
            </button>
        </div>

        {{-- Transfer --}}
        @if($targets->count() > 0)
            <form method="POST" action="{{ route('accounts.transfer', $account) }}"
                  x-show="transferOpen" x-cloak x-collapse
                  class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 mb-5 space-y-4">
                @csrf
                <div class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                    <span class="material-icons-round text-amber-500 text-lg">swap_horiz</span>
                    {{ __('messages.transfer_from', ['account' => $account->name]) }}
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-field :label="__('messages.transfer_to')" name="to_account_id" required>
                        <x-input as="select" name="to_account_id" id="to_account_id" required>
                            @foreach($targets as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </x-input>
                    </x-field>

                    <x-field :label="__('messages.amount')" name="amount" required>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-sm">{{ $symbol }}</span>
                            <x-input name="amount" id="amount" type="number" step="0.01" min="0.01" required class="!pl-9" placeholder="0.00" />
                        </div>
                    </x-field>

                    <x-field :label="__('messages.date')" name="occurred_at">
                        <x-input name="occurred_at" type="date" :value="$today" max="{{ $today }}" />
                    </x-field>

                    <x-field :label="__('messages.note')" name="description" optional>
                        <x-input name="description" maxlength="160" placeholder="{{ __('messages.transfer_note_placeholder') }}" />
                    </x-field>
                </div>

                <button type="submit"
                        class="h-11 px-5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-sm font-bold transition">
                    {{ __('messages.record_transfer') }}
                </button>
            </form>
        @endif

        {{-- Manual movement --}}
        <form method="POST" action="{{ route('accounts.movements.store', $account) }}"
              x-show="movementOpen" x-cloak x-collapse
              class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 mb-5 space-y-4">
            @csrf
            <div class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                <span class="material-icons-round text-amber-500 text-lg">add_circle_outline</span>
                {{ __('messages.add_movement') }}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-field :label="__('messages.direction')" name="direction" required>
                    <x-input as="select" name="direction" id="direction" required>
                        <option value="in">{{ __('messages.money_in') }}</option>
                        <option value="out">{{ __('messages.money_out') }}</option>
                    </x-input>
                </x-field>

                <x-field :label="__('messages.amount')" name="amount" required>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-slate-500 text-sm">{{ $symbol }}</span>
                        <x-input name="amount" type="number" step="0.01" min="0.01" required class="!pl-9" placeholder="0.00" />
                    </div>
                </x-field>

                <x-field :label="__('messages.date')" name="occurred_at">
                    <x-input name="occurred_at" type="date" :value="$today" max="{{ $today }}" />
                </x-field>

                <x-field :label="__('messages.note')" name="description" optional>
                    <x-input name="description" maxlength="160" />
                </x-field>
            </div>

            <button type="submit"
                    class="h-11 px-5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-sm font-bold transition">
                {{ __('messages.record_movement') }}
            </button>
        </form>

        {{-- Ledger --}}
        <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-slate-700 text-sm font-bold text-gray-900 dark:text-white">
                {{ __('messages.movements') }}
            </div>

            @forelse($transactions as $t)
                @php
                    $incoming = $t->isIncoming();
                    $label = $t->description
                        ?: ($t->income?->name ?? $t->payment?->bill?->name ?? __('messages.type_' . $t->type));
                @endphp
                <div class="flex items-center gap-3 px-5 py-3.5 {{ !$loop->first ? 'border-t border-gray-100 dark:border-slate-700/60' : '' }}">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0
                                {{ $incoming ? 'bg-emerald-500/[0.14] text-emerald-600 dark:text-emerald-400' : 'bg-red-500/[0.12] text-red-500 dark:text-red-400' }}">
                        <span class="material-icons-round text-lg">
                            @if($t->transfer_group) swap_horiz
                            @elseif($t->payment_id) receipt_long
                            @elseif($t->income_id) trending_up
                            @else {{ $incoming ? 'arrow_downward' : 'arrow_upward' }}
                            @endif
                        </span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $label }}</div>
                        <div class="text-[0.7rem] text-gray-400 dark:text-slate-500 mt-px">
                            {{ $t->occurred_at->translatedFormat('j M Y') }}
                            · {{ __('messages.type_' . $t->type) }}
                        </div>
                    </div>
                    <div class="text-sm font-bold shrink-0 {{ $incoming ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                        {{ $incoming ? '+' : '−' }}{{ $symbol }}{{ number_format((float) $t->amount, 2) }}
                    </div>
                    @unless($t->payment_id)
                        <form method="POST" action="{{ route('accounts.movements.destroy', [$account, $t]) }}"
                              onsubmit="return confirm('{{ addslashes(__('messages.confirm_delete_movement')) }}')">
                            @csrf @method('DELETE')
                            <x-icon-btn tone="danger" icon="delete" type="submit" title="{{ __('messages.delete') }}" />
                        </form>
                    @endunless
                </div>
            @empty
                <div class="px-5 py-12 text-center text-sm text-gray-400 dark:text-slate-500">
                    {{ __('messages.no_movements_yet') }}
                </div>
            @endforelse

            @if($transactions->hasPages())
                <div class="px-5 py-3 border-t border-gray-100 dark:border-slate-700">{{ $transactions->links() }}</div>
            @endif
        </div>
    </div>
@endsection
