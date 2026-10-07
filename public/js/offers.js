/* Job offer comparer: two or three offers, compared after tax. */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('offerCompare', (boot) => ({
        options: boot.options,
        routes: boot.routes,
        form: JSON.parse(JSON.stringify(boot.input)),
        result: boot.result,
        loading: false,
        error: null,
        timer: null,
        seq: 0,

        init() {
            try {
                const draft = JSON.parse(localStorage.getItem('kh:offers') || 'null');
                if (draft && Array.isArray(draft.offers) && draft.offers.length >= 2) { this.form = draft; this.queue(0); }
            } catch (e) { /* ignore */ }
            this.$watch('form', () => {
                try { localStorage.setItem('kh:offers', JSON.stringify(this.form)); } catch (e) { /* private mode */ }
                this.queue();
            });
            window.addEventListener('kh:theme', () => this.renderChart());
            this.$nextTick(() => this.renderChart());
        },

        bdt: (n) => KH.bdt(n),
        pct: (r, d = 1) => KH.pct(r, d),
        money(el, get, set) { KH.bindMoney(el, get, set); },

        addOffer() {
            if (this.form.offers.length >= 3) return;
            const last = this.form.offers[this.form.offers.length - 1];
            this.form.offers.push({ ...last, name: KH.t('Offer :letter', { letter: String.fromCharCode(65 + this.form.offers.length) }) });
        },
        removeOffer(i) {
            if (this.form.offers.length <= 2) return;
            this.form.offers.splice(i, 1);
        },

        /* Rows of the comparison table. kind: how a difference against offer A reads (gain = higher is better). */
        get rows() {
            return [
                { key: 'monthly', label: KH.t('Monthly salary'), kind: 'gain' },
                { key: 'bonuses', label: KH.t('Festival bonuses per year'), kind: 'gain' },
                { key: 'employer_pf', label: KH.t('Employer PF per year'), kind: 'gain' },
                { key: 'gross', label: KH.t('Salary for tax per year'), kind: 'neutral' },
                { key: 'tax', label: KH.t('Tax per year'), kind: 'cost' },
                { key: 'investment_needed', label: KH.t('Invest for the full rebate'), kind: 'neutral' },
                { key: 'take_home_monthly', label: KH.t('Monthly take-home'), kind: 'gain', strong: true },
                { key: 'total_value', label: KH.t('Total yearly value'), kind: 'gain', strong: true },
            ];
        },
        diffClass(row, offer) {
            const base = this.result.offers[0];
            if (!base || offer.index === 0 || row.kind === 'neutral') return '';
            const d = offer[row.key] - base[row.key];
            if (Math.abs(d) < 1) return '';
            return (row.kind === 'cost') === (d > 0) ? 'diff-up' : 'diff-down';
        },
        diffText(row, offer) {
            const base = this.result.offers[0];
            if (!base || offer.index === 0) return '';
            const d = offer[row.key] - base[row.key];
            return Math.abs(d) < 1 ? '' : (d > 0 ? '+' : '−') + KH.bdt(Math.abs(d));
        },
        get verdict() {
            const r = this.result;
            if (!r.offers.length || r.best === null) return '';
            const best = r.offers[r.best];
            const base = r.offers[0];
            if (r.best === 0) return KH.t(':name keeps the most money in your pocket each month.', { name: best.name });
            const gain = best.take_home_monthly - base.take_home_monthly;
            return KH.t(':name leaves you :gain more a month than :base after tax: a :raise raise on paper is :real in your pocket.', {
                name: best.name, gain: KH.bdt(gain), base: base.name,
                raise: KH.pct(best.vs_first?.gross_raise ?? 0), real: KH.pct(best.vs_first?.take_home_raise ?? 0),
            });
        },

        queue(delay = 220) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.compute(), delay);
        },
        async compute() {
            const seq = ++this.seq;
            this.loading = true;
            try {
                const data = await KH.request(this.routes.compute, 'POST', { ...this.form, grouping: KH.grouping() });
                if (seq !== this.seq) return;
                this.result = data;
                this.error = null;
                this.$nextTick(() => this.renderChart());
            } catch (e) {
                if (seq === this.seq) this.error = e.message;
            } finally {
                if (seq === this.seq) this.loading = false;
            }
        },

        renderChart() {
            if (!window.Chart || !this.$refs.offerChart) return;
            const offers = this.result.offers;
            KH.chart(this.$refs.offerChart, {
                type: 'bar',
                data: {
                    labels: offers.map((o) => o.name),
                    datasets: [
                        { label: KH.t('Take-home per year'), data: offers.map((o) => o.take_home_annual), backgroundColor: KH.css('--green'), borderRadius: 6, maxBarThickness: 48, stack: 'v' },
                        { label: KH.t('Employer PF'), data: offers.map((o) => o.employer_pf), backgroundColor: KH.css('--slab-1'), borderRadius: 6, maxBarThickness: 48, stack: 'v' },
                        { label: KH.t('Tax'), data: offers.map((o) => o.tax), backgroundColor: KH.css('--red'), borderRadius: 6, maxBarThickness: 48, stack: 'v' },
                    ],
                },
                options: {
                    interaction: { mode: 'index', intersect: false },
                    plugins: { tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + KH.bdt(c.parsed.y) } } },
                    scales: { y: KH.axisMoney({ beginAtZero: true, stacked: true }), x: { stacked: true, grid: { display: false } } },
                },
            });
        },
    }));
});
