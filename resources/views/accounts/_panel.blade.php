{{--
    The accounts panel: total balance across the active accounts, and one row
    per account. Rendered inside the income page — accounts and income are the
    same subject, and "λογαριασμοί" already means bills in the Greek menu.

    Expects: $rows (account / balance / movements), $stats.
--}}
@php
    $currency = auth()->user()->currency_code;
    $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
    $symbol  = $symbols[$currency] ?? $currency;
@endphp

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_2fr] gap-[18px] items-start">

        {{-- ── Total ──────────────────────────────────────────────────── --}}
        <div class="rounded-[24px] p-[22px] border border-emerald-500/[0.26]
                    bg-linear-to-br from-emerald-500/[0.18] to-amber-500/[0.12]">
            <div class="text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-emerald-600 dark:text-emerald-400">
                {{ __('messages.total_balance') }}
            </div>
            <div class="text-[2.2rem] font-extrabold tracking-[-0.02em] mt-1.5 {{ $stats['total'] < 0 ? 'text-red-500' : 'text-gray-900 dark:text-white' }}">
                {{ $symbol }}{{ number_format($stats['total'], 2) }}
            </div>
            <div class="grid grid-cols-2 gap-3 mt-4 pt-3.5 border-t border-emerald-500/20">
                <div>
                    <div class="text-[0.66rem] font-semibold uppercase tracking-[0.08em] text-emerald-500">
                        {{ __('messages.in_this_month') }}
                    </div>
                    <div class="text-[0.95rem] font-extrabold text-emerald-600 dark:text-emerald-400 mt-0.5">
                        +{{ $symbol }}{{ number_format($stats['in'], 2) }}
                    </div>
                </div>
                <div>
                    <div class="text-[0.66rem] font-semibold uppercase tracking-[0.08em] text-red-400">
                        {{ __('messages.out_this_month') }}
                    </div>
                    <div class="text-[0.95rem] font-extrabold text-red-500 dark:text-red-400 mt-0.5">
                        −{{ $symbol }}{{ number_format($stats['out'], 2) }}
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Accounts ───────────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-[24px] overflow-hidden">
            @forelse($rows as $row)
                @php($a = $row['account'])
                <div class="flex items-center gap-3 sm:gap-4 px-4 lg:px-5 py-3.5 {{ !$loop->first ? 'border-t border-gray-100 dark:border-slate-700/60' : '' }}
                            hover:bg-gray-50 dark:hover:bg-slate-700/30 transition {{ $a->is_active ? '' : 'opacity-60' }}">

                    <div class="flex items-center gap-3 flex-1 min-w-0 cursor-pointer"
                         onclick="window.location='{{ route('accounts.show', $a) }}'">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                             style="background-color: {{ $a->color_hex }}22; color: {{ $a->color_hex }}">
                            <span class="material-icons-round text-xl">{{ $a->icon }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-[0.9rem] font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 truncate">
                                {{ $a->name }}
                                @if($a->is_shared)
                                    <span class="material-icons-round text-gray-300 dark:text-slate-500" style="font-size:14px;">group</span>
                                @endif
                                @unless($a->is_active)
                                    <span class="text-[0.62rem] font-bold uppercase tracking-wide text-gray-400 dark:text-slate-500">{{ __('messages.inactive') }}</span>
                                @endunless
                            </div>
                            <div class="text-[0.7rem] text-gray-400 dark:text-slate-500 mt-px">
                                +{{ $symbol }}{{ number_format($row['movements']['in'], 2) }}
                                · −{{ $symbol }}{{ number_format($row['movements']['out'], 2) }}
                                <span class="hidden sm:inline">· {{ __('messages.this_month') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-right shrink-0 cursor-pointer"
                         onclick="window.location='{{ route('accounts.show', $a) }}'">
                        <div class="text-[0.95rem] font-extrabold {{ $row['balance'] < 0 ? 'text-red-500' : 'text-gray-900 dark:text-white' }}">
                            {{ $symbol }}{{ number_format($row['balance'], 2) }}
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <x-icon-btn tone="neutral" icon="edit" :href="route('accounts.edit', $a)"
                                    title="{{ __('messages.edit') }}" />
                    </div>
                </div>
            @empty
                <div class="px-5 py-14 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-gray-50 dark:bg-slate-900 flex items-center justify-center mx-auto mb-3">
                        <span class="material-icons-round text-2xl text-gray-300 dark:text-slate-600">account_balance</span>
                    </div>
                    <div class="text-sm font-semibold text-gray-700 dark:text-slate-200">{{ __('messages.no_accounts') }}</div>
                    <div class="text-[0.78rem] text-gray-400 dark:text-slate-500 mt-1 max-w-sm mx-auto">{{ __('messages.no_accounts_hint') }}</div>
                    <a href="{{ route('accounts.create') }}"
                       class="inline-flex items-center gap-2 mt-4 h-10 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-[0.82rem] font-bold transition">
                        <span class="material-icons-round text-base">add</span>{{ __('messages.add_account') }}
                    </a>
                </div>
            @endforelse
        </div>
    </div>
