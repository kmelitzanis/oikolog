@extends('layouts.app')
@section('title', $vehicle->name)

{{--
    Vehicle detail.

    Three stacked concerns, in the order they are asked about: what is due, what
    it has cost, and what has been done to it. Each has its own inline form —
    recording a fill-up or a service is a thing done often and in a hurry, so it
    never navigates away.
--}}

@section('content')
    @php
        $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
        $code    = auth()->user()->currency_code ?? 'EUR';
        $symbol  = $symbols[$code] ?? $code;
        $perKm   = $vehicle->costPerKm();
    @endphp

    <div class="max-w-4xl">

        {{-- ── Header ──────────────────────────────────────────────────── --}}
        <div class="flex items-start gap-3 mb-6">
            <a href="{{ route('vehicles.index') }}"
               class="mt-1 text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white truncate">{{ $vehicle->name }}</h1>
                    @if($vehicle->plate)
                        <span class="text-xs font-mono font-semibold px-2 py-0.5 rounded
                                     bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300">{{ $vehicle->plate }}</span>
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

        {{-- ── Hero: photo, odometer, running cost ─────────────────────── --}}
        <x-card flush class="mb-4 overflow-hidden">
            <div class="grid sm:grid-cols-[minmax(0,260px)_1fr]">
                <div class="h-44 sm:h-full bg-gray-100 dark:bg-slate-900 flex items-center justify-center">
                    @if($vehicle->photoUrl())
                        <img src="{{ $vehicle->photoUrl() }}" alt="" class="w-full h-full object-cover">
                    @else
                        <span class="material-icons-round text-6xl text-gray-300 dark:text-slate-600">{{ $vehicle->icon() }}</span>
                    @endif
                </div>

                <div class="p-5">
                    <div class="text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-gray-500 dark:text-slate-400">
                        {{ __('messages.vehicle_odometer') }}
                    </div>
                    <div class="flex items-end gap-3 mt-1.5">
                        <div class="text-[2.2rem] leading-none font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white">
                            {{ number_format($vehicle->odometer_km, 0, ',', '.') }}
                            <span class="text-base font-semibold text-gray-400">{{ __('messages.vehicle_odometer_short') }}</span>
                        </div>
                    </div>
                    <div class="text-xs text-gray-400 dark:text-slate-500 mt-1">
                        {{ number_format($vehicle->kmPerYear(), 0, ',', '.') }} {{ __('messages.vehicle_km_per_year') }}
                        @if($vehicle->odometer_read_at)
                            · {{ $vehicle->odometer_read_at->translatedFormat('j M Y') }}
                        @endif
                    </div>

                    {{-- Inline because the odometer changes weekly while the rest
                         of the vehicle never does. --}}
                    <form method="POST" action="{{ route('vehicles.odometer', $vehicle) }}" class="flex gap-2 mt-3">
                        @csrf
                        <input type="number" name="odometer_km" min="0" step="1" required
                               value="{{ $vehicle->odometer_km }}"
                               class="w-32 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700
                                      rounded-xl px-3 py-2 text-sm outline-none focus:border-amber-500 dark:text-white">
                        <x-btn type="submit" size="sm" variant="ghost" icon="save">
                            {{ __('messages.vehicle_update_odometer') }}
                        </x-btn>
                    </form>

                    <div class="grid grid-cols-3 gap-3 mt-5 pt-4 border-t border-gray-100 dark:border-slate-700">
                        <div>
                            <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $symbol }}{{ number_format($vehicle->yearCost(), 2) }}</div>
                            <div class="text-[0.66rem] text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_per_year') }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $symbol }}{{ number_format($vehicle->monthCost(), 2) }}</div>
                            <div class="text-[0.66rem] text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_per_month') }}</div>
                        </div>
                        <div>
                            {{-- Null, not zero: an unmeasured rate must not read
                                 like a measured one. --}}
                            <div class="text-sm font-bold text-gray-900 dark:text-white">
                                {{ $perKm === null ? '—' : $symbol . number_format($perKm, 3) }}
                            </div>
                            <div class="text-[0.66rem] text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_per_km') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- ── Reminders ───────────────────────────────────────────────── --}}
        <x-card class="mb-4" x-data="{ adding: false }">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ __('messages.vehicle_section_reminders') }}</h2>
                <button type="button" @click="adding = !adding"
                        class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline">
                    <span x-text="adding ? @js(__('messages.cancel')) : @js(__('messages.add'))"></span>
                </button>
            </div>

            <form method="POST" action="{{ route('vehicles.reminders.store', $vehicle) }}"
                  x-show="adding" x-cloak class="grid sm:grid-cols-2 gap-3 mb-4 p-3 rounded-2xl bg-gray-50 dark:bg-slate-900/50">
                @csrf
                <div class="sm:col-span-2">
                    <x-input name="label" required :placeholder="__('messages.vehicle_reminder_label_ph')" />
                </div>
                <x-input type="date" name="due_date" :placeholder="__('messages.vehicle_due_date')" />
                <x-input type="number" name="due_km" min="0" :placeholder="__('messages.vehicle_due_km')" />
                <x-input type="number" name="interval_months" min="1" :placeholder="__('messages.vehicle_repeat_months')" />
                <x-input type="number" name="interval_km" min="1" :placeholder="__('messages.vehicle_repeat_km')" />
                <div class="sm:col-span-2">
                    <x-btn type="submit" size="sm" icon="add">{{ __('messages.save') }}</x-btn>
                </div>
            </form>

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
                <div class="flex items-center gap-3 py-2.5 border-b border-gray-100 dark:border-slate-700 last:border-0">
                    <span class="w-2 h-2 rounded-full {{ $dot }} shrink-0"></span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-gray-800 dark:text-white truncate">{{ $reminder->label }}</div>
                        <div class="text-[0.7rem] {{ $txt }}">
                            @if($reminder->due_date)
                                {{ $reminder->due_date->translatedFormat('j M Y') }}
                            @endif
                            @if($reminder->due_date && $km !== null) · @endif
                            @if($km !== null)
                                {{ $km < 0
                                    ? __('messages.vehicle_overdue_by_km', ['km' => number_format(abs($km), 0, ',', '.')])
                                    : __('messages.vehicle_in_km', ['km' => number_format($km, 0, ',', '.')]) }}
                            @endif
                            @if($reminder->note) · {{ $reminder->note }} @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('vehicles.reminders.complete', [$vehicle, $reminder]) }}" class="shrink-0">
                        @csrf
                        <button type="submit" title="{{ __('messages.vehicle_mark_done') }}"
                                class="w-8 h-8 flex items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20
                                       text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 transition">
                            <span class="material-icons-round text-base">check</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('vehicles.reminders.destroy', [$vehicle, $reminder]) }}" class="shrink-0">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-xl text-gray-300 hover:text-red-500 transition">
                            <span class="material-icons-round text-base">delete</span>
                        </button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_no_reminders') }}</p>
            @endforelse
        </x-card>

        {{-- ── Running costs ───────────────────────────────────────────── --}}
        <x-card class="mb-4" x-data="{ adding: false }">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ __('messages.vehicle_section_expenses') }}</h2>
                    <p class="text-[0.7rem] text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_costs_last_year') }}</p>
                </div>
                <button type="button" @click="adding = !adding"
                        class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline">
                    <span x-text="adding ? @js(__('messages.cancel')) : @js(__('messages.vehicle_add_expense'))"></span>
                </button>
            </div>

            <form method="POST" action="{{ route('vehicles.expenses.store', $vehicle) }}"
                  x-show="adding" x-cloak class="grid sm:grid-cols-3 gap-3 mb-4 p-3 rounded-2xl bg-gray-50 dark:bg-slate-900/50">
                @csrf
                <x-input as="select" name="category">
                    @foreach(\App\Models\VehicleExpense::CATEGORIES as $c)
                        <option value="{{ $c }}">{{ __('messages.vehicle_expense_' . $c) }}</option>
                    @endforeach
                </x-input>
                <x-input type="number" name="amount" step="any" min="0" required placeholder="0.00" />
                <x-input type="date" name="spent_at" required value="{{ now()->format('Y-m-d') }}" />
                <x-input type="number" name="odometer_km" min="0" :placeholder="__('messages.vehicle_odometer')" />
                <x-input type="number" name="litres" step="any" min="0" :placeholder="__('messages.vehicle_litres')" />
                <x-btn type="submit" size="sm" icon="add">{{ __('messages.save') }}</x-btn>
            </form>

            @forelse($breakdown as $category => $row)
                @php
                    $pct = collect($breakdown)->sum('amount') > 0
                        ? round($row['amount'] / collect($breakdown)->sum('amount') * 100)
                        : 0;
                @endphp
                <div class="py-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-gray-800 dark:text-white">{{ __('messages.vehicle_expense_' . $category) }}</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $symbol }}{{ number_format($row['amount'], 2) }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
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

        {{-- ── Service history ─────────────────────────────────────────── --}}
        <x-card x-data="{ adding: false, lines: [{ what: '', cost: '' }] }">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ __('messages.vehicle_section_services') }}</h2>
                <button type="button" @click="adding = !adding"
                        class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline">
                    <span x-text="adding ? @js(__('messages.cancel')) : @js(__('messages.vehicle_add_service'))"></span>
                </button>
            </div>

            <form method="POST" action="{{ route('vehicles.services.store', $vehicle) }}"
                  x-show="adding" x-cloak class="mb-4 p-3 rounded-2xl bg-gray-50 dark:bg-slate-900/50 space-y-3">
                @csrf
                <div class="grid sm:grid-cols-4 gap-3">
                    <x-input type="date" name="performed_at" required value="{{ now()->format('Y-m-d') }}" />
                    <x-input type="number" name="odometer_km" min="0" :placeholder="__('messages.vehicle_odometer')" />
                    <x-input name="shop" :placeholder="__('messages.vehicle_service_shop')" />
                    <x-input type="number" name="total" step="any" min="0" :placeholder="__('messages.vehicle_service_total')" />
                </div>

                {{-- The receipt's lines. Added client-side because a workshop
                     bill has as many rows as it has, not a fixed number. --}}
                <template x-for="(line, i) in lines" :key="i">
                    <div class="grid grid-cols-[1fr_120px_40px] gap-2">
                        <input type="text" :name="`lines[${i}][what]`" x-model="line.what"
                               placeholder="{{ __('messages.vehicle_service_line') }}"
                               class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm outline-none focus:border-amber-500 dark:text-white">
                        <input type="number" step="any" min="0" :name="`lines[${i}][cost]`" x-model="line.cost"
                               placeholder="{{ __('messages.vehicle_service_cost') }}"
                               class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm outline-none focus:border-amber-500 dark:text-white">
                        <button type="button" @click="lines.splice(i, 1)" x-show="lines.length > 1"
                                class="text-gray-300 hover:text-red-500 transition">
                            <span class="material-icons-round text-base">close</span>
                        </button>
                    </div>
                </template>

                <div class="flex items-center gap-3">
                    <button type="button" @click="lines.push({ what: '', cost: '' })"
                            class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline">
                        + {{ __('messages.vehicle_service_line') }}
                    </button>
                    <x-btn type="submit" size="sm" icon="check" class="ms-auto">{{ __('messages.save') }}</x-btn>
                </div>
            </form>

            @forelse($services as $service)
                <div class="py-3 border-b border-gray-100 dark:border-slate-700 last:border-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <div class="min-w-0">
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">
                                {{ $service->performed_at->translatedFormat('j M Y') }}
                            </span>
                            @if($service->shop)
                                <span class="text-xs text-gray-400 dark:text-slate-500">· {{ $service->shop }}</span>
                            @endif
                            @if($service->odometer_km)
                                <span class="text-xs text-gray-400 dark:text-slate-500">
                                    · {{ number_format($service->odometer_km, 0, ',', '.') }} {{ __('messages.vehicle_odometer_short') }}
                                </span>
                            @endif
                        </div>
                        <span class="shrink-0 text-sm font-bold text-gray-900 dark:text-white">
                            {{ $symbol }}{{ number_format($service->effectiveTotal(), 2) }}
                        </span>
                    </div>
                    @if($service->lines)
                        <ul class="mt-1.5 space-y-0.5">
                            @foreach($service->lines as $line)
                                <li class="flex justify-between text-[0.72rem] text-gray-500 dark:text-slate-400">
                                    <span class="truncate">{{ $line['what'] ?? '' }}</span>
                                    <span class="shrink-0 ms-3">{{ $symbol }}{{ number_format((float) ($line['cost'] ?? 0), 2) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_no_services') }}</p>
            @endforelse
        </x-card>
    </div>
@endsection
