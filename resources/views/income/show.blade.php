@extends('layouts.app')
@section('title', $income->name)

@section('content')
    <div class="max-w-2xl">

        {{-- Back --}}
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('income.index') }}"
               class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <h1 class="text-xl font-extrabold text-gray-900 dark:text-white truncate">{{ $income->name }}</h1>
            @if(!$income->is_active)
                <span
                    class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400">Inactive</span>
            @endif
        </div>

        @php
            $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
            $symbol  = $symbols[$income->currency_code] ?? $income->currency_code;
            $daysUntil = $income->daysUntilNext();
            $isRecurring = $income->frequency !== 'once';
        @endphp

        {{-- Hero card --}}
        <div class="bg-linear-to-br from-emerald-600 to-emerald-500 rounded-2xl p-6 text-white mb-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-emerald-200 mb-1">{{ $income->source ?? 'Income' }}</div>
                    <div
                        class="text-4xl font-extrabold tracking-tight">{{ $symbol }}{{ number_format($income->amount, 2) }}</div>
                    <div class="text-sm text-emerald-300 mt-1">{{ $income->frequencyLabel() }}</div>
                    @if($isRecurring)
                        <div class="text-sm text-emerald-200 mt-2">
                            ≈ {{ $symbol }}{{ number_format($income->monthlyEquivalent(), 2) }}/mo
                            · {{ $symbol }}{{ number_format($income->monthlyEquivalent() * 12, 2) }}/yr
                        </div>
                    @endif
                </div>
                <div class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center shrink-0">
                    <span class="material-icons-round text-3xl">{{ $isRecurring ? 'repeat' : 'attach_money' }}</span>
                </div>
            </div>

            @if($isRecurring && $income->next_date && $income->is_active)
                <div class="mt-4 pt-4 border-t border-emerald-400/40">
                    @if($daysUntil < 0)
                        <div class="text-sm text-red-200">Expected {{ abs($daysUntil) }}
                            day{{ abs($daysUntil) !== 1 ? 's' : '' }} ago
                            — {{ $income->next_date->format('d M Y') }}</div>
                    @elseif($daysUntil === 0)
                        <div class="text-sm text-white font-semibold">Expected today!</div>
                    @else
                        <div class="text-sm text-emerald-200">Next expected in {{ $daysUntil }}
                            day{{ $daysUntil !== 1 ? 's' : '' }} — {{ $income->next_date->format('d M Y') }}</div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Allocation: how much of this income has been spent on bills --}}
        <x-card class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ __('messages.income_allocation') }}</h2>
                @if($periodStart)
                    <span class="text-xs text-gray-400 dark:text-slate-500">
                        {{ $periodStart->format('d M') }}@if($periodEnd) – {{ $periodEnd->copy()->subDay()->format('d M') }}@endif
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="text-center bg-gray-50 dark:bg-slate-700/40 rounded-xl py-3">
                    <div class="text-[11px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wide">{{ __('messages.income_received') }}</div>
                    <div class="text-base font-extrabold text-gray-900 dark:text-white mt-0.5">{{ $symbol }}{{ number_format($income->amount, 2) }}</div>
                </div>
                <div class="text-center bg-red-50 dark:bg-red-900/15 rounded-xl py-3">
                    <div class="text-[11px] font-semibold text-red-400 uppercase tracking-wide">{{ __('messages.income_spent') }}</div>
                    <div class="text-base font-extrabold text-red-500 dark:text-red-400 mt-0.5">{{ $symbol }}{{ number_format($spent, 2) }}</div>
                </div>
                <div class="text-center bg-emerald-50 dark:bg-emerald-900/15 rounded-xl py-3">
                    <div class="text-[11px] font-semibold text-emerald-500 uppercase tracking-wide">{{ __('messages.income_remaining') }}</div>
                    <div class="text-base font-extrabold {{ $remaining < 0 ? 'text-red-500' : 'text-emerald-600 dark:text-emerald-400' }} mt-0.5">{{ $symbol }}{{ number_format($remaining, 2) }}</div>
                </div>
            </div>

            {{-- Progress bar --}}
            <div class="h-2.5 rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500 {{ $spentPercent >= 100 ? 'bg-red-500' : 'bg-linear-to-r from-emerald-500 to-emerald-400' }}"
                     style="width: {{ $spentPercent }}%"></div>
            </div>
            <div class="text-[11px] text-gray-400 dark:text-slate-500 mt-1.5 text-right">{{ $spentPercent }}% {{ __('messages.income_spent') }}</div>

            {{-- Spending timeline --}}
            <div class="mt-5">
                <div class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-slate-500 mb-2">{{ __('messages.spending_timeline') }}</div>
                @if($periodPayments->isEmpty())
                    <div class="text-center py-6 text-sm text-gray-400 dark:text-slate-500">
                        <span class="material-icons-round text-3xl block mb-1 text-gray-300 dark:text-slate-600">savings</span>
                        {{ __('messages.no_spending_yet') }}
                    </div>
                @else
                    <ol class="relative border-l border-gray-100 dark:border-slate-700 ml-2 space-y-4">
                        @foreach($periodPayments as $p)
                            @php $cat = $p->bill?->category; @endphp
                            <li class="ml-4">
                                <span class="absolute -left-[7px] w-3.5 h-3.5 rounded-full border-2 border-white dark:border-slate-800"
                                      style="background: {{ $cat?->color_hex ?? '#ef4444' }}"></span>
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ $p->bill ? route('bills.show', $p->bill) : '#' }}"
                                           class="text-sm font-semibold text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition truncate block">
                                            {{ $p->bill?->name ?? __('messages.bill') }}
                                        </a>
                                        <div class="text-xs text-gray-400 dark:text-slate-500">
                                            {{ $p->paid_at->format('d M Y') }}
                                            @if($p->is_partial) · {{ __('messages.partial') ?? 'partial' }} @endif
                                        </div>
                                    </div>
                                    <div class="text-sm font-bold text-red-500 dark:text-red-400 shrink-0">− {{ $symbol }}{{ number_format((float)$p->amount, 2) }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </x-card>

        {{-- Details card --}}
        <x-card flush class="divide-y divide-gray-50 dark:divide-slate-700 mb-6">

            @foreach([
                ['label'=>'Frequency',     'value'=> $income->frequencyLabel()],
                ['label'=>'Start Date',    'value'=> $income->start_date->format('d M Y')],
                ['label'=>'End Date',      'value'=> $income->end_date ? $income->end_date->format('d M Y') : '—'],
                ['label'=>'Last Received', 'value'=> $income->last_received_date ? $income->last_received_date->format('d M Y') : 'Never'],
                ['label'=>'Status',        'value'=> $income->is_active ? 'Active' : 'Inactive'],
                ['label'=>'Shared',        'value'=> $income->is_shared ? 'Yes (Family)' : 'No'],
            ] as $row)
                <div class="flex items-center px-5 py-3.5 gap-4">
                    <span class="text-sm text-gray-400 dark:text-slate-500 w-32 shrink-0">{{ $row['label'] }}</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $row['value'] }}</span>
                </div>
            @endforeach

            @if($income->notes)
                <div class="px-5 py-3.5">
                    <div class="text-sm text-gray-400 dark:text-slate-500 mb-1">Notes</div>
                    <div
                        class="text-sm text-gray-700 dark:text-slate-300 whitespace-pre-line">{{ $income->notes }}</div>
                </div>
            @endif
        </x-card>

        {{-- Actions --}}
        <div class="flex flex-wrap gap-3">
            @if($isRecurring && $income->is_active)
                <form method="POST" action="{{ route('income.receive', $income) }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                        <span class="material-icons-round text-base">check_circle</span> Mark as Received
                    </button>
                </form>
            @endif

            <a href="{{ route('income.edit', $income) }}"
               class="inline-flex items-center gap-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-200 text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                <span class="material-icons-round text-base">edit</span> Edit
            </a>

            <form method="POST" action="{{ route('income.destroy', $income) }}" class="ml-auto" x-data>
                @csrf @method('DELETE')
                <button type="submit"
                        @click="if(!confirm('Delete {{ addslashes($income->name) }}?')) $event.preventDefault()"
                        class="inline-flex items-center gap-2 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                    <span class="material-icons-round text-base">delete</span> Delete
                </button>
            </form>
        </div>

    </div>
@endsection

