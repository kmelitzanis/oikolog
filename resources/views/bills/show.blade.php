@extends('layouts.app')
@section('title', $bill->name)

{{--
    Bill detail.

    Rebuilt onto the design system (x-page-header / x-card / x-btn / x-badge /
    x-stat-tile) to match the rest of the app, and moved off `Bill::status()` so
    the chip here always agrees with the one in the list. Every string that used
    to be hardcoded — half English, half Greek — now goes through messages.*.
--}}

@section('content')
    @php
        $status     = $bill->status();
        $daysUntil  = $bill->daysUntilDue();
        $color      = $bill->category?->color_hex ?? '#f59e0b';
        $isPartial  = $status === 'partial';
        $lastPayment = $payments->first();

        // The hero tints to the state, which is the one place a full colour wash
        // still earns its keep — there's a single bill on screen to describe.
        [$heroBg, $heroBorder] = match ($status) {
            'overdue' => ['from-red-500/[0.16] to-red-500/[0.04]', 'border-red-500/30'],
            'soon'    => ['from-orange-500/[0.16] to-orange-500/[0.04]', 'border-orange-500/30'],
            'partial' => ['from-amber-500/[0.18] to-amber-500/[0.05]', 'border-amber-500/30'],
            'paid'    => ['from-emerald-500/[0.16] to-emerald-500/[0.04]', 'border-emerald-500/30'],
            default   => ['from-slate-500/[0.10] to-slate-500/[0.03]', 'border-gray-200 dark:border-slate-700'],
        };
    @endphp

    <div class="max-w-3xl">

        {{-- ── Header ──────────────────────────────────────────────────── --}}
        <div class="flex items-start gap-3 mb-6">
            <a href="{{ route('bills.index') }}"
               class="mt-1 text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0"
               title="{{ __('messages.bills') }}">
                <span class="material-icons-round">arrow_back</span>
            </a>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white truncate">{{ $bill->name }}</h1>
                    <x-bill-status :bill="$bill" />
                </div>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5">
                    {{ $bill->category?->name ?? '—' }}
                    @if($bill->provider) · {{ $bill->provider->name }} @endif
                    · {{ __('messages.' . $bill->frequency) }}
                </p>
            </div>

            <div class="flex gap-2 shrink-0">
                <x-btn variant="ghost" size="sm" :href="route('bills.edit', $bill)" icon="edit">
                    <span class="hidden sm:inline">{{ __('messages.edit') }}</span>
                </x-btn>
                <form method="POST" action="{{ route('bills.destroy', $bill) }}">
                    @csrf @method('DELETE')
                    <x-btn variant="danger" size="sm" type="submit" icon="delete"
                           :title="__('messages.delete')"
                           onclick="return confirm({{ Illuminate\Support\Js::from(__('messages.confirm_delete')) }})" />
                </form>
            </div>
        </div>

        {{-- ── Hero: what's owed, when, and what to do about it ────────── --}}
        <div class="rounded-3xl border {{ $heroBorder }} bg-linear-to-br {{ $heroBg }} p-6 mb-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-gray-500 dark:text-slate-400">
                        {{ $isPartial ? __('messages.remaining') : __('messages.amount') }}
                    </div>
                    @if(! $isPartial && $bill->cost_varies)
                        {{-- Editable in place: this is where you record what the
                             provider billed this period, before paying it. --}}
                        <div class="mt-2">
                            <x-editable-amount :bill="$bill" size="lg" />
                        </div>
                        <div class="text-xs text-gray-500 dark:text-slate-400 mt-2">
                            {{ $bill->hasCurrentAmount()
                                ? __('messages.amount_this_period')
                                : __('messages.set_amount_hint') }}
                        </div>
                    @else
                        <div class="text-[2.4rem] leading-none font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white mt-2">
                            {{ $bill->currency_code }}
                            {{ number_format($isPartial ? $bill->getEffectiveRemainingBalance() : $bill->amount, 2) }}
                        </div>
                        @if($isPartial)
                            <div class="text-xs text-gray-500 dark:text-slate-400 mt-2">
                                {{ __('messages.amount') }}: {{ $bill->currency_code }} {{ number_format($bill->periodAmount(), 2) }}
                            </div>
                        @endif
                    @endif
                </div>

                <div class="text-right">
                    <div class="text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-gray-500 dark:text-slate-400">
                        {{ __('messages.next_due') }}
                    </div>
                    <div class="text-lg font-bold text-gray-900 dark:text-white mt-1.5">
                        {{ $bill->next_due_date?->translatedFormat('j M Y') ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                        @if($status === 'overdue')
                            {{ __('messages.overdue_by', ['days' => abs($daysUntil ?? 0)]) }}
                        @elseif($daysUntil === 0)
                            {{ __('messages.due_today') }}
                        @elseif($daysUntil !== null && $daysUntil > 0)
                            {{ __('messages.in_days', ['days' => $daysUntil]) }}
                        @endif
                    </div>
                </div>
            </div>

            @if($bill->is_active && $status === 'paid')
                {{-- Nothing is owed on this cycle. The button states that rather
                     than offering an action that would record a second payment
                     against a settled bill. --}}
                <div class="mt-6">
                    <x-btn variant="success" block type="button" icon="check_circle" disabled>
                        {{ __('messages.paid') }}
                    </x-btn>
                </div>
            @elseif($bill->is_active)
                <div class="mt-6" x-data>
                    <x-btn variant="success" block type="button" icon="check_circle"
                           @click="$dispatch('open-pay-modal', {
                               billName:         {{ Illuminate\Support\Js::from($bill->name) }},
                               amount:           '{{ number_format($bill->tracksDebt() ? min($bill->periodAmount(), max(0, (float) $bill->debt_remaining)) : $bill->periodAmount(), 2) }}',
                               currency:         '{{ $bill->currency_code }}',
                               payRoute:         '{{ route('bills.pay', $bill) }}',
                               costVaries:       {{ $bill->cost_varies ? 'true' : 'false' }},
                               defaultAccountId: '{{ $bill->default_account_id }}',
                               lastPaidAmount:   '{{ $bill->cost_varies ? number_format($bill->hasCurrentAmount() ? (float) $bill->current_amount : ($lastPayment ? (float) $lastPayment->amount : 0), 2, '.', '') : '' }}',
                               remainingBalance: {{ $bill->hasPartialPayment() ? number_format($bill->getEffectiveRemainingBalance(), 2, '.', '') : 'null' }}
                           })">
                        {{ __('messages.mark_paid') }}
                    </x-btn>
                </div>
            @endif
        </div>

        {{-- ── Debt: how far along a loan or a card balance is ─────────── --}}
        @if($bill->tracksDebt())
            @php $progress = $bill->debtProgress(); @endphp
            <x-card class="mb-4">
                <div class="flex items-end justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-gray-500 dark:text-slate-400">
                            {{ __('messages.debt_remaining') }}
                        </div>
                        <div class="text-2xl font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white mt-1.5">
                            {{ $bill->currency_code }} {{ number_format((float) $bill->debt_remaining, 2) }}
                        </div>
                    </div>
                    @if($bill->isPaidOff())
                        <x-badge tone="paid">{{ __('messages.debt_paid_off') }}</x-badge>
                    @elseif($progress !== null)
                        <div class="text-right text-xs text-gray-500 dark:text-slate-400">
                            {{ __('messages.debt_progress', [
                                'paid'  => $bill->currency_code . ' ' . number_format((float) $bill->debt_initial - (float) $bill->debt_remaining, 2),
                                'total' => $bill->currency_code . ' ' . number_format((float) $bill->debt_initial, 2),
                            ]) }}
                        </div>
                    @endif
                </div>
                @if($progress !== null)
                    <div class="mt-3 h-2 rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $progress }}%"></div>
                    </div>
                @endif
            </x-card>
        @endif

        {{-- ── Parsed from the provider's mail, awaiting review ─────────── --}}
        @foreach($bill->amountSuggestions()->pending()->latest('email_date')->get() as $suggestion)
            <div class="mb-4">
                <x-amount-suggestion :bill="$bill" :suggestion="$suggestion" />
            </div>
        @endforeach

        {{-- ── At a glance ─────────────────────────────────────────────── --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
            <x-stat-tile tone="brand"
                         :label="__('messages.monthly_equivalent')"
                         :value="$bill->currency_code . ' ' . number_format($bill->monthlyEquivalent(), 2)" />
            <x-stat-tile :tone="$lastPayment ? 'success' : 'neutral'"
                         :label="__('messages.last_paid')"
                         :value="$lastPayment?->paid_at->translatedFormat('j M Y') ?? __('messages.never_paid')"
                         :hint="$lastPayment?->paidBy?->name" />
            <x-stat-tile class="col-span-2 sm:col-span-1"
                         :label="__('messages.payment_history')"
                         :value="(string) $payments->count()" />
        </div>

        {{-- ── Details ─────────────────────────────────────────────────── --}}
        <x-card class="mb-4">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-4">{{ __('messages.details') }}</h2>
            @php
                $details = [
                    __('messages.category')   => $bill->category?->name ?? '—',
                    __('messages.provider')   => $bill->provider?->name ?? '—',
                    __('messages.frequency')  => __('messages.' . $bill->frequency),
                    __('messages.start_date') => $bill->start_date->translatedFormat('j M Y'),
                    __('messages.end_date')   => $bill->end_date?->translatedFormat('j M Y') ?? '—',
                    __('messages.share_family') => $bill->is_shared ? __('messages.shared_with_family') : __('messages.not_shared'),
                    __('messages.reminder')   => $bill->notify_enabled
                        ? __('messages.reminder_days_before', ['days' => $bill->notify_days_before])
                        : __('messages.reminder_off'),
                ];
            @endphp
            <dl class="divide-y divide-gray-50 dark:divide-slate-700">
                @foreach($details as $key => $val)
                    <div class="flex items-center gap-4 py-2.5">
                        <dt class="w-32 text-xs font-medium text-gray-400 dark:text-slate-500 shrink-0">{{ $key }}</dt>
                        <dd class="text-sm text-gray-800 dark:text-slate-200 font-medium min-w-0">{{ $val }}</dd>
                    </div>
                @endforeach
                @if($bill->url)
                    <div class="flex items-center gap-4 py-2.5">
                        <dt class="w-32 text-xs font-medium text-gray-400 dark:text-slate-500 shrink-0">{{ __('messages.website') }}</dt>
                        <dd class="min-w-0">
                            <a href="{{ $bill->url }}" target="_blank" rel="noopener noreferrer"
                               class="text-sm text-amber-700 dark:text-amber-400 font-medium hover:underline break-all">{{ $bill->url }}</a>
                        </dd>
                    </div>
                @endif
            </dl>
            @if($bill->notes)
                <div class="mt-4 bg-gray-50 dark:bg-slate-700/50 rounded-xl p-4">
                    <div class="text-xs font-semibold text-gray-400 dark:text-slate-400 uppercase tracking-wide mb-1">
                        {{ __('messages.notes') }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-slate-300 whitespace-pre-line">{{ $bill->notes }}</div>
                </div>
            @endif
        </x-card>

        {{-- ── Payment history ─────────────────────────────────────────── --}}
        <x-card>
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-4">{{ __('messages.payment_history') }}</h2>

            @forelse($payments as $payment)
                {{-- The `payment` query parameter comes from a push notification
                     deep link: scroll to that row and tint it so the reason for
                     landing here is obvious. --}}
                @php $isLinked = request('payment') === $payment->id; @endphp
                <div id="payment-{{ $payment->id }}"
                     @class([
                        'flex items-center gap-3 py-3 scroll-mt-24 transition-colors',
                        'border-b border-gray-50 dark:border-slate-700' => !$loop->last,
                        '-mx-3 px-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 ring-1 ring-amber-300 dark:ring-amber-500/40' => $isLinked,
                     ])>
                    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0
                                {{ $payment->is_partial ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-emerald-100 dark:bg-emerald-900/30' }}">
                        <span class="material-icons-round text-base {{ $payment->is_partial ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ $payment->is_partial ? 'payments' : 'check' }}
                        </span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold {{ $payment->is_partial ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ $payment->currency_code }} {{ number_format($payment->amount, 2) }}
                            </span>
                            @if($payment->is_partial)
                                <x-badge tone="partial" class="text-[0.68rem] px-2 py-0.5">{{ __('messages.partial') }}</x-badge>
                            @endif
                            @if($loop->first)
                                <x-badge tone="neutral" class="text-[0.68rem] px-2 py-0.5">{{ __('messages.latest') }}</x-badge>
                            @endif
                        </div>
                        <div class="text-xs text-gray-400 dark:text-slate-500 mt-0.5 truncate">
                            {{ $payment->paid_at->translatedFormat('j M Y, H:i') }} · {{ $payment->paidBy?->name ?? '—' }}
                            @if($payment->income)
                                · <span class="text-amber-500 dark:text-amber-400">{{ __('messages.paid_from', ['source' => $payment->income->name]) }}</span>
                            @endif
                        </div>
                        @if($payment->notes)
                            <div class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ $payment->notes }}</div>
                        @endif
                    </div>

                    {{-- Undo is offered only on the latest entry — it rolls the
                         schedule back, which only makes sense for the payment
                         that moved it. Any entry can still be deleted outright. --}}
                    <div class="flex items-center gap-1.5 shrink-0">
                        @if($loop->first)
                            <form method="POST" action="{{ route('bills.unpay', $bill) }}">
                                @csrf @method('DELETE')
                                <x-btn variant="ghost" size="sm" type="submit" icon="undo"
                                       :title="__('messages.undo_payment')"
                                       onclick="return confirm({{ Illuminate\Support\Js::from(__('messages.undo_payment') . '?') }})">
                                    <span class="hidden sm:inline">{{ __('messages.undo') }}</span>
                                </x-btn>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('bills.payments.destroy', [$bill, $payment]) }}">
                            @csrf @method('DELETE')
                            <x-icon-btn tone="danger" icon="close" type="submit"
                                        :title="__('messages.delete_payment')"
                                        onclick="return confirm({{ Illuminate\Support\Js::from(__('messages.confirm_delete_payment')) }})" />
                        </form>
                    </div>
                </div>
            @empty
                <x-empty-state quiet icon="receipt_long" :text="__('messages.no_payments_yet')" />
            @endforelse
        </x-card>

        {{-- ── Attachments ─────────────────────────────────────────────── --}}
        @php $receipts = method_exists($bill, 'receiptUrls') ? $bill->receiptUrls() : []; @endphp
        @if(!empty($receipts))
            <x-card class="mt-4">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-4">{{ __('messages.attachments') }}</h2>
                <div class="flex gap-3 flex-wrap">
                    @foreach($receipts as $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                           class="w-28 h-28 overflow-hidden rounded-xl border border-gray-100 dark:border-slate-600 hover:opacity-80 transition">
                            <img src="{{ $url }}" class="w-full h-full object-cover" alt="{{ __('messages.attachments') }}">
                        </a>
                    @endforeach
                </div>
            </x-card>
        @endif

    </div>
@endsection
