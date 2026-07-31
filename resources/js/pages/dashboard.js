// Dashboard charts initializer

function formatCurrency(code, v) {
    return `${code} ${Number(v).toFixed(2)}`;
}

function makeLineChart(ctx, labels, income, spending, currency) {
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels, datasets: [
                {
                    label: 'Spending',
                    data: spending,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,.07)',
                    tension: .35,
                    fill: true,
                    pointRadius: 3
                },
                {
                    label: 'Income',
                    data: income,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,.07)',
                    tension: .35,
                    fill: true,
                    pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {tooltip: {callbacks: {label: c => `${c.dataset.label}: ${formatCurrency(currency, c.parsed.y)}`}}},
            scales: {y: {beginAtZero: true, ticks: {callback: v => formatCurrency(currency, v)}}}
        }
    });
}

function makeDoughnutChart(ctx, labels, data, colors, cutout = '65%') {
    return new Chart(ctx, {
        type: 'doughnut',
        data: {labels, datasets: [{data, backgroundColor: colors, borderWidth: 0}]},
        options: {responsive: true, maintainAspectRatio: false, cutout}
    });
}

function initDashboardCharts() {
    try {
        const el = document.getElementById('dashboard-chart-data');
        if (!el) return;
        const chartData = JSON.parse(el.getAttribute('data-chart') || '{}');
        const cur = chartData.currency ?? 'EUR';

        const monthlyCtx = document.getElementById('chart-monthly')?.getContext('2d');
        if (monthlyCtx) {
            makeLineChart(monthlyCtx, chartData.months || [], chartData.income || [], chartData.spending || [], cur);
        }

        const isCtx = document.getElementById('chart-income-spend')?.getContext('2d');
        if (isCtx) {
            const ts = (chartData.spending || []).reduce((a, b) => a + b, 0);
            const ti = (chartData.income || []).reduce((a, b) => a + b, 0);
            makeDoughnutChart(isCtx, ['Spending', 'Income'], [ts, ti], ['#f59e0b', '#10b981'], '65%');
        }

        const catCtx = document.getElementById('chart-category')?.getContext('2d');
        if (catCtx && chartData.by_category && Object.keys(chartData.by_category).length > 0) {
            const entries = Object.entries(chartData.by_category).slice(0, 8);
            const palette = ['#f59e0b', '#3b82f6', '#d97706', '#34d399', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899'];
            makeDoughnutChart(catCtx, entries.map(e => e[0]), entries.map(e => e[1]), palette, '55%');
        }
    } catch (e) {
        // eslint-disable-next-line no-console
        console.warn('Dashboard charts failed to initialize:', e && e.message ? e.message : e);
    }
}

// Initialize when DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboardCharts);
} else {
    initDashboardCharts();
}


