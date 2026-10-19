{{--
    OBD-II diagnostics for a car: scan over Web Bluetooth (Chrome on Android /
    desktop), or type codes in by hand. A scan is reviewed before it is saved.
    Expects: $vehicle, $scans.
--}}
@php
    $latest = $scans->first();
    $older  = $scans->slice(1);
    $units  = \App\Models\VehicleScan::READINGS;
    $sevTone = [
        'high'   => 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/30',
        'medium' => 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/30',
        'low'    => 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/30',
    ];
    $fmt = fn($key, $v) => (in_array($key, ['battery_v', 'module_v']) ? number_format((float) $v, 1, ',', '.')
        : number_format((float) $v, in_array($key, ['stft_pct', 'ltft_pct']) ? 1 : 0, ',', '.')) . ' ' . $units[$key];
    $labels = [
        'choose'     => __('messages.obd_step_choose'),
        'connecting' => __('messages.obd_step_connecting'),
        'init'       => __('messages.obd_step_init'),
        'protocol'   => __('messages.obd_step_protocol'),
        'codes'      => __('messages.obd_step_codes'),
        'readings'   => __('messages.obd_step_readings'),
        'cancelled'  => __('messages.obd_err_cancelled'),
        'unsupported'=> __('messages.obd_err_unsupported'),
        'ignition'   => __('messages.obd_err_ignition'),
        'timeout'    => __('messages.obd_err_timeout'),
        'failed'     => __('messages.obd_err_failed'),
        'saveFailed' => __('messages.obd_err_save'),
    ];
    $readingLabels = collect(array_keys($units))->mapWithKeys(fn($k) => [$k => __('messages.obd_r_' . $k)])->all();
@endphp

