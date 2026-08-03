{{--
    Total balance across the active accounts, with this month's in and out.

    Expects: $stats.
--}}
@php
    $currency = auth()->user()->currency_code;
    $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
    $symbol  = $symbols[$currency] ?? $currency;
    $net     = round($stats['in'] - $stats['out'], 2);
@endphp

<div class="relative overflow-hidden rounded-[24px] p-[22px] border border-emerald-500/[0.26]
            bg-linear-to-br from-emerald-500/[0.18] to-amber-500/[0.12]">

    {{-- A soft glow behind the figure, so the card reads as the page's anchor
         without needing a heavier border. --}}
    <div class="pointer-events-none absolute -top-14 -right-10 w-40 h-40 rounded-full bg-emerald-400/20 blur-3xl"></div>

    <div class="relative">
        <div class="flex items-center gap-2 text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-emerald-600 dark:text-emerald-400">
            <span class="material-icons-round" style="font-size:15px;">account_balance_wallet</span>
            {{ __('messages.total_balance') }}
        </div>

        <div class="text-[2.2rem] leading-[1.1] font-extrabold tracking-[-0.02em] mt-2 {{ $stats['total'] < 0 ? 'text-red-500' : 'text-gray-900 dark:text-white' }}">
            {{ $symbol }}{{ number_format($stats['total'], 2) }}
        </div>

        <div class="text-[0.72rem] text-gray-500 dark:text-slate-400 mt-1">
            {{ __('messages.accounts_count', ['count' => $stats['count']]) }}
        </div>

        <div class="grid grid-cols-2 gap-3 mt-[18px] pt-3.5 border-t border-emerald-500/20">
            <div>
                <div class="flex items-center gap-1 text-[0.64rem] font-semibold uppercase tracking-[0.08em] text-emerald-600/80 dark:text-emerald-400/80">
                    <span class="material-icons-round" style="font-size:13px;">south_west</span>
                    {{ __('messages.in_this_month') }}
                </div>
                <div class="text-[0.95rem] font-extrabold text-emerald-600 dark:text-emerald-400 mt-0.5 tabular-nums">
                    +{{ $symbol }}{{ number_format($stats['in'], 2) }}
                </div>
            </div>
            <div>
                <div class="flex items-center gap-1 text-[0.64rem] font-semibold uppercase tracking-[0.08em] text-red-400/90">
                    <span class="material-icons-round" style="font-size:13px;">north_east</span>
                    {{ __('messages.out_this_month') }}
                </div>
                <div class="text-[0.95rem] font-extrabold text-red-500 dark:text-red-400 mt-0.5 tabular-nums">
                    −{{ $symbol }}{{ number_format($stats['out'], 2) }}
                </div>
            </div>
        </div>

        @if($stats['in'] > 0 || $stats['out'] > 0)
            <div class="text-[0.72rem] text-gray-500 dark:text-slate-400 mt-3">
                {{ __('messages.net_this_month') }}:
                <span class="font-bold tabular-nums {{ $net < 0 ? 'text-red-500 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    {{ $net < 0 ? '−' : '+' }}{{ $symbol }}{{ number_format(abs($net), 2) }}
                </span>
            </div>
        @endif
    </div>
</div>
