@extends('layouts.app')
@section('title', __('messages.income'))

{{--
    Income — a strict build of the `atIncome` panel in mockup 3a.

    A 1fr / 2fr split: the "received this month" card on the left, the source
    table on the right. Emerald carries income throughout; amber appears only
    on the frequency pill, exactly as the mockup has it.
--}}

@section('content')

    @php
        $currency = auth()->user()->currency_code;
        // The mockup's four columns plus a fifth for row actions, which the
        // table in 3a doesn't show but the app needs.
        $incomeGrid = 'lg:grid lg:grid-cols-[minmax(0,1.9fr)_1.2fr_0.8fr_1fr_110px] lg:gap-3.5 lg:items-center';

        // Greek needs the genitive after "Εισπραχθέντα" — Ιουλίου, not Ιούλιος.
        // Carbon only produces it when a day number precedes the month, so take
        // the month off a day-and-month format. English is unaffected ("July").
        $monthName = \Illuminate\Support\Str::after(now()->isoFormat('D MMMM'), ' ');
    @endphp

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="flex items-end justify-between gap-5 mb-[22px] flex-wrap">
        <div>
            <div class="text-[1.6rem] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white leading-tight">
                {{ __('messages.income') }}
            </div>
            <div class="text-[0.82rem] text-gray-400 dark:text-slate-500 mt-[3px]">
                {{ __('messages.received_summary', [
                    'count' => $stats['received_count'],
                    'total' => $stats['total_sources'],
                ]) }}
            </div>
        </div>
        <a href="{{ route('income.create') }}"
           class="h-10 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-[0.82rem] font-bold whitespace-nowrap flex items-center gap-2 transition shadow-[0_6px_18px_rgba(245,158,11,0.32)]">
            <span class="material-icons-round text-base">add</span>{{ __('messages.add_income') }}
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_2fr] gap-[18px] items-start">

        {{-- ── Received this month ────────────────────────────────────── --}}
        <div class="rounded-[24px] p-[22px] border border-emerald-500/[0.26]
                    bg-linear-to-br from-emerald-500/[0.18] to-amber-500/[0.12]">
            <div class="text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-emerald-600 dark:text-emerald-400">
                {{ __('messages.received_this_month', ['month' => $monthName]) }}
            </div>
            <div class="text-[2.2rem] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white mt-1.5">
                {{ $currency }} {{ number_format($stats['received_this_month'], 2) }}
            </div>
            <div class="h-2 rounded-full bg-gray-200/70 dark:bg-slate-900/50 mt-3.5 overflow-hidden">
                <div class="h-full rounded-full bg-emerald-500 transition-[width] duration-[350ms] ease-out"
                     style="width: {{ $stats['received_pct'] }}%"></div>
            </div>
            <div class="text-[0.74rem] text-gray-500 dark:text-slate-400 mt-2.5">
                {{ __('messages.received_summary', [
                    'count' => $stats['received_count'],
                    'total' => $stats['total_sources'],
                ]) }}
                · {{ $currency }} {{ number_format($stats['monthly_income'], 2) }}/mo
            </div>
        </div>

        {{-- ── Sources ────────────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-[24px] overflow-hidden">

            {{-- Filters --}}
            <form method="GET" action="{{ route('income.index') }}"
                  class="flex flex-wrap gap-2.5 p-4 border-b border-gray-100 dark:border-slate-700" x-data>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('messages.search_income') }}"
                       class="flex-1 min-w-40 bg-gray-50 dark:bg-slate-900/50 dark:text-white border border-gray-200 dark:border-slate-700 rounded-xl px-3.5 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 dark:focus:ring-emerald-500/30 transition">
                <select name="frequency" @change="$el.form.submit()"
                        class="bg-gray-50 dark:bg-slate-900/50 dark:text-white border border-gray-200 dark:border-slate-700 rounded-xl px-3.5 py-2 text-sm outline-none focus:border-emerald-500 transition">
                    <option value="">{{ __('messages.all_frequencies') }}</option>
                    @foreach(['once','weekly','biweekly','monthly','quarterly','yearly'] as $fv)
                        <option value="{{ $fv }}" {{ request('frequency')===$fv ? 'selected' : '' }}>{{ __('messages.' . $fv) }}</option>
                    @endforeach
                </select>
                <select name="status" @change="$el.form.submit()"
                        class="bg-gray-50 dark:bg-slate-900/50 dark:text-white border border-gray-200 dark:border-slate-700 rounded-xl px-3.5 py-2 text-sm outline-none focus:border-emerald-500 transition">
                    <option value="">{{ __('messages.all_status') }}</option>
                    <option value="active" {{ request('status')==='active' ? 'selected' : '' }}>{{ __('messages.filter_active') }}</option>
                    <option value="inactive" {{ request('status')==='inactive' ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
                </select>
                @if(request()->hasAny(['search','frequency','status']))
                    <a href="{{ route('income.index') }}"
                       class="inline-flex items-center px-3.5 py-2 rounded-xl border border-gray-200 dark:border-slate-700 text-sm text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                        <span class="material-icons-round text-base">close</span>
                    </a>
                @endif
            </form>

            {{-- Column header (lg and up) --}}
            <div class="{{ $incomeGrid }} hidden px-5 py-3 bg-gray-50 dark:bg-slate-900/50 text-[0.64rem] font-bold uppercase tracking-[0.07em] text-gray-400 dark:text-slate-500">
                <div>{{ __('messages.source') }}</div>
                <div>{{ __('messages.next_payment') }}</div>
                <div>{{ __('messages.frequency') }}</div>
                <div class="text-right">{{ __('messages.amount') }}</div>
                <div></div>
            </div>

            @forelse($incomes as $income)
                @php
                    $daysUntil = $income->daysUntilNext();
                    $isOnce    = $income->frequency === 'once';
                    $isLate    = !$isOnce && $income->is_active && $daysUntil !== null && $daysUntil < 0;
                    $isSoon    = !$isOnce && !$isLate && $daysUntil !== null && $daysUntil <= 7;

                    [$stateLabel, $stateClass] = match (true) {
                        ! $income->is_active => [__('messages.inactive'), 'text-gray-400 dark:text-slate-500'],
                        $isLate => [__('messages.income_late'), 'text-red-500'],
                        $isSoon => [__('messages.income_soon'), 'text-amber-600 dark:text-amber-400'],
                        default => [$income->source ?: __('messages.income'), 'text-gray-400 dark:text-slate-500'],
                    };

                    $nextLabel = match (true) {
                        $isOnce => $income->start_date?->translatedFormat('j M Y') ?? '—',
                        $isLate => __('messages.expected_ago', ['days' => abs($daysUntil)]),
                        $daysUntil === 0 => __('messages.expected_today'),
                        $daysUntil !== null => __('messages.in_days', ['days' => $daysUntil]),
                        default => '—',
                    };

                    // Bills paid "from" this income reduce it — but only the
                    // detail page showed that, so paying against an income
                    // looked like it did nothing. Surface it here too.
                    $spentThisPeriod = $income->spentThisPeriod();
                @endphp
                <div class="flex items-center gap-3 sm:gap-4 {{ $incomeGrid }} px-4 lg:px-5 py-3.5 border-t border-gray-100 dark:border-slate-700/60 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition"
                     x-data>

                    {{-- 1 · Source --}}
                    <div class="flex items-center gap-3 flex-1 min-w-0 lg:flex-none cursor-pointer"
                         @click="window.location='{{ route('income.show', $income) }}'">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 bg-emerald-500/[0.14] text-emerald-600 dark:text-emerald-400">
                            <span class="material-icons-round text-lg">{{ $isOnce ? 'attach_money' : 'repeat' }}</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-[0.88rem] font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 truncate">
                                {{ $income->name }}
                                @if($income->is_shared)
                                    <span class="material-icons-round text-gray-300 dark:text-slate-500" style="font-size:14px;">group</span>
                                @endif
                            </div>
                            <div class="text-[0.68rem] font-semibold {{ $stateClass }} mt-px truncate">
                                {{ $stateLabel }}
                                <span class="lg:hidden font-normal">· {{ $nextLabel }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- 2 · Next payment --}}
                    <div class="hidden lg:block text-[0.8rem] font-medium text-gray-600 dark:text-slate-300">
                        {{ $nextLabel }}
                    </div>

                    {{-- 3 · Frequency — the mockup's amber pill --}}
                    <div class="hidden lg:block">
                        <span class="inline-flex px-2.5 py-1 rounded-full bg-amber-500/[0.16] text-amber-700 dark:text-amber-300 text-[0.72rem] font-semibold">
                            {{ __('messages.' . $income->frequency) }}
                        </span>
                    </div>

                    {{-- 4 · Amount --}}
                    <div class="text-right shrink-0 cursor-pointer"
                         @click="window.location='{{ route('income.show', $income) }}'">
                        <div class="text-[0.92rem] font-extrabold text-emerald-600 dark:text-emerald-400">
                            {{ $currency }} {{ number_format($income->amount, 2) }}
                        </div>
                        <div class="text-[0.68rem] text-gray-400 dark:text-slate-500">
                            {{ number_format($income->monthlyEquivalent(), 2) }}/mo
                        </div>
                        @if($spentThisPeriod > 0)
                            <div class="text-[0.68rem] font-semibold text-amber-600 dark:text-amber-400 mt-0.5">
                                −{{ $currency }} {{ number_format($spentThisPeriod, 2) }} {{ __('messages.income_spent') }}
                            </div>
                        @endif
                    </div>

                    {{-- 5 · Actions --}}
                    <div class="flex items-center gap-1.5 shrink-0 lg:justify-end" @click.stop>
                        @unless($isOnce)
                            <form method="POST" action="{{ route('income.receive', $income) }}">
                                @csrf
                                <x-icon-btn tone="pay" icon="check_circle" title="{{ __('messages.mark_received') }}" />
                            </form>
                        @endunless
                        <x-icon-btn tone="neutral" icon="edit" :href="route('income.edit', $income)"
                                    title="{{ __('messages.edit') }}" />
                        <form method="POST" action="{{ route('income.destroy', $income) }}">
                            @csrf @method('DELETE')
                            <x-icon-btn tone="danger" icon="delete" type="submit"
                                        title="{{ __('messages.delete') }}"
                                        @click="if(!confirm('{{ addslashes(__('messages.confirm_delete')) }}')) $event.preventDefault()" />
                        </form>
                    </div>
                </div>
            @empty
                <x-empty-state icon="savings" :title="__('messages.no_incomes')">
                    <x-btn :href="route('income.create')" icon="add">{{ __('messages.add_first_income') }}</x-btn>
                </x-empty-state>
            @endforelse
        </div>
    </div>

    @if($incomes->hasPages())
        <div class="mt-4">{{ $incomes->appends(request()->query())->links() }}</div>
    @endif

@endsection
