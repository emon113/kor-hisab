/* Salary certificate report: charts only (every number is rendered by the server, so print never depends on JS). */
(function () {
    'use strict';

    function draw() {
        const R = window.KH_REPORT;
        if (!R || !window.Chart) return;
        const css = KH.css;
        const palette = ['--green', '--slab-1', '--slab-2', '--slab-3', '--slab-4', '--slab-5', '--exempt', '--turmeric', '--red'].map(css);
        const money = { y: KH.axisMoney({ beginAtZero: true }), x: { grid: { display: false } } };

        const salary = R.salary.filter((s) => s.amount > 0);
        KH.chart(document.getElementById('rc-salary'), {
            type: 'doughnut',
            data: { labels: salary.map((s) => s.label), datasets: [{ data: salary.map((s) => s.amount), backgroundColor: palette, borderColor: css('--surface'), borderWidth: 3 }] },
            options: { cutout: '62%', plugins: { legend: { position: 'right' }, tooltip: { callbacks: { label: (c) => ' ' + c.label + ': ' + KH.bdt(c.parsed) } } } },
        });

        KH.chart(document.getElementById('rc-slabs'), {
            type: 'bar',
            data: { labels: R.slabs.map((s) => KH.pct(s.rate, 0)), datasets: [{ data: R.slabs.map((s) => s.tax), backgroundColor: R.slabs.map((s) => KH.slabColor(s.rate)), borderRadius: 6, maxBarThickness: 42 }] },
            options: { plugins: { legend: { display: false }, tooltip: KH.tooltipMoney(KH.t('Tax')) }, scales: money },
        });

        const wf = R.charts.waterfall;
        let running = 0;
        const bars = wf.map((step) => {
            if (step.kind === 'total') { running = step.value; return [0, step.value]; }
            const start = running;
            running += step.value;
            return [start, running];
        });
        KH.chart(document.getElementById('rc-waterfall'), {
            type: 'bar',
            data: { labels: wf.map((w) => w.label), datasets: [{ data: bars, backgroundColor: wf.map((w) => (w.kind === 'total' ? css('--ink-2') : (w.kind === 'down' ? css('--green') : css('--red')))), borderRadius: 5, maxBarThickness: 46 }] },
            options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + KH.bdt(wf[c.dataIndex].value) } } }, scales: money },
        });

        const rc = R.charts.rate_curve;
        KH.chart(document.getElementById('rc-rate'), {
            type: 'line',
            data: {
                datasets: [
                    { label: KH.t('Marginal slab rate'), data: rc.points.map((p) => ({ x: p.income, y: p.marginal * 100 })), borderColor: css('--turmeric'), backgroundColor: css('--turmeric'), stepped: true, pointRadius: 0, borderWidth: 2 },
                    { label: KH.t('Average (effective) rate'), data: rc.points.map((p) => ({ x: p.income, y: p.effective * 100 })), borderColor: css('--red'), backgroundColor: css('--red'), tension: 0.3, pointRadius: 0, borderWidth: 2.5 },
                    { type: 'scatter', label: KH.t('You'), data: [{ x: rc.you.income, y: rc.you.effective * 100 }], backgroundColor: css('--ink'), borderColor: css('--surface'), borderWidth: 2, pointRadius: 7 },
                ],
            },
            options: {
                parsing: false,
                plugins: { tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + KH.num(c.parsed.y.toFixed(1)) + '%' } } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: (v) => KH.num(v) + '%' }, grid: { color: css('--line') } },
                    x: { type: 'linear', min: 0, max: rc.points[rc.points.length - 1].income, ticks: { callback: (v) => KH.short(v), maxTicksLimit: 6 }, grid: { display: false } },
                },
            },
        });

        const curve = R.investments.curve;
        KH.chart(document.getElementById('rc-rebate'), {
            type: 'line',
            data: {
                labels: curve.map((p) => p.investment),
                datasets: [
                    { label: KH.t('Rebate'), data: curve.map((p) => p.rebate), borderColor: css('--green'), backgroundColor: css('--green'), pointRadius: 0, borderWidth: 2.5 },
                    { label: KH.t('Tax payable'), data: curve.map((p) => p.tax), borderColor: css('--red'), backgroundColor: css('--red'), pointRadius: 0, borderWidth: 2.5 },
                ],
            },
            options: { scales: { y: KH.axisMoney({ beginAtZero: true }), x: { ticks: { callback: function (v) { return KH.short(this.getLabelForValue(v)); }, maxTicksLimit: 6 }, grid: { display: false } } } },
        });

        const fu = R.charts.future;
        KH.chart(document.getElementById('rc-future'), {
            type: 'bar',
            data: { labels: fu.map((f) => f.label + (f.projected ? ' *' : '')), datasets: [{ data: fu.map((f) => f.tax), backgroundColor: fu.map((f) => (f.current ? css('--red') : (f.projected ? css('--slab-1') : css('--exempt')))), borderRadius: 6, maxBarThickness: 50 }] },
            options: { plugins: { legend: { display: false }, tooltip: KH.tooltipMoney(KH.t('Tax')) }, scales: money },
        });
    }

    document.addEventListener('DOMContentLoaded', draw);
    window.addEventListener('kh:theme', draw);
})();
