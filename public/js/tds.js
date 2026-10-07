/* Monthly TDS planner: twelve months in, a deduction plan out. */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('tdsPlanner', (boot) => ({
        options: boot.options,
        routes: boot.routes,
        form: JSON.parse(JSON.stringify(boot.input)),
        plan: boot.plan,
        quickSalary: 0,
        loading: false,
        error: null,
        timer: null,
        seq: 0,

        init() {
            const draft = this.loadDraft();
            if (draft) {
                this.form = draft;
                this.queue(0);
            }
            this.quickSalary = this.form.months.oct?.salary || 0;
            this.$watch('form', () => { this.saveDraft(); this.queue(); });
            window.addEventListener('kh:theme', () => this.renderChart());
            this.$nextTick(() => this.renderChart());
        },

        bdt: (n) => KH.bdt(n),
        money(el, get, set) { KH.bindMoney(el, get, set); },
        get yearInfo() { return this.options.years.find((y) => y.key === this.form.year) || {}; },

        /* Drafts live in this browser only. */
        loadDraft() {
            try {
                const d = JSON.parse(localStorage.getItem('kh:tds') || 'null');
                return d && d.months && Object.keys(d.months).length === 12 ? d : null;
            } catch (e) { return null; }
        },
        saveDraft() { try { localStorage.setItem('kh:tds', JSON.stringify(this.form)); } catch (e) { /* private mode */ } },

        fillSalary() {
            Object.values(this.form.months).forEach((m) => { m.salary = this.quickSalary; });
            KH.toast(KH.t('Salary set for all twelve months.'));
        },
        async copy() {
            try {
                await navigator.clipboard.writeText(this.plan.hr_text);
                KH.toast(KH.t('Message copied.'));
            } catch (e) {
                KH.toast(KH.t('Copy failed. Select the text and copy it manually.'), 'error');
            }
        },

        get statusTone() {
            return { on_track: 'good', ahead: 'info', behind: 'cost', refund: 'good' }[this.plan.status];
        },
        get statusTitle() {
            const p = this.plan;
            return {
                on_track: KH.t('On track'),
                ahead: KH.t('Ahead by :amount', { amount: KH.bdt(p.deducted - p.needed_by_now) }),
                behind: KH.t('Behind by :amount', { amount: KH.bdt(p.needed_by_now - p.deducted) }),
                refund: KH.t(':amount over-deducted', { amount: KH.bdt(-p.remaining) }),
            }[p.status];
        },
        get statusText() {
            return {
                on_track: KH.t('Deductions so far match the tax due on what you have earned.'),
                ahead: KH.t('More has been deducted than is due so far. The months left will need less.'),
                behind: KH.t('Less has been deducted than is due so far. The months left need more, or you will pay the balance with your return.'),
                refund: KH.t('More tax has been deducted than the year needs. Stop further TDS and claim the excess in your return.'),
            }[this.plan.status];
        },

        queue(delay = 220) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.refresh(), delay);
        },
        async refresh() {
            const seq = ++this.seq;
            this.loading = true;
            try {
                const data = await KH.request(this.routes.plan, 'POST', { ...this.form, grouping: KH.grouping() });
                if (seq !== this.seq) return;
                this.plan = data;
                this.error = null;
                this.$nextTick(() => this.renderChart());
            } catch (e) {
                if (seq === this.seq) this.error = e.message;
            } finally {
                if (seq === this.seq) this.loading = false;
            }
        },

        renderChart() {
            if (!window.Chart || !this.$refs.paceChart) return;
            const rows = this.plan.months;
            const done = rows.map((r) => this.form.months[r.key]?.done);
            KH.chart(this.$refs.paceChart, {
                type: 'bar',
                data: {
                    labels: rows.map((r) => r.label.split(' ')[0]),
                    datasets: [
                        { type: 'line', label: KH.t('Tax due so far'), data: rows.map((r) => r.cumulative_needed), borderColor: KH.css('--muted'), backgroundColor: KH.css('--muted'), borderDash: [6, 5], pointRadius: 0, borderWidth: 1.5, tension: 0 },
                        { label: KH.t('TDS, cumulative'), data: rows.map((r) => r.cumulative_planned), backgroundColor: done.map((d) => (d ? KH.css('--green') : KH.css('--slab-1'))), borderRadius: 5, maxBarThickness: 28 },
                    ],
                },
                options: {
                    interaction: { mode: 'index', intersect: false },
                    plugins: { tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + KH.bdt(c.parsed.y) } } },
                    scales: { y: KH.axisMoney({ beginAtZero: true }), x: { grid: { display: false } } },
                },
            });
        },
    }));
});
