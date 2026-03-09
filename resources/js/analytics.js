import axios from 'axios';

function formatCurrency(code, v) {
    return `${code} ${Number(v).toFixed(2)}`;
}

async function fetchStats() {
    const res = await axios.get('/api/bills/stats');
    return res.data;
}

async function fetchSeries() {
    const res = await axios.get('/api/bills/series?months=12');
    return res.data;
}

function makeLineChart(ctx, labels, income, spending, currency) {
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Spending',
                    data: spending,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239,68,68,0.08)',
                    tension: 0.35,
                },
                {
                    label: 'Income',
                    data: income,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.08)',
                    tension: 0.35,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${formatCurrency(currency, ctx.parsed.y)}`
                    }
                }
            },
            scales: {y: {beginAtZero: true}}
        }
    });
}

function makeDoughnutChart(ctx, labels, data, colors) {
    return new Chart(ctx, {
        type: 'doughnut',
        data: {labels, datasets: [{data, backgroundColor: colors}]},
        options: {responsive: true, maintainAspectRatio: false}
    });
}

async function renderCharts() {
    try {
        const [statsResp, seriesResp] = await Promise.all([fetchStats(), fetchSeries()]);
        const currency = statsResp.currency_code ?? seriesResp.currency_code ?? 'EUR';

        // Monthly line
        const monthlyCtx = document.getElementById('chart-monthly')?.getContext('2d');
        if (monthlyCtx) {
            makeLineChart(monthlyCtx, seriesResp.months.map(m => m), seriesResp.income, seriesResp.spending, currency);
        }

        // Income vs spending doughnut
        const totalIncome = seriesResp.income.reduce((a, b) => a + b, 0);
        const totalSpending = seriesResp.spending.reduce((a, b) => a + b, 0);
        const incomeSpendCtx = document.getElementById('chart-income-spend')?.getContext('2d');
        if (incomeSpendCtx) {
            makeDoughnutChart(incomeSpendCtx, ['Spending', 'Income'], [totalSpending, totalIncome], ['#ef4444', '#10b981']);
        }

        // Category doughnut from stats.by_category (object)
        const categoryCtx = document.getElementById('chart-category')?.getContext('2d');
        if (categoryCtx && statsResp.by_category) {
            const items = Object.entries(statsResp.by_category).slice(0, 10);
            const labels = items.map(i => i[0]);
            const data = items.map(i => i[1]);
            const palette = ['#6366f1', '#4f46e5', '#ef4444', '#f59e0b', '#10b981', '#06b6d4', '#f97316', '#8b5cf6', '#06b6d4', '#a3e635'];
            makeDoughnutChart(categoryCtx, labels, data, palette);
        }

    } catch (err) {
        // eslint-disable-next-line no-console
        console.error('Failed to render analytics:', err);
    }
}

// Wait for DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderCharts);
} else {
    renderCharts();
}

