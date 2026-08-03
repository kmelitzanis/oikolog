{{--
    The accounts the user keeps money in, each with its balance and this
    month's movements.

    Expects: $rows (account / balance / movements).
--}}
@php
    $currency = auth()->user()->currency_code;
    $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
    $symbol  = $symbols[$currency] ?? $currency;
@endphp

<div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-[24px] overflow-hidden">

    <div class="flex items-center justify-between gap-3 px-4 lg:px-5 py-3.5 border-b border-gray-100 dark:border-slate-700/60">
        <div class="text-[0.9rem] font-bold text-gray-900 dark:text-white">
            {{ __('messages.accounts') }}
        </div>
        <a href="{{ route('accounts.create') }}"
           class="w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 dark:text-slate-500 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-amber-600 dark:hover:text-amber-400 transition"
           title="{{ __('messages.add_account') }}">
            <span class="material-icons-round text-lg">add</span>
        </a>
    </div>

    @forelse($rows as $row)
        @php($a = $row['account'])
        <a href="{{ route('accounts.show', $a) }}"
           class="group flex items-center gap-3 px-4 lg:px-5 py-3.5 {{ !$loop->first ? 'border-t border-gray-100 dark:border-slate-700/60' : '' }}
                  hover:bg-gray-50 dark:hover:bg-slate-700/30 transition {{ $a->is_active ? '' : 'opacity-60' }}">

            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                 style="background-color: {{ $a->color_hex }}22; color: {{ $a->color_hex }}">
                <span class="material-icons-round text-xl">{{ $a->icon }}</span>
            </div>

            <div class="min-w-0 flex-1">
                <div class="text-[0.88rem] font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 truncate">
                    {{ $a->name }}
                    @if($a->is_shared)
                        <span class="material-icons-round text-gray-300 dark:text-slate-500 shrink-0" style="font-size:14px;"
                              title="{{ __('messages.shared_with_family') }}">group</span>
                    @endif
                    @unless($a->is_active)
                        <span class="text-[0.6rem] font-bold uppercase tracking-wide text-gray-400 dark:text-slate-500 shrink-0">{{ __('messages.inactive') }}</span>
                    @endunless
                </div>

                {{-- The in/out line is only worth the room when something
                     actually moved this month. --}}
                @if($row['movements']['in'] > 0 || $row['movements']['out'] > 0)
                    <div class="flex items-center gap-2 text-[0.68rem] mt-0.5 tabular-nums">
                        @if($row['movements']['in'] > 0)
                            <span class="text-emerald-600 dark:text-emerald-400">+{{ $symbol }}{{ number_format($row['movements']['in'], 2) }}</span>
                        @endif
                        @if($row['movements']['out'] > 0)
                            <span class="text-red-500 dark:text-red-400">−{{ $symbol }}{{ number_format($row['movements']['out'], 2) }}</span>
                        @endif
                        <span class="text-gray-400 dark:text-slate-500">{{ __('messages.this_month') }}</span>
                    </div>
                @else
                    <div class="text-[0.68rem] text-gray-400 dark:text-slate-500 mt-0.5">
                        {{ __('messages.no_movements_this_month') }}
                    </div>
                @endif
            </div>

            <div class="shrink-0 flex items-center gap-1.5">
                <span class="text-[0.95rem] font-extrabold tabular-nums {{ $row['balance'] < 0 ? 'text-red-500' : 'text-gray-900 dark:text-white' }}">
                    {{ $symbol }}{{ number_format($row['balance'], 2) }}
                </span>
                <span class="material-icons-round text-gray-300 dark:text-slate-600 group-hover:text-gray-400 dark:group-hover:text-slate-400 transition"
                      style="font-size:18px;">chevron_right</span>
            </div>
        </a>
    @empty
        <div class="px-5 py-10 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-50 dark:bg-slate-900 flex items-center justify-center mx-auto mb-3">
                <span class="material-icons-round text-2xl text-gray-300 dark:text-slate-600">account_balance</span>
            </div>
            <div class="text-sm font-semibold text-gray-700 dark:text-slate-200">{{ __('messages.no_accounts') }}</div>
            <div class="text-[0.76rem] text-gray-400 dark:text-slate-500 mt-1">{{ __('messages.no_accounts_hint') }}</div>
            <a href="{{ route('accounts.create') }}"
               class="inline-flex items-center gap-2 mt-4 h-10 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-[0.82rem] font-bold transition">
                <span class="material-icons-round text-base">add</span>{{ __('messages.add_account') }}
            </a>
        </div>
    @endforelse
</div>
