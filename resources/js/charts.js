// Charts: an Alpine component around ApexCharts, used through the
// <x-chart> Blade component (resources/views/components/chart.blade.php).
//
// The library is imported on first use, so pages without a chart never
// download it. Colours follow the page theme and are redrawn when the
// theme switch flips the `dark` class on <html>.

const isDark = () => document.documentElement.classList.contains('dark');
const pageLocale = () => document.documentElement.lang || 'el';

let apexPromise = null;
const loadApex = () => (apexPromise ??= import('apexcharts').then((m) => m.default));

function money(currency, value, short = false) {
    const n = Number(value) || 0;
    // Axis labels: "1,5k €" rather than the locale's long "1,5 χιλ. €".
    const text = short
        ? (Math.abs(n) >= 1000
            ? new Intl.NumberFormat(pageLocale(), { maximumFractionDigits: 1 }).format(n / 1000) + 'k'
            : new Intl.NumberFormat(pageLocale(), { maximumFractionDigits: 0 }).format(n))
        : new Intl.NumberFormat(pageLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);

    return currency ? `${text} ${currency === 'EUR' ? '€' : currency}` : text;
}

function themed(config) {
    const dark = isDark();
    const ink = dark ? '#94a3b8' : '#6b7280';
    const grid = dark ? 'rgba(148,163,184,0.12)' : '#f1f5f9';
    const currency = config.currency ?? null;
    const isPie = ['donut', 'pie', 'radialBar'].includes(config.type);
    const horizontal = !!config.horizontal;

    const options = {
        chart: {
            type: config.type ?? 'bar',
            height: config.height ?? 280,
            stacked: !!config.stacked,
            background: 'transparent',
            fontFamily: 'inherit',
            foreColor: ink,
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: true, speed: 450 },
            parentHeightOffset: 0,
        },
        theme: { mode: dark ? 'dark' : 'light' },
        colors: config.colors ?? ['#f59e0b', '#3b82f6', '#8b5cf6', '#10b981', '#ef4444', '#14b8a6', '#ec4899', '#64748b'],
        series: config.series ?? [],
        dataLabels: { enabled: false },
        legend: {
            show: config.legend ?? true,
            position: isPie ? 'bottom' : 'top',
            horizontalAlign: isPie ? 'center' : 'right',
            fontSize: '12px',
            fontWeight: 500,
            markers: { size: 5, shape: 'circle' },
            itemMargin: { horizontal: 8, vertical: 2 },
        },
        tooltip: {
            theme: dark ? 'dark' : 'light',
            shared: !isPie && !horizontal,
            intersect: false,
            y: { formatter: (v) => money(currency, v) },
        },
        grid: {
            borderColor: grid,
            strokeDashArray: 4,
            padding: { left: 4, right: 8 },
            xaxis: { lines: { show: horizontal } },
            yaxis: { lines: { show: !horizontal } },
        },
        states: { hover: { filter: { type: 'darken', value: 0.9 } } },
    };

    if (isPie) {
        options.labels = config.labels ?? [];
        options.stroke = { width: 2, colors: [dark ? '#1e293b' : '#ffffff'] };
        options.plotOptions = {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        value: { fontSize: '18px', fontWeight: 700, color: dark ? '#fff' : '#111827', formatter: (v) => money(currency, v) },
                        total: {
                            show: true,
                            label: config.totalLabel ?? '',
                            fontSize: '12px',
                            color: ink,
                            formatter: (w) => money(currency, w.globals.seriesTotals.reduce((a, b) => a + b, 0)),
                        },
                    },
                },
            },
        };
    } else {
        options.xaxis = {
            categories: config.categories ?? [],
            axisBorder: { show: false },
            axisTicks: { show: false },
            ...(horizontal ? { tickAmount: 4 } : {}),
            labels: {
                style: { fontSize: '11px' },
                hideOverlappingLabels: true,
                ...(horizontal ? { formatter: (v) => money(currency, v, true) } : {}),
            },
        };
        options.yaxis = {
            labels: {
                style: { fontSize: '11px' },
                maxWidth: horizontal ? 160 : 80,
                ...(horizontal ? {} : { formatter: (v) => money(currency, v, true) }),
            },
        };
        options.plotOptions = {
            bar: {
                horizontal,
                borderRadius: 5,
                borderRadiusApplication: 'end',
                borderRadiusWhenStacked: 'last',
                columnWidth: config.columnWidth ?? '58%',
                barHeight: '62%',
                distributed: !!config.distributed,
            },
        };
        if (config.type === 'area' || config.type === 'line') {
            // monotoneCubic: smooth, but never bulges past the real values.
            options.stroke = { curve: 'monotoneCubic', width: 2.5 };
            options.fill = config.type === 'area'
                ? { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 95] } }
                : {};
            options.markers = { size: 0, hover: { size: 5 } };
        }
        // Mixed charts: a series may name its own type (bars with a line on top).
        if (options.series.some((s) => s.type)) {
            options.stroke = { width: options.series.map((s) => (s.type === 'line' ? 3 : 0)), curve: 'smooth' };
            options.markers = { size: options.series.map((s) => (s.type === 'line' ? 4 : 0)) };
        }
    }

    return options;
}

window.oikoChart = function (config) {
    return {
        chart: null,
        observer: null,

        async init() {
            const ApexCharts = await loadApex();
            this.chart = new ApexCharts(this.$refs.canvas, themed(config));
            await this.chart.render();

            // The theme switch only toggles a class, so watch for it.
            let dark = isDark();
            this.observer = new MutationObserver(() => {
                if (isDark() !== dark) {
                    dark = isDark();
                    this.chart?.updateOptions(themed(config), false, false);
                }
            });
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },

        destroy() {
            this.observer?.disconnect();
            this.chart?.destroy();
        },
    };
};
