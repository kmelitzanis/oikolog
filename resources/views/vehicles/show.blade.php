@extends('layouts.app')
@section('title', $vehicle->name)

{{--
    Vehicle detail.

    Top: the car and its numbers. Below, two columns from lg up — the history
    (workshop visits and loose expenses, one timeline) on the left, what is due
    and where the money went on the right. Every entry can be edited in place
    or deleted; adding and editing share the same partial forms.
--}}

@section('content')
    @php
        $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
        $code    = auth()->user()->currency_code ?? 'EUR';
        $symbol  = $symbols[$code] ?? $code;
        $perKm   = $vehicle->costPerKm();
        $kmFmt   = fn($km) => number_format((int) $km, 0, ',', '.');
        $money   = fn($v) => $symbol . number_format((float) $v, 2);
        $nextDue = $reminders->first();
        $expenseIcons = ['fuel' => 'local_gas_station', 'service' => 'build', 'insurance' => 'shield', 'tax' => 'receipt_long', 'other' => 'more_horiz'];
        $breakdownTotal = collect($breakdown)->sum('amount');
        $actionBtn = 'w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 dark:text-slate-500 hover:bg-gray-100 dark:hover:bg-slate-700 transition';
    @endphp

    <div class="max-w-6xl">

        {{-- ── Header ──────────────────────────────────────────────────── --}}
        <div class="flex items-start gap-3 mb-5">
            <a href="{{ route('vehicles.index') }}"
               class="mt-1 text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white truncate">{{ $vehicle->name }}</h1>
                    @if($vehicle->plate)
                        <span class="text-xs font-mono font-bold tracking-wider px-2 py-0.5 rounded-md border-2
                                     border-gray-300 dark:border-slate-500 text-gray-700 dark:text-slate-200">{{ $vehicle->plate }}</span>
                    @endif
                </div>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5">
                    {{ $vehicle->fullModel() }}
                    @if($vehicle->year) · {{ $vehicle->year }} @endif
                    @if($vehicle->variant) · {{ $vehicle->variant }} @endif
                </p>
            </div>
            <div class="flex gap-2 shrink-0">
                <x-btn variant="ghost" size="sm" :href="route('vehicles.edit', $vehicle)" icon="edit">
                    <span class="hidden sm:inline">{{ __('messages.edit') }}</span>
                </x-btn>
                <form method="POST" action="{{ route('vehicles.destroy', $vehicle) }}">
                    @csrf @method('DELETE')
                    <x-btn variant="danger" size="sm" type="submit" icon="delete" :title="__('messages.delete')"
                           onclick="return confirm({{ Illuminate\Support\Js::from(__('messages.confirm_delete')) }})" />
                </form>
            </div>
        </div>

        {{-- ── Hero ────────────────────────────────────────────────────── --}}
        <x-card flush class="mb-5 overflow-hidden">
            <div class="grid md:grid-cols-[minmax(0,320px)_1fr]">
                <div class="h-48 md:h-full min-h-[12rem] bg-gray-100 dark:bg-slate-900 flex items-center justify-center">
                    @if($vehicle->photoUrl())
                        <img src="{{ $vehicle->photoUrl() }}" alt="" class="w-full h-full object-cover">
                    @else
                        <span class="material-icons-round text-6xl text-gray-300 dark:text-slate-600">{{ $vehicle->icon() }}</span>
                    @endif
                </div>

                <div class="p-5 lg:p-6 flex flex-col gap-5">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <div class="text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-gray-500 dark:text-slate-400">
                                {{ __('messages.vehicle_odometer') }}
                            </div>
                            <div class="text-[2.4rem] leading-none font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white mt-1.5">
                                {{ $kmFmt($vehicle->odometer_km) }}
                                <span class="text-base font-semibold text-gray-400">{{ __('messages.vehicle_odometer_short') }}</span>
                            </div>
                            <div class="text-xs text-gray-400 dark:text-slate-500 mt-1">
                                @if(($kmPerYear = $vehicle->kmPerYear()) !== null)
                                    {{ $kmFmt($kmPerYear) }} {{ __('messages.vehicle_km_per_year') }}
                                @endif
                                @if($vehicle->odometer_read_at)
                                    {{ $kmPerYear !== null ? '·' : '' }} {{ $vehicle->odometer_read_at->translatedFormat('j M Y') }}
                                @endif
                            </div>
                        </div>

                        {{-- Inline: the odometer changes weekly, the rest never. --}}
                        <form method="POST" action="{{ route('vehicles.odometer', $vehicle) }}" class="flex gap-2">
                            @csrf
                            <input type="number" name="odometer_km" min="0" step="1" required
                                   value="{{ $vehicle->odometer_km }}" aria-label="{{ __('messages.vehicle_odometer') }}"
                                   class="w-32 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700
                                          rounded-xl px-3 py-2 text-sm outline-none focus:border-amber-500 dark:text-white">
                            <x-btn type="submit" size="sm" variant="ghost" icon="speed">
                                {{ __('messages.vehicle_update_odometer') }}
                            </x-btn>
                        </form>
                    </div>

                    <div class="grid grid-cols-3 gap-2.5">
                        @foreach([
                            [$money($vehicle->yearCost()), __('messages.vehicle_per_year')],
                            [$money($vehicle->monthCost()), __('messages.vehicle_per_month')],
                            // Null, not zero: an unmeasured rate must not read like a measured one.
                            [$perKm === null ? '—' : $symbol . number_format($perKm, 3), __('messages.vehicle_per_km')],
                        ] as [$value, $label])
                            <div class="rounded-2xl bg-gray-50 dark:bg-slate-900/60 px-3.5 py-3">
                                <div class="text-base font-extrabold text-gray-900 dark:text-white tabular-nums">{{ $value }}</div>
                                <div class="text-[0.66rem] text-gray-400 dark:text-slate-500 mt-0.5">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>

                    @if($nextDue)
                        @php $tone = $nextDue->tone(); @endphp
                        <div class="flex items-center gap-2 text-xs font-semibold rounded-xl px-3 py-2
                                    {{ $tone === 'overdue' ? 'bg-red-500/10 text-red-600 dark:text-red-400'
                                        : ($tone === 'warn' ? 'bg-orange-500/10 text-orange-600 dark:text-orange-400'
                                        : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400') }}">
                            <span class="material-icons-round text-base">notifications</span>
                            <span class="truncate">{{ __('messages.vehicle_next_due') }}: {{ $nextDue->label }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </x-card>

        <div class="grid lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">

            {{-- ── History: services and expenses, newest first ─────────── --}}
            <x-card x-data="{ adding: null }">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons-round text-amber-500 text-[20px]">history</span>
                        {{ __('messages.vehicle_section_history') }}
                    </h2>
                    <div class="flex gap-2">
                        <x-btn type="button" size="sm" variant="ghost" icon="build"
                               x-on:click="adding = adding === 'service' ? null : 'service'">{{ __('messages.vehicle_add_service') }}</x-btn>
                        <x-btn type="button" size="sm" variant="ghost" icon="add"
                               x-on:click="adding = adding === 'expense' ? null : 'expense'">{{ __('messages.vehicle_add_expense') }}</x-btn>
                    </div>
                </div>

                <div x-show="adding === 'service'" x-cloak class="mb-4">
                    @include('vehicles.partials.service-form', ['action' => route('vehicles.services.store', $vehicle), 'onCancel' => 'adding = null'])
                </div>
                <div x-show="adding === 'expense'" x-cloak class="mb-4">
                    @include('vehicles.partials.expense-form', ['action' => route('vehicles.expenses.store', $vehicle), 'onCancel' => 'adding = null'])
                </div>

                @forelse($history as $row)
                    @php
                        $item = $row['item'];
                        $isService = $row['type'] === 'service';
                        $updateRoute = $isService ? route('vehicles.services.update', [$vehicle, $item]) : route('vehicles.expenses.update', [$vehicle, $item]);
                        $deleteRoute = $isService ? route('vehicles.services.destroy', [$vehicle, $item]) : route('vehicles.expenses.destroy', [$vehicle, $item]);
                        $amount = $isService ? $item->effectiveTotal() : (float) $item->amount;
                        $icon = $isService ? 'build' : ($expenseIcons[$item->category] ?? 'payments');
                        $title = $isService ? ($item->shop ?: __('messages.vehicle_expense_service')) : __('messages.vehicle_expense_' . $item->category);
                        // Lines without a price are a checklist of what was done;
                        // printing "€0.00" against each one was noise.
                        $lines = $isService ? collect($item->lines ?? []) : collect();
                    @endphp
                    <div x-data="{ editing: false, open: false }"
                         class="py-3.5 border-t border-gray-100 dark:border-slate-700/70 first-of-type:border-t-0">
                        <div x-show="!editing" class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0
                                        {{ $isService ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400' : 'bg-sky-500/15 text-sky-600 dark:text-sky-400' }}">
                                <span class="material-icons-round text-lg">{{ $icon }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-3">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $title }}</div>
                                    <div class="text-sm font-extrabold text-gray-900 dark:text-white tabular-nums shrink-0">
                                        {{ $amount > 0 ? $money($amount) : '—' }}
                                    </div>
                                </div>
                                <div class="text-[0.72rem] text-gray-400 dark:text-slate-500 mt-0.5">
                                    {{ $row['date']?->translatedFormat('j M Y') }}
                                    @if($item->odometer_km) · {{ $kmFmt($item->odometer_km) }} {{ __('messages.vehicle_odometer_short') }} @endif
                                    @if(! $isService && $item->litres) · {{ rtrim(rtrim(number_format((float) $item->litres, 2, ',', '.'), '0'), ',') }} L @endif
                                    @if(! $isService && $item->note) · {{ $item->note }} @endif
                                </div>

                                @if($lines->isNotEmpty())
                                    <button type="button" @click="open = !open"
                                            class="mt-1.5 inline-flex items-center gap-1 text-[0.72rem] font-semibold text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200">
                                        <span class="material-icons-round text-sm transition" :class="open && 'rotate-90'">chevron_right</span>
                                        {{ trans_choice('messages.vehicle_service_items', $lines->count(), ['count' => $lines->count()]) }}
                                    </button>
                                    <ul x-show="open" x-cloak class="mt-1.5 ms-1 ps-3 border-s-2 border-gray-100 dark:border-slate-700 space-y-1">
                                        @foreach($lines as $line)
                                            <li class="flex justify-between gap-3 text-[0.74rem] text-gray-600 dark:text-slate-300">
                                                <span class="truncate">{{ $line['what'] ?? '' }}</span>
                                                @if((float) ($line['cost'] ?? 0) > 0)
                                                    <span class="shrink-0 tabular-nums text-gray-400 dark:text-slate-500">{{ $money($line['cost']) }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="flex shrink-0 -me-1">
                                <button type="button" @click="editing = true" class="{{ $actionBtn }} hover:text-amber-600"
                                        title="{{ __('messages.edit') }}" aria-label="{{ __('messages.edit') }}">
                                    <span class="material-icons-round text-[18px]">edit</span>
                                </button>
                                <form method="POST" action="{{ $deleteRoute }}"
                                      onsubmit="return confirm({{ Illuminate\Support\Js::from(__('messages.confirm_delete')) }})">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="{{ $actionBtn }} hover:text-red-500"
                                            title="{{ __('messages.delete') }}" aria-label="{{ __('messages.delete') }}">
                                        <span class="material-icons-round text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div x-show="editing" x-cloak>
                            @if($isService)
                                @include('vehicles.partials.service-form', ['action' => $updateRoute, 'service' => $item, 'onCancel' => 'editing = false'])
                            @else
                                @include('vehicles.partials.expense-form', ['action' => $updateRoute, 'expense' => $item, 'onCancel' => 'editing = false'])
                            @endif
                        </div>
                    </div>
                @empty
                    <x-empty-state quiet icon="history" :title="__('messages.vehicle_no_history')" />
                @endforelse
            </x-card>

            {{-- ── Side column ─────────────────────────────────────────── --}}
            <div class="space-y-5">

                {{-- OBD-II only applies to cars. --}}
                @if($vehicle->type === 'car')
                    @include('vehicles.partials.obd-card', ['vehicle' => $vehicle, 'scans' => $scans])
                @endif

                {{-- Reminders --}}
                <x-card x-data="{ adding: false }">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons-round text-amber-500 text-[20px]">notifications_active</span>
                        {{ __('messages.vehicle_section_reminders') }}
                    </h2>
                        <button type="button" @click="adding = !adding" title="{{ __('messages.add') }}"
                                class="{{ $actionBtn }} hover:text-amber-600">
                            <span class="material-icons-round text-[20px]">add</span>
                        </button>
                    </div>

                    <div x-show="adding" x-cloak class="mb-3">
                        @include('vehicles.partials.reminder-form', ['action' => route('vehicles.reminders.store', $vehicle), 'onCancel' => 'adding = false'])
                    </div>

                    @forelse($reminders as $reminder)
                        @php
                            $tone = $reminder->tone();
                            [$dot, $txt] = match ($tone) {
                                'overdue' => ['bg-red-500', 'text-red-600 dark:text-red-400'],
                                'warn'    => ['bg-orange-500', 'text-orange-600 dark:text-orange-400'],
                                default   => ['bg-emerald-500', 'text-gray-500 dark:text-slate-400'],
                            };
                            $km = $reminder->kmRemaining();
                        @endphp
                        <div x-data="{ editing: false }" class="py-2.5 border-t border-gray-100 dark:border-slate-700/70 first-of-type:border-t-0">
                            <div x-show="!editing" class="flex items-start gap-2.5">
                                <span class="w-2 h-2 rounded-full {{ $dot }} shrink-0 mt-1.5"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-800 dark:text-white break-words">{{ $reminder->label }}</div>
                                    <div class="text-[0.7rem] {{ $txt }} mt-0.5">
                                        @if($reminder->due_date){{ $reminder->due_date->translatedFormat('j M Y') }}@endif
                                        @if($reminder->due_date && $km !== null) · @endif
                                        @if($km !== null)
                                            {{ $km < 0
                                                ? __('messages.vehicle_overdue_by_km', ['km' => $kmFmt(abs($km))])
                                                : __('messages.vehicle_in_km', ['km' => $kmFmt($km)]) }}
                                        @endif
                                        @if($reminder->interval_months || $reminder->interval_km)
                                            <span class="material-icons-round align-middle text-[13px] ms-0.5" title="{{ __('messages.vehicle_repeats') }}">repeat</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex shrink-0 -me-1">
                                    <form method="POST" action="{{ route('vehicles.reminders.complete', [$vehicle, $reminder]) }}">
                                        @csrf
                                        <button type="submit" class="{{ $actionBtn }} hover:text-emerald-600"
                                                title="{{ __('messages.vehicle_mark_done') }}" aria-label="{{ __('messages.vehicle_mark_done') }}">
                                            <span class="material-icons-round text-[18px]">check_circle</span>
                                        </button>
                                    </form>
                                    <button type="button" @click="editing = true" class="{{ $actionBtn }} hover:text-amber-600"
                                            title="{{ __('messages.edit') }}" aria-label="{{ __('messages.edit') }}">
                                        <span class="material-icons-round text-[18px]">edit</span>
                                    </button>
                                    <form method="POST" action="{{ route('vehicles.reminders.destroy', [$vehicle, $reminder]) }}"
                                          onsubmit="return confirm({{ Illuminate\Support\Js::from(__('messages.confirm_delete')) }})">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="{{ $actionBtn }} hover:text-red-500"
                                                title="{{ __('messages.delete') }}" aria-label="{{ __('messages.delete') }}">
                                            <span class="material-icons-round text-[18px]">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div x-show="editing" x-cloak>
                                @include('vehicles.partials.reminder-form', [
                                    'action' => route('vehicles.reminders.update', [$vehicle, $reminder]),
                                    'reminder' => $reminder, 'onCancel' => 'editing = false',
                                ])
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_no_reminders') }}</p>
                    @endforelse
                </x-card>

                {{-- Where the money went --}}
                <x-card>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons-round text-amber-500 text-[20px]">pie_chart</span>
                        {{ __('messages.vehicle_section_expenses') }}
                    </h2>
                    <p class="text-[0.7rem] text-gray-400 dark:text-slate-500 mb-3">{{ __('messages.vehicle_costs_last_year') }}</p>

                    @forelse($breakdown as $category => $row)
                        @php $pct = $breakdownTotal > 0 ? round($row['amount'] / $breakdownTotal * 100) : 0; @endphp
                        <div class="py-2">
                            <div class="flex items-center justify-between text-sm gap-3">
                                <span class="flex items-center gap-2 font-semibold text-gray-800 dark:text-white min-w-0">
                                    <span class="material-icons-round text-base text-gray-400">{{ $expenseIcons[$category] ?? 'payments' }}</span>
                                    <span class="truncate">{{ __('messages.vehicle_expense_' . $category) }}</span>
                                </span>
                                <span class="font-bold text-gray-900 dark:text-white tabular-nums shrink-0">{{ $money($row['amount']) }}</span>
                            </div>
                            <div class="flex items-center gap-2 mt-1.5">
                                <div class="flex-1 h-1.5 rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden">
                                    <div class="h-full rounded-full bg-amber-500" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="text-[0.66rem] text-gray-400 dark:text-slate-500 shrink-0">
                                    {{ __('messages.vehicle_times', ['count' => $row['count']]) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_no_expenses') }}</p>
                    @endforelse
                </x-card>
            </div>
        </div>
    </div>
@endsection
