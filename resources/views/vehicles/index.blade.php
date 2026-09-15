@extends('layouts.app')
@section('title', __('messages.vehicles'))

{{--
    Vehicles list.

    One card per vehicle, each carrying its own photo — the design's "slot per
    vehicle" — with the single most pressing reminder surfaced on the card so
    the page answers "is anything due?" without opening anything.
--}}

@section('content')
    @php
        $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
        $code    = auth()->user()->currency_code ?? 'EUR';
        $symbol  = $symbols[$code] ?? $code;
    @endphp

    <x-page-header :title="__('messages.vehicles')"
                   :subtitle="$vehicles->count() . ' ' . mb_strtolower(__('messages.vehicles'))">
        <x-btn :href="route('vehicles.create')" icon="add">{{ __('messages.add_vehicle') }}</x-btn>
    </x-page-header>

    @if($vehicles->isEmpty())
        <x-empty-state icon="directions_car"
                       :title="__('messages.no_vehicles')"
                       :text="__('messages.no_vehicles_hint')">
            <x-btn :href="route('vehicles.create')" icon="add">{{ __('messages.add_vehicle') }}</x-btn>
        </x-empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($vehicles as $vehicle)
                @php
                    $next = $vehicle->nextReminder();
                    $tone = $next?->tone();
                    [$toneRing, $toneText] = match ($tone) {
                        'overdue' => ['border-red-500/40', 'text-red-600 dark:text-red-400'],
                        'warn'    => ['border-orange-500/40', 'text-orange-600 dark:text-orange-400'],
                        default   => ['border-gray-200 dark:border-slate-700', 'text-gray-500 dark:text-slate-400'],
                    };
                    $perKm = $vehicle->costPerKm();
                @endphp

                <a href="{{ route('vehicles.show', $vehicle) }}"
                   class="block rounded-3xl border {{ $toneRing }} bg-white dark:bg-slate-800 overflow-hidden
                          hover:shadow-lg transition {{ $vehicle->is_active ? '' : 'opacity-60' }}">

                    {{-- The photo is the card. Without one the type icon fills
                         the same space, so the grid keeps its rhythm either way. --}}
                    <div class="relative h-36 bg-gray-100 dark:bg-slate-900 flex items-center justify-center">
                        @if($vehicle->photoUrl())
                            <img src="{{ $vehicle->photoUrl() }}" alt="{{ $vehicle->name }}"
                                 class="w-full h-full object-cover">
                        @else
                            <span class="material-icons-round text-5xl text-gray-300 dark:text-slate-600">{{ $vehicle->icon() }}</span>
                        @endif

                        @if($vehicle->needsAttention())
                            <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full text-[0.66rem] font-bold uppercase tracking-wide
                                         bg-white/95 dark:bg-slate-900/95 {{ $toneText }}">
                                {{ __('messages.vehicle_needs_attention') }}
                            </span>
                        @endif
                    </div>

                    <div class="p-4">
                        <div class="flex items-baseline justify-between gap-2">
                            <h2 class="font-bold text-gray-900 dark:text-white truncate">{{ $vehicle->name }}</h2>
                            @if($vehicle->plate)
                                <span class="shrink-0 text-[0.7rem] font-mono font-semibold px-1.5 py-0.5 rounded
                                             bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300">{{ $vehicle->plate }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5 truncate">
                            {{ $vehicle->fullModel() }}
                            @if($vehicle->year) · {{ $vehicle->year }} @endif
                            @if($vehicle->variant) · {{ $vehicle->variant }} @endif
                        </p>

                        <div class="flex items-end justify-between gap-3 mt-3">
                            <div>
                                <div class="text-lg font-extrabold text-gray-900 dark:text-white leading-none">
                                    {{ number_format($vehicle->odometer_km, 0, ',', '.') }}
                                    <span class="text-xs font-semibold text-gray-400">{{ __('messages.vehicle_odometer_short') }}</span>
                                </div>
                                <div class="text-[0.68rem] text-gray-400 dark:text-slate-500 mt-0.5">
                                    {{ number_format($vehicle->kmPerYear(), 0, ',', '.') }} {{ __('messages.vehicle_km_per_year') }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-bold text-gray-700 dark:text-slate-200">
                                    {{ $symbol }}{{ number_format($vehicle->monthCost(), 2) }}
                                </div>
                                <div class="text-[0.68rem] text-gray-400 dark:text-slate-500">{{ __('messages.vehicle_per_month') }}</div>
                            </div>
                        </div>

                        {{-- The one thing to know next, or silence when nothing
                             is pending — an empty reassurance line is noise. --}}
                        @if($next)
                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-slate-700 flex items-center gap-2 text-xs {{ $toneText }}">
                                <span class="material-icons-round text-sm">schedule</span>
                                <span class="font-semibold truncate">{{ $next->label }}</span>
                                <span class="ms-auto shrink-0 text-gray-400 dark:text-slate-500">
                                    @if($next->due_date)
                                        {{ $next->due_date->translatedFormat('j M Y') }}
                                    @elseif($next->due_km !== null)
                                        {{ number_format($next->due_km, 0, ',', '.') }} {{ __('messages.vehicle_odometer_short') }}
                                    @endif
                                </span>
                            </div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
