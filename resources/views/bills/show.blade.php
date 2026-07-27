@extends('layouts.app')
@section('title', $bill->name)

@section('content')
    <div class="max-w-2xl">

        {{-- Header --}}
        @php
            $isOverdue = $bill->next_due_date && $bill->next_due_date->isPast() && $bill->is_active;
            $daysUntil = $bill->next_due_date ? (int) now()->diffInDays($bill->next_due_date, false) : null;
            $isSoon    = !$isOverdue && $daysUntil !== null && $daysUntil <= 7 && $bill->is_active;
            $color     = $bill->category?->color_hex ?? '#6366F1';
            $statusClass = $isOverdue ? 'bg-red-100 text-red-700' : ($isSoon ? 'bg-orange-100 text-orange-700' : 'bg-indigo-100 text-indigo-700');
        @endphp

        <div class="flex items-center justify-between mb-6 gap-3 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('bills.index') }}"
                   class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0">
                    <span class="material-icons-round">arrow_back</span>
                </a>
                <h1 class="text-xl font-extrabold text-gray-900 dark:text-white truncate">{{ $bill->name }}</h1>
                @if(!$bill->is_active)
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400 shrink-0">Inactive</span>
                @endif
            </div>
            <div class="flex gap-2 shrink-0">
                <a href="{{ route('bills.edit', $bill) }}"
                   class="inline-flex items-center gap-1.5 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-200 text-sm font-semibold rounded-xl px-3 py-2 transition">
                    <span class="material-icons-round text-base">edit</span> Edit
                </a>
                <form method="POST" action="{{ route('bills.destroy', $bill) }}">
                    @csrf @method('DELETE')
                    <button type="submit"
                            onclick="return confirm('Delete this bill?')"
                            class="inline-flex items-center gap-1.5 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 text-sm font-semibold rounded-xl px-3 py-2 transition">
                        <span class="material-icons-round text-base">delete</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Amounts + Dates --}}
        <x-card class="mb-4">
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div
                    class="{{ $isOverdue ? 'bg-red-50 dark:bg-red-900/20' : ($isSoon ? 'bg-orange-50 dark:bg-orange-900/20' : 'bg-indigo-50 dark:bg-indigo-900/20') }} rounded-xl p-4">
                    <div
                        class="text-xs font-semibold {{ $isOverdue ? 'text-red-400' : ($isSoon ? 'text-orange-400' : 'text-indigo-400') }} uppercase tracking-wide mb-1">
                        Amount
                    </div>
                    <div
                        class="text-2xl font-extrabold {{ $isOverdue ? 'text-red-600 dark:text-red-400' : ($isSoon ? 'text-orange-600 dark:text-orange-400' : 'text-indigo-600 dark:text-indigo-400') }}">
                        {{ $bill->currency_code }} {{ number_format($bill->amount, 2) }}
                    </div>
                    @if($bill->hasPartialPayment())
                        <div class="mt-1.5 inline-flex items-center gap-1 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-xs font-semibold rounded-lg px-2 py-0.5">
                            <span class="material-icons-round text-xs">account_balance_wallet</span>
                            Υπόλοιπο: {{ $bill->currency_code }} {{ number_format($bill->remaining_balance, 2) }}
                        </div>
                    @endif
                </div>
                <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-4">
                    <div class="text-xs font-semibold text-emerald-400 uppercase tracking-wide mb-1">Monthly Equiv.
                    </div>
                    <div
                        class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ $bill->currency_code }} {{ number_format($bill->monthlyEquivalent(), 2) }}</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-4">
                    <div class="text-xs font-semibold text-gray-400 dark:text-slate-400 uppercase tracking-wide mb-1">
                        Next Due
                    </div>
                    <div
                        class="text-base font-bold {{ $isOverdue ? 'text-red-600 dark:text-red-400' : ($isSoon ? 'text-orange-500 dark:text-orange-400' : 'text-gray-900 dark:text-white') }}">
                        {{ $bill->next_due_date?->format('d M Y') ?? '—' }}
                    </div>
                    <div
                        class="text-xs {{ $isOverdue ? 'text-red-400' : ($isSoon ? 'text-orange-400' : 'text-gray-400 dark:text-slate-500') }} mt-0.5">
                        @if($isOverdue)
                            Overdue by {{ abs($daysUntil) }}d
                        @elseif($daysUntil === 0)
                            Today
                        @elseif($daysUntil !== null)
                            In {{ $daysUntil }} days
                        @endif
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-4">
                    <div class="text-xs font-semibold text-gray-400 dark:text-slate-400 uppercase tracking-wide mb-1">
                        Last Paid
                    </div>
                    <div
                        class="text-base font-bold {{ $bill->last_paid_date ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-slate-500' }}">
                        {{ $bill->last_paid_date?->format('d M Y') ?? 'Never' }}
                    </div>
                </div>
            </div>

            @if($bill->is_active)
                <div class="mt-4" x-data>
                    <button type="button"
                            @click="$dispatch('open-pay-modal', {
                                billName:         '{{ addslashes($bill->name) }}',
                                amount:           '{{ number_format($bill->amount, 2) }}',
                                currency:         '{{ $bill->currency_code }}',
                                payRoute:         '{{ route('bills.pay', $bill) }}',
                                costVaries:       {{ $bill->cost_varies ? 'true' : 'false' }},
                                defaultIncomeId:  '{{ $bill->default_income_id }}',
                                lastPaidAmount:   '{{ $bill->cost_varies && $payments->first() ? number_format((float)$payments->first()->amount, 2, '.', '') : '' }}',
                                remainingBalance: {{ $bill->hasPartialPayment() ? number_format($bill->remaining_balance, 2, '.', '') : 'null' }}
                            })"
                            class="w-full flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl py-3 text-sm transition">
                        <span class="material-icons-round text-lg">check_circle</span> Mark as Paid
                    </button>
                </div>
            @endif
        </x-card>

        {{-- Details --}}
        <x-card class="mb-4">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Details</h2>
            @php
                $lastPayment = $payments->first();
                $details = [
                    'Category'     => $bill->category?->name ?? '—',
                    'Provider'     => $bill->provider?->name ?? '—',
                    'Frequency'    => ucfirst($bill->frequency),
                    'Start Date'   => $bill->start_date->format('d M Y'),
                    'End Date'     => $bill->end_date ? $bill->end_date->format('d M Y') : '—',
                    'Last Paid by' => $lastPayment?->paidBy?->name ?? '—',
                    'Shared'       => $bill->is_shared ? 'Yes, with family' : 'No',
                    'Reminder'     => $bill->notify_enabled ? $bill->notify_days_before.' days before' : 'Off',
                ];
            @endphp
            @foreach($details as $key => $val)
                <div
                    class="flex items-center py-2.5 {{ !$loop->last ? 'border-b border-gray-50 dark:border-slate-700' : '' }}">
                    <span class="w-32 text-xs font-medium text-gray-400 dark:text-slate-500 shrink-0">{{ $key }}</span>
                    <span class="text-sm text-gray-800 dark:text-slate-200 font-medium">{{ $val }}</span>
                </div>
            @endforeach
            @if($bill->url)
                <div class="flex items-center py-2.5 border-t border-gray-50 dark:border-slate-700">
                    <span class="w-32 text-xs font-medium text-gray-400 dark:text-slate-500 shrink-0">Website</span>
                    <a href="{{ $bill->url }}" target="_blank"
                       class="text-sm text-indigo-600 dark:text-indigo-400 font-medium hover:underline break-all">{{ $bill->url }}</a>
                </div>
            @endif
            @if($bill->notes)
                <div class="mt-4 bg-gray-50 dark:bg-slate-700/50 rounded-xl p-4">
                    <div class="text-xs font-semibold text-gray-400 dark:text-slate-400 uppercase tracking-wide mb-1">
                        Notes
                    </div>
                    <div class="text-sm text-gray-600 dark:text-slate-300">{{ $bill->notes }}</div>
                </div>
            @endif
        </x-card>

        {{-- Payment History --}}
        <x-card>
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Payment History</h2>
            @forelse($payments as $payment)
                <div
                    class="flex items-center gap-3 py-3 {{ !$loop->last ? 'border-b border-gray-50 dark:border-slate-700' : '' }}">
                    <div
                            class="w-8 h-8 {{ $payment->is_partial ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-emerald-100 dark:bg-emerald-900/30' }} rounded-full flex items-center justify-center shrink-0">
                        <span class="material-icons-round {{ $payment->is_partial ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }} text-base">
                            {{ $payment->is_partial ? 'payments' : 'check' }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <div class="text-sm font-semibold {{ $payment->is_partial ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ $payment->currency_code }} {{ number_format($payment->amount, 2) }}
                            </div>
                            @if($payment->is_partial)
                                <span class="inline-flex items-center text-xs font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 px-1.5 py-0.5 rounded-md">Μερική</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">
                            {{ $payment->paid_at->format('d M Y, H:i') }} · {{ $payment->paidBy?->name ?? '—' }}
                            @if($payment->income)
                                · <span class="text-indigo-500 dark:text-indigo-400">{{ $payment->income->name }}</span>
                            @endif
                        </div>
                        @if($payment->notes)
                            <div class="text-xs text-gray-400 dark:text-slate-500">{{ $payment->notes }}</div>
                        @endif
                    </div>
                    @if($loop->first)
                        <form method="POST" action="{{ route('bills.unpay', $bill) }}">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    onclick="return confirm('Undo this payment?')"
                                    class="inline-flex items-center gap-1.5 bg-orange-50 dark:bg-orange-900/20 hover:bg-orange-100 dark:hover:bg-orange-900/40 text-orange-600 dark:text-orange-400 text-xs font-semibold rounded-xl px-3 py-1.5 transition shrink-0">
                                <span class="material-icons-round text-base">undo</span>
                                <span class="hidden sm:inline">Undo</span>
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-400 dark:text-slate-500 text-center py-8">No payments recorded yet.</p>
            @endforelse
        </x-card>

        {{-- Receipts / attachments --}}
        @php $receipts = method_exists($bill, 'receiptUrls') ? $bill->receiptUrls() : []; @endphp
        @if(!empty($receipts))
            <x-card class="mt-4">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Attachments</h2>
                <div class="flex gap-3 flex-wrap">
                    @foreach($receipts as $url)
                        <a href="{{ $url }}" target="_blank"
                           class="w-28 h-28 overflow-hidden rounded-lg border border-gray-100 dark:border-slate-600">
                            <img src="{{ $url }}" class="w-full h-full object-cover" alt="attachment">
                        </a>
                    @endforeach
                </div>
            </x-card>
        @endif

    </div>
@endsection
