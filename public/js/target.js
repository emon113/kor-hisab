/* Target tax page: tax amount → gross salary → salary components. */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('targetTax', (boot) => ({
        options: boot.options,
        routes: boot.routes,
        auth: boot.auth,
        labels: boot.labels,
        defaults: boot.defaults,
        form: JSON.parse(JSON.stringify(boot.input)),
        result: boot.result,
        loading: false,
        error: null,
        timer: null,
        seq: 0,

        init() {
            this.$watch('form', () => this.queue());
            window.addEventListener('kh:theme', () => this.renderCharts());
            this.$nextTick(() => this.renderCharts());
        },

        bdt: (n) => KH.bdt(n),
        group: (n) => KH.group(n),
        pct: (r, d = 2) => KH.pct(r, d),

        money(el, field) {
            KH.bindMoney(el, () => this.form[field] || 0, (v) => { this.form[field] = v; });
        },
        get ratioTotal() {
            return Object.values(this.form.ratios).reduce((a, v) => a + (Number(v) || 0), 0);
        },
        resetRatios() {
            this.form.ratios = { ...this.defaults };
            KH.toast('Salary split reset to the default ratios.');
        },
        async saveRatios() {
            try {
                const data = await KH.request(this.routes.ratios, 'POST', { ratios: this.form.ratios });
                KH.toast(data.message);
            } catch (e) {
                KH.toast(e.message, 'error');
            }
        },
        async copy() {
            try {
                await navigator.clipboard.writeText(this.result.copy_text);
                KH.toast('Breakdown copied.');
            } catch (e) {
                KH.toast('Copy failed. Select the text and copy it manually.', 'error');
            }
        },

        queue(delay = 220) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.solve(), delay);
        },
        async solve() {
            const seq = ++this.seq;
            this.loading = true;
            try {
                const data = await KH.request(this.routes.solve, 'POST', { ...this.form, grouping: KH.grouping() });
                if (seq !== this.seq) return;
                this.result = data;
                this.error = null;
                this.$nextTick(() => this.renderCharts());
            } catch (e) {
                if (seq === this.seq) this.error = e.message;
            } finally {
                if (seq === this.seq) this.loading = false;
            }
        },

        renderCharts() {
            if (!window.Chart || !this.$refs.splitChart) return;
            const comps = this.result.components.filter((c) => c.amount > 0);
            const palette = ['--green', '--slab-1', '--slab-2', '--slab-3', '--slab-4', '--slab-5', '--exempt'].map((v) => KH.css(v));

            KH.chart(this.$refs.splitChart, {
                type: 'doughnut',
                data: {
                    labels: comps.map((c) => c.label),
                    datasets: [{ data: comps.map((c) => c.amount), backgroundColor: palette, borderColor: KH.css('--surface'), borderWidth: 3 }],
                },
                options: { cutout: '62%', plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => ' ' + c.label + ': ' + KH.bdt(c.parsed) } } } },
            });

            KH.chart(this.$refs.monthlyChart, {
                type: 'bar',
                data: {
                    labels: comps.map((c) => c.label),
                    datasets: [{ label: 'Per month', data: comps.map((c) => c.monthly), backgroundColor: palette, borderRadius: 6, maxBarThickness: 26 }],
                },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + KH.bdt(c.parsed.x) + ' a month' } } },
                    scales: { x: KH.axisMoney({ beginAtZero: true }), y: { grid: { display: false } } },
                },
            });

            const curve = this.result.curve;
            const target = this.result.tax;
            KH.chart(this.$refs.curveChart, {
                type: 'line',
                data: {
                    datasets: [
                        { label: 'Tax', data: curve.map((p) => ({ x: p.income, y: p.tax })), borderColor: KH.css('--red'), backgroundColor: KH.css('--red'), pointRadius: 0, borderWidth: 2.5, tension: 0 },
                        { label: 'Your target', data: [{ x: 0, y: target }, { x: curve[curve.length - 1].income, y: target }], borderColor: KH.css('--muted'), backgroundColor: KH.css('--muted'), borderDash: [6, 5], pointRadius: 0, borderWidth: 1.5 },
                        { type: 'scatter', label: 'Required income', data: [{ x: this.result.gross, y: target }], backgroundColor: KH.css('--green'), borderColor: KH.css('--surface'), borderWidth: 2, pointRadius: 8 },
                    ],
                },
                options: {
                    parsing: false,
                    interaction: { mode: 'nearest', axis: 'x', intersect: false },
                    plugins: { tooltip: { callbacks: { title: (items) => 'Income ' + KH.bdt(items[0].parsed.x), label: (c) => ' ' + c.dataset.label + ': ' + KH.bdt(c.parsed.y) } } },
                    scales: {
                        y: KH.axisMoney({ beginAtZero: true }),
                        x: { type: 'linear', min: 0, max: curve[curve.length - 1].income, ticks: { callback: (v) => KH.short(v), maxTicksLimit: 8 }, grid: { display: false } },
                    },
                },
            });
        },
    }));
});