{{-- x-data lives on a plain div: Blade directives such as @js are not
     compiled inside a component tag's attributes. --}}
<div x-data="Object.assign(obdScanner({ url: @js(route('vehicles.scans.store', $vehicle)), csrf: @js(csrf_token()), labels: @js($labels) }), { manual: false, units: @js($units), names: @js($readingLabels) })">
<x-card>
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <span class="material-icons-round text-amber-500 text-[20px]">monitor_heart</span>
            {{ __('messages.obd_title') }}
        </h2>
        <button type="button" @click="manual = !manual" class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline"
                x-text="manual ? @js(__('messages.cancel')) : @js(__('messages.obd_manual'))"></button>
    </div>

    {{-- Scan button / progress --}}
    <div x-show="!manual && (phase === 'idle' || phase === 'error')">
        <template x-if="supported">
            <x-btn type="button" block icon="bluetooth_searching" x-on:click="scan()">{{ __('messages.obd_scan') }}</x-btn>
        </template>
        <template x-if="!supported">
            <p class="text-xs text-gray-500 dark:text-slate-400 rounded-xl bg-gray-50 dark:bg-slate-900/60 p-3 flex gap-2">
                <span class="material-icons-round text-base text-gray-400">info</span>
                <span>{{ __('messages.obd_unsupported_browser') }}</span>
            </p>
        </template>
        <p class="text-[0.7rem] text-gray-400 dark:text-slate-500 mt-2" x-show="supported">{{ __('messages.obd_hint') }}</p>
        <p class="text-xs text-red-500 mt-2" x-show="error" x-text="error"></p>
    </div>

    <div x-show="phase === 'connecting' || phase === 'scanning'" x-cloak
         class="flex items-center gap-3 rounded-xl bg-amber-500/10 text-amber-700 dark:text-amber-300 p-3 text-sm font-semibold">
        <span class="material-icons-round animate-spin text-[20px]">progress_activity</span>
        <span x-text="step"></span>
    </div>

    {{-- Review before saving --}}
    <template x-if="result && (phase === 'review' || phase === 'saving')">
        <div class="rounded-2xl border border-gray-100 dark:border-slate-700 p-3 space-y-3">
            <div class="flex items-center gap-2 text-sm font-semibold"
                 :class="result.mil_on ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
                <span class="material-icons-round text-[20px]" x-text="result.mil_on ? 'warning' : 'check_circle'"></span>
                <span x-text="result.codes.length
                    ? @js(__('messages.obd_found_codes')).replace(':count', result.codes.length)
                    : @js(__('messages.obd_no_codes'))"></span>
            </div>
            <div class="flex flex-wrap gap-1.5" x-show="result.codes.length">
                <template x-for="c in result.codes" :key="c.code + c.status">
                    <span class="font-mono text-xs font-bold px-2 py-1 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-white"
                          x-text="c.code + (c.status !== 'stored' ? ' · ' + c.status : '')"></span>
                </template>
            </div>
            <div class="grid grid-cols-2 gap-1.5 text-xs">
                <template x-for="[k, v] in Object.entries(result.readings)" :key="k">
                    <div class="rounded-lg bg-gray-50 dark:bg-slate-900/60 px-2.5 py-1.5">
                        <div class="text-gray-400 dark:text-slate-500 text-[0.66rem]" x-text="names[k] || k"></div>
                        <div class="font-bold text-gray-900 dark:text-white tabular-nums" x-text="v + ' ' + (units[k] || '')"></div>
                    </div>
                </template>
            </div>
            <p class="text-[0.7rem] text-gray-400" x-show="result.vin || result.odometer_km">
                <span x-show="result.vin" x-text="'VIN ' + result.vin"></span>
                <span x-show="result.odometer_km" x-text="' · ' + result.odometer_km + ' km'"></span>
            </p>
            <p class="text-xs text-red-500" x-show="error" x-text="error"></p>
            <div class="flex gap-2 justify-end">
                <x-btn type="button" variant="ghost" size="sm" x-on:click="reset()">{{ __('messages.obd_discard') }}</x-btn>
                <x-btn type="button" size="sm" icon="save" x-on:click="save()" ::disabled="phase === 'saving'">{{ __('messages.obd_save') }}</x-btn>
            </div>
        </div>
    </template>

    {{-- Manual entry: any scanner, any phone --}}
    <form x-show="manual" x-cloak method="POST" action="{{ route('vehicles.scans.store', $vehicle) }}"
          class="space-y-2.5 p-3 rounded-2xl bg-gray-50 dark:bg-slate-900/60">
        @csrf
        <input type="hidden" name="source" value="manual">
        <x-input name="codes_text" required :placeholder="__('messages.obd_codes_ph')" class="font-mono uppercase" />
        <div class="grid grid-cols-2 gap-2.5">
            <x-input type="number" name="odometer_km" min="0" :placeholder="__('messages.vehicle_odometer')" />
            <x-input type="number" step="0.1" name="readings[battery_v]" min="0" max="20" :placeholder="__('messages.obd_r_battery_v') . ' (V)'" />
        </div>
        <x-input name="note" maxlength="255" :placeholder="__('messages.note')" />
        @error('codes') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        @foreach($errors->get('codes.*') as $msgs) <p class="text-xs text-red-500">{{ $msgs[0] }}</p> @endforeach
        <div class="flex justify-end"><x-btn type="submit" size="sm" icon="check">{{ __('messages.save') }}</x-btn></div>
    </form>

    {{-- Latest scan, in full --}}
    @if($latest)
        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-700/70">
            <div class="flex items-center justify-between gap-2 text-[0.72rem] text-gray-400 dark:text-slate-500 mb-2">
                <span class="flex items-center gap-1.5">
                    <span class="material-icons-round text-sm">{{ $latest->source === 'bluetooth' ? 'bluetooth' : 'edit_note' }}</span>
                    {{ __('messages.obd_last_scan') }} · {{ $latest->scanned_at->translatedFormat('j M Y, H:i') }}
                </span>
                <form method="POST" action="{{ route('vehicles.scans.destroy', [$vehicle, $latest]) }}"
                      onsubmit="return confirm({{ Illuminate\Support\Js::from(__('messages.confirm_delete')) }})">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-gray-300 hover:text-red-500 transition" title="{{ __('messages.delete') }}" aria-label="{{ __('messages.delete') }}">
                        <span class="material-icons-round text-[17px]">delete</span>
                    </button>
                </form>
            </div>

            @if($latest->mil_on !== null)
                <div class="text-xs font-semibold mb-2 flex items-center gap-1.5 {{ $latest->mil_on ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    <span class="material-icons-round text-base">{{ $latest->mil_on ? 'warning' : 'check_circle' }}</span>
                    {{ $latest->mil_on ? __('messages.obd_mil_on') : __('messages.obd_mil_off') }}
                </div>
            @endif

            @forelse($latest->describedCodes() as $c)
                <div class="rounded-xl border p-2.5 mb-2 {{ $sevTone[$c['severity']] ?? $sevTone['medium'] }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono font-extrabold text-sm">{{ $c['code'] }}</span>
                        <span class="text-[0.62rem] font-bold uppercase tracking-wide">{{ __('messages.obd_status_' . $c['status']) }}</span>
                    </div>
                    <div class="text-xs mt-1 text-gray-800 dark:text-slate-100">
                        {{ $c['description'] ?? ($c['manufacturer'] ? __('messages.obd_manufacturer_code', ['brand' => $vehicle->brand ?: __('messages.obd_the_maker')]) : ($c['system'] ?? $c['area'])) }}
                    </div>
                    <div class="flex items-center justify-between gap-2 mt-1.5">
                        <span class="text-[0.66rem] text-gray-500 dark:text-slate-400">{{ $c['area'] }}@if($c['system'] && $c['description']) · {{ $c['system'] }}@endif</span>
                        {{-- Turn a fault into something that gets done. --}}
                        <form method="POST" action="{{ route('vehicles.reminders.store', $vehicle) }}">
                            @csrf
                            <input type="hidden" name="label" value="{{ __('messages.obd_reminder_label', ['code' => $c['code']]) }}">
                            <input type="hidden" name="due_date" value="{{ now()->addWeek()->toDateString() }}">
                            <input type="hidden" name="note" value="{{ \Illuminate\Support\Str::limit($c['description'] ?? $c['area'], 250) }}">
                            <button type="submit" class="text-[0.66rem] font-semibold underline hover:no-underline">{{ __('messages.obd_make_reminder') }}</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('messages.obd_no_codes') }}</p>
            @endforelse

            @if($latest->readings)
                <div class="grid grid-cols-2 gap-1.5 mt-2">
                    @foreach($latest->readings as $key => $value)
                        @continue(! isset($units[$key]))
                        <div class="rounded-lg bg-gray-50 dark:bg-slate-900/60 px-2.5 py-1.5">
                            <div class="text-[0.66rem] text-gray-400 dark:text-slate-500">{{ __('messages.obd_r_' . $key) }}</div>
                            <div class="text-xs font-bold text-gray-900 dark:text-white tabular-nums">{{ $fmt($key, $value) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
            @if($latest->vin || $latest->note)
                <p class="text-[0.68rem] text-gray-400 dark:text-slate-500 mt-2">
                    @if($latest->vin) VIN {{ $latest->vin }} @endif
                    @if($latest->vin && $latest->note) · @endif
                    {{ $latest->note }}
                </p>
            @endif
        </div>
    @endif

    {{-- Earlier scans, compact --}}
    @if($older->isNotEmpty())
        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-slate-700/70 space-y-1.5">
            <div class="text-[0.66rem] font-semibold uppercase tracking-wide text-gray-400 dark:text-slate-500">{{ __('messages.obd_history') }}</div>
            @foreach($older as $scan)
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-gray-500 dark:text-slate-400 shrink-0">{{ $scan->scanned_at->translatedFormat('j M Y') }}</span>
                    <span class="flex-1 min-w-0 truncate font-mono font-semibold text-gray-800 dark:text-slate-200">
                        {{ $scan->hasCodes() ? collect($scan->codes)->pluck('code')->implode(', ') : '—' }}
                    </span>
                    <form method="POST" action="{{ route('vehicles.scans.destroy', [$vehicle, $scan]) }}"
                          onsubmit="return confirm({{ Illuminate\Support\Js::from(__('messages.confirm_delete')) }})">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-gray-300 hover:text-red-500" aria-label="{{ __('messages.delete') }}">
                            <span class="material-icons-round text-[16px]">close</span>
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</x-card>
</div>
