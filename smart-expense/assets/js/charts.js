const PALETTE = ['#5b7fff','#00b4a0','#f05c6a','#e8c547','#8b5cf6','#f97316','#06b6d4','#84cc16','#ec4899','#a78bfa'];
const PALETTE_A = PALETTE.map(c => c + '22');

const MONO = "'DM Mono', monospace";

const TIP = {
    callbacks: {
        label: ctx => {
            const v = ctx.parsed.y ?? ctx.parsed;
            if (v === null || v === undefined) return null;
            return ' ৳ ' + parseFloat(v).toLocaleString('en-BD', { minimumFractionDigits: 2 });
        }
    },
    backgroundColor: '#11111a',
    titleColor: '#ffffff',
    bodyColor: 'rgba(255,255,255,.75)',
    padding: 10,
    cornerRadius: 8,
    titleFont: { family: MONO, size: 12 },
    bodyFont: { family: MONO, size: 12 }
};

const SCALE = {
    y: {
        beginAtZero: true,
        grid: { color: 'rgba(0,0,0,.05)', drawBorder: false },
        ticks: { color: '#888899', font: { family: MONO, size: 11 }, callback: v => '৳' + Number(v).toLocaleString('en-BD') }
    },
    x: {
        grid: { display: false },
        ticks: { color: '#888899', font: { size: 11 } }
    }
};

const BASE = {
    responsive: true,
    maintainAspectRatio: true,
    animation: { duration: 500, easing: 'easeInOutQuart' },
    plugins: {
        legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 }, usePointStyle: true, color: '#4a4a6a' } },
        tooltip: TIP
    }
};

function renderPieChart(id, labels, values, doughnut = false) {
    const ctx = document.getElementById(id);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: doughnut ? 'doughnut' : 'pie',
        data: {
            labels,
            datasets: [{ data: values, backgroundColor: PALETTE, borderColor: '#ffffff', borderWidth: 2, hoverOffset: 6 }]
        },
        options: {
            ...BASE,
            cutout: doughnut ? '60%' : 0,
            plugins: {
                ...BASE.plugins,
                tooltip: {
                    ...TIP,
                    callbacks: {
                        label: ctx => {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                            return ` ৳${parseFloat(ctx.parsed).toLocaleString('en-BD', { minimumFractionDigits: 2 })} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
}

function renderLineChart(id, labels, datasets) {
    const ctx = document.getElementById(id);
    if (!ctx) return null;
    const built = datasets.map((ds, i) => ({
        label: ds.label ?? 'Series',
        data: ds.data,
        borderColor: ds.color ?? PALETTE[i % PALETTE.length],
        backgroundColor: ds.fill ? (ds.color ?? PALETTE[i % PALETTE.length]) + '18' : 'transparent',
        borderDash: ds.dashed ? [6, 4] : [],
        borderWidth: 2,
        pointRadius: ds.dashed ? 7 : 4,
        pointHoverRadius: 7,
        pointBackgroundColor: ds.color ?? PALETTE[i % PALETTE.length],
        tension: 0.4,
        fill: ds.fill ?? false,
        spanGaps: true
    }));
    return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets: built },
        options: {
            ...BASE,
            scales: SCALE,
            plugins: {
                ...BASE.plugins,
                tooltip: { ...TIP, mode: 'index', intersect: false }
            }
        }
    });
}

function renderBarChart(id, labels, values, label = 'Expense (৳)', color = '#5b7fff') {
    const ctx = document.getElementById(id);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{ label, data: values, backgroundColor: color + 'bb', borderColor: color, borderWidth: 0, borderRadius: 5, borderSkipped: false, hoverBackgroundColor: color }]
        },
        options: { ...BASE, scales: SCALE }
    });
}

function renderPredictionChart(id, labels, actuals, predicted) {
    const ctx = document.getElementById(id);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: [...labels, 'Next month'],
            datasets: [
                { label: 'Actual', data: [...actuals, null], borderColor: '#5b7fff', backgroundColor: '#5b7fff18', borderWidth: 2, pointRadius: 4, tension: 0.4, fill: true, spanGaps: false },
                { label: 'Predicted', data: [...Array(actuals.length).fill(null), predicted], borderColor: '#f05c6a', borderDash: [7, 4], borderWidth: 2.5, pointRadius: 9, pointStyle: 'star', pointBackgroundColor: '#f05c6a', fill: false }
            ]
        },
        options: {
            ...BASE,
            scales: SCALE,
            plugins: { ...BASE.plugins, tooltip: { ...TIP, mode: 'index', intersect: false } }
        }
    });
}

function renderHorizontalBar(id, labels, values) {
    const ctx = document.getElementById(id);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Spent (৳)', data: values, backgroundColor: PALETTE.slice(0, labels.length), borderRadius: 5, borderSkipped: false }] },
        options: {
            ...BASE,
            indexAxis: 'y',
            scales: {
                x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { color: '#888899', font: { family: MONO, size: 11 }, callback: v => '৳' + Number(v).toLocaleString('en-BD') } },
                y: { grid: { display: false }, ticks: { color: '#4a4a6a', font: { size: 12 } } }
            }
        }
    });
}

const _instances = {};
function safeRender(fn, id, ...args) {
    if (_instances[id]) _instances[id].destroy();
    _instances[id] = fn(id, ...args);
    return _instances[id];
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('canvas[data-chart]').forEach(el => {
        const labels = JSON.parse(el.dataset.labels || '[]');
        const values = JSON.parse(el.dataset.values || '[]');
        switch (el.dataset.chart) {
            case 'pie': safeRender(renderPieChart, el.id, labels, values); break;
            case 'doughnut': safeRender(renderPieChart, el.id, labels, values, true); break;
            case 'bar': safeRender(renderBarChart, el.id, labels, values, el.dataset.label || 'Value', el.dataset.color || '#5b7fff'); break;
            case 'hbar': safeRender(renderHorizontalBar, el.id, labels, values); break;
        }
    });
});
