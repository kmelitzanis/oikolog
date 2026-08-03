{{--
    Total balance across the active accounts, with this month's in and out.

    Expects: $stats.
--}}
@php
    $currency = auth()->user()->currency_code;
    $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
    $symbol  = $symbols[$currency] ?? $currency;
@endphp

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
