{{--
    A chart, drawn by ApexCharts through resources/js/charts.js. Follows the
    light/dark theme and formats amounts in the given currency.

    Bars (stacked or side by side), area, line, horizontal bars:
    &lt;x-chart type="bar" stacked :categories="$months" currency="EUR"
             :series="[['name' => 'Bills', 'data' => [120, 80]], ['name' => 'Purchases', 'data' => [40, 55]]]" />

    Bars with a line on top: give each series its own `type` ('column' / 'line').

    Donut:
    &lt;x-chart type="donut" :labels="['Bills', 'Purchases']" :series="[200, 95]" currency="EUR" total-label="Total" />

    `height` is in pixels. `empty` is shown instead when there is nothing to draw.
--}}
@props([
    'type'       => 'bar',
    'series'     => [],
    'categories' => [],
    'labels'     => [],
    'currency'   => null,
    'height'     => 280,
    'stacked'    => false,
    'horizontal' => false,
    'distributed'=> false,
    'colors'     => null,
    'legend'     => true,
    'totalLabel' => null,
    'empty'      => null,
])
@php
    $values = collect($series)->flatMap(fn ($s) => is_array($s) ? ($s['data'] ?? []) : [$s]);
    $hasData = $values->contains(fn ($v) => (float) $v != 0);
    $config = array_filter([
        'type' => $type, 'series' => $series, 'categories' => $categories, 'labels' => $labels,
        'currency' => $currency, 'height' => $height, 'stacked' => $stacked, 'horizontal' => $horizontal,
        'distributed' => $distributed, 'colors' => $colors, 'legend' => $legend, 'totalLabel' => $totalLabel,
    ], fn ($v) => $v !== null);
@endphp
@if($hasData)
    <div x-data="oikoChart(@js($config))" {{ $attributes->class(['w-full min-w-0']) }} style="min-height: {{ (int) $height }}px">
        <div x-ref="canvas"></div>
    </div>
@else
    <div {{ $attributes->class(['w-full flex items-center justify-center text-sm text-gray-400 dark:text-slate-500']) }}
         style="min-height: {{ (int) $height }}px">
        {{ $empty ?? __('messages.reports_no_data') }}
    </div>
@endif
