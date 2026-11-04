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

<div class="relative overflow-hidden rounded-[24px] p-[22px] mb-4 border border-emerald-500/[0.26] bg-linear-to-br from-emerald-500/[0.18] to-amber-500/[0.12]">

    {{-- A soft glow behind the figure, so the card reads as the page's anchor
         without needing a heavier border. --}}
    <div class="pointer-events-none absolute -top-14 -right-10 w-40 h-40 rounded-full bg-emerald-400/20 blur-3xl"></div>

    {{-- Laid out by the card's own width: it sits in a narrow column beside
         the income list on a laptop and spans the page on a phone. --}}
    <div class="relative @container">
        <div class="flex items-center gap-2 text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-emerald-600 dark:text-emerald-400">
            <span class="material-icons-round" style="font-size:15px;">account_balance_wallet</span>
            {{ __('messages.total_balance') }}
        </div>

        <div class="text-[2.2rem] leading-[1.1] font-extrabold tracking-[-0.02em] mt-2 {{ $stats['total'] < 0 ? 'text-red-500' : 'text-gray-900 dark:text-white' }}">
            {{ $symbol }}{{ number_format($stats['total'], 2) }}
        </div>

        <div class="text-[0.72rem] text-gray-500 dark:text-slate-400 mt-1">
            {{ trans_choice('messages.accounts_count', $stats['count'] - $stats['budget_count'], ['count' => $stats['count'] - $stats['budget_count']]) }}
        </div>

        {{-- Envelopes are allowances, not savings, so they are kept out of
             the total above and summed on their own line. --}}
        @if($stats['budget_count'] > 0)
            {{-- Label and figures on one line when they fit; when they do not,
                 the figures drop below rather than squeezing the label into a
                 column. "left / total" itself never splits. --}}
            <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-0.5 text-[0.76rem] text-gray-600 dark:text-slate-300 mt-2">
                <span class="flex items-start gap-1.5 min-w-0">
                    <span class="material-icons-round text-amber-500 shrink-0 mt-px" style="font-size:15px;">wallet</span>
                    <span>{{ __('messages.budget_left_line') }}</span>
                </span>
                <span class="shrink-0 whitespace-nowrap tabular-nums ps-[21px] @sm:ps-0">
                    <span class="font-bold">{{ $symbol }}{{ number_format($stats['budget_left'], 2) }}</span>
                    <span class="text-gray-400 dark:text-slate-500">/ {{ $symbol }}{{ number_format($stats['budget_total'], 2) }}</span>
                </span>
            </div>
        @endif

        {{-- In, out and what that leaves, side by side on one baseline. --}}
        <div class="grid grid-cols-2 @sm:grid-cols-3 gap-3 mt-[18px] pt-3.5 border-t border-emerald-500/20">
            @foreach([
                ['south_west', __('messages.in_this_month'), '+' . $symbol . number_format($stats['in'], 2),
                    'text-emerald-600/80 dark:text-emerald-400/80', 'text-emerald-600 dark:text-emerald-400'],
                ['north_east', __('messages.out_this_month'), '−' . $symbol . number_format($stats['out'], 2),
                    'text-red-400/90', 'text-red-500 dark:text-red-400'],
                ['drag_handle', __('messages.net_month_short'), ($net < 0 ? '−' : '+') . $symbol . number_format(abs($net), 2),
                    'text-gray-500 dark:text-slate-400', $net < 0 ? 'text-red-500 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'],
            ] as [$icon, $label, $value, $labelTone, $valueTone])
                <div class="min-w-0">
                    <div class="flex items-center gap-1 text-[0.64rem] font-semibold uppercase tracking-[0.08em] {{ $labelTone }}">
                        <span class="material-icons-round shrink-0" style="font-size:13px;">{{ $icon }}</span>
                        <span class="truncate">{{ $label }}</span>
                    </div>
                    <div class="text-[0.95rem] font-extrabold mt-0.5 tabular-nums whitespace-nowrap {{ $valueTone }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>
