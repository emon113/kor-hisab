/* Calculator page: live recalculation, drafts, saving and every chart. */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('calculator', (boot) => ({
        options: boot.options,
        routes: boot.routes,
        auth: boot.auth,
        report: boot.report,
        form: null,
        calculation: boot.calculation,
        snapshot: '',
        incomeMode: 'annual',
        loading: false,
        error: null,
        saving: false,
        saveTitle: '',
        saveNotes: '',
        seq: 0,
        timer: null,
        controller: null,

        init() {
            this.form = this.clone(boot.report.input);
            this.snapshot = this.signature(this.form);
            this.incomeMode = window.localStorage?.getItem('kh:incomeMode') || 'annual';
            this.saveNotes = boot.calculation?.notes || '';

            if (boot.fresh) {
                KH.draft.clear();
            } else if (!this.calculation) {
                const draft = KH.draft.load();
                if (draft && this.signature(draft) !== this.snapshot) {
                    this.form = { ...this.form, ...draft, investments: { ...this.form.investments, ...(draft.investments || {}) } };
                    this.$nextTick(() => KH.toast(KH.t('Your last numbers are back.')));
                    this.queue(0);
                }
            }

            this.$watch('form', () => {
                if (!this.calculation) KH.draft.save(this.form);
                this.queue();
            });
            this.$watch('incomeMode', (mode) => window.localStorage?.setItem('kh:incomeMode', mode));
            window.addEventListener('kh:theme', () => this.renderCharts());
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); this.openSave(); }
            });
            this.$nextTick(() => this.renderCharts());
        },

        /* ---------- formatting shortcuts for templates ---------- */
        bdt: (n) => KH.bdt(n),
        pct: (r, d = 1) => KH.pct(r, d),
        group: (n) => KH.group(n),
        icon: (n) => KH.icon(n),
        slabVar: (r) => KH.slabVar(r),
        clone: (o) => JSON.parse(JSON.stringify(o)),

        get s() { return this.report.summary; },
        get inv() { return this.report.investments; },
        get next() { return this.report.next_slab; },
        get dirty() { return this.signature(this.form) !== this.snapshot; },

        signature(f) {
            const inv = {};
            this.options.instruments.forEach((i) => { inv[i.key] = Number(f.investments?.[i.key] || 0); });
            return JSON.stringify([f.year, f.category, Number(f.gross_income || 0), inv, Number(f.tds_paid || 0),
                f.filing || 'standard', !!f.new_taxpayer, Number(f.disabled_children || 0)]);
        },

        /* ---------- inputs ---------- */
        money(el, field) {
            KH.bindMoney(el, () => this.getMoney(field), (v) => this.setMoney(field, v));
        },
        getMoney(field) {
            if (field === 'income') return this.incomeMode === 'monthly' ? Math.round(this.form.gross_income / 12) : this.form.gross_income;
            if (field === 'tds') return this.form.tds_paid;
            return this.form.investments[field.slice(4)] || 0;
        },
        setMoney(field, v) {
            if (field === 'income') this.form.gross_income = this.incomeMode === 'monthly' ? v * 12 : v;
            else if (field === 'tds') this.form.tds_paid = v;
            else this.form.investments[field.slice(4)] = v;
        },
        get incomeHint() {
            return this.incomeMode === 'monthly'
                ? KH.t('That is :amount a year, including bonuses spread across the months.', { amount: KH.bdt(this.form.gross_income) })
                : KH.t('Everything in your salary for the year: basic, allowances, bonuses and employer PF.');
        },
        meter(inst) {
            const amount = Number(this.form.investments[inst.key] || 0);
            if (!inst.cap) return { width: 0, over: false, text: amount ? KH.t('No limit, all of it counts') : KH.t('No limit') };
            const over = amount > inst.cap;
            return {
                width: Math.min(100, (amount / inst.cap) * 100),
                over,
                text: over ? KH.t(':over above the :cap limit won’t count', { over: KH.bdt(amount - inst.cap), cap: KH.bdt(inst.cap) })
                    : (amount ? KH.t(':room room left of :cap', { room: KH.bdt(inst.cap - amount), cap: KH.bdt(inst.cap) }) : KH.t('Up to :cap', { cap: KH.bdt(inst.cap) })),
            };
        },
        applyPlan() {
            this.inv.plan.forEach((p) => { this.form.investments[p.key] = Number(this.form.investments[p.key] || 0) + p.add; });
            KH.toast(KH.t('Added :amount to your investments.', { amount: KH.bdt(this.inv.gap) }));
        },

        /* ---------- server round-trip ---------- */
        queue(delay = 220) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.calculate(), delay);
        },
        async calculate() {
            const seq = ++this.seq;
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;
            try {
                const data = await KH.request(this.routes.calculate, 'POST', { ...this.form, grouping: KH.grouping() }, { signal: this.controller.signal });
                if (seq !== this.seq) return;
                this.report = data;
                this.error = null;
                this.$nextTick(() => this.renderCharts());
            } catch (e) {
                if (e.name === 'AbortError') return;
                if (seq === this.seq) this.error = e.message;
            } finally {
                if (seq === this.seq) this.loading = false;
            }
        },

        /* ---------- saving ---------- */
        openSave() {
            if (!this.auth) { this.$refs.authDialog.showModal(); return; }
            this.saveTitle = this.calculation?.title || KH.t('Salary :year, :amount', { year: this.yearLabel, amount: KH.bdt(this.form.gross_income) });
            this.$refs.saveDialog.showModal();
            this.$nextTick(() => this.$refs.titleInput?.select());
        },
        async save(asNew = false) {
            if (!this.saveTitle.trim()) { KH.toast(KH.t('Give it a name so you can find it later.'), 'error'); return; }
            this.saving = true;
            const updating = this.calculation && !asNew;
            try {
                const payload = { ...this.form, title: this.saveTitle.trim(), notes: this.saveNotes || null, grouping: KH.grouping() };
                const data = updating
                    ? await KH.request(this.calculation.update_url || this.routes.update, 'PUT', payload)
                    : await KH.request(this.routes.store, 'POST', payload);
                this.calculation = data.calculation;
                this.snapshot = this.signature(this.form);
                KH.draft.clear();
                window.history.replaceState({}, '', data.calculation.url);
                this.$refs.saveDialog.close();
                KH.toast(data.message);
            } catch (e) {
                KH.toast(e.message, 'error');
            } finally {
                this.saving = false;
            }
        },
        get saveLabel() {
            if (!this.calculation) return KH.t('Save calculation');
            return this.dirty ? KH.t('Save changes') : KH.t('Saved');
        },

        /* ---------- derived view data ---------- */
        get yearLabel() {
            return this.options.years.find((y) => y.key === this.form.year)?.label || this.form.year;
        },
        get yearInfo() {
            return this.options.years.find((y) => y.key === this.form.year) || {};
        },
        get lede() {
            return KH.t('For :year (income :period), your income tax is', { year: this.report.rules.label, period: this.report.rules.income_year });
        },
        get verdictSub() {
            const s = this.s;
            if (s.gross <= 0) return KH.t('Enter your gross income to see your tax.');
            if (s.liability <= 0) {
                return KH.t('Your taxable income is within the tax-free limit of <strong>:limit</strong>. You keep all <strong>:monthly</strong> a month.',
                    { limit: KH.bdt(s.threshold), monthly: KH.bdt(s.take_home_monthly) });
            }
            return KH.t('That is <strong>:rate</strong> of your gross income, or <strong>:tax</strong> a month. You keep <strong>:monthly</strong> a month.',
                { rate: KH.pct(s.effective_rate), tax: KH.bdt(s.monthly_tax), monthly: KH.bdt(s.take_home_monthly) });
        },
        get ribbon() {
            const parts = this.report.charts.income_split;
            const total = parts.reduce((a, p) => a + p.value, 0) || 1;
            return parts.filter((p) => p.value > 0).map((p, i) => {
                const share = p.value / total;
                return {
                    key: p.label,
                    exempt: p.rate === null,
                    share,
                    basis: (share * 100).toFixed(3) + '%',
                    color: p.rate === null ? 'var(--exempt)' : KH.slabVar(p.rate),
                    light: p.rate !== null && p.rate >= 0.25,
                    title: p.rate === null ? KH.t('⅓ tax-free') : (p.rate === 0 ? KH.t('Tax-free') : KH.pct(p.rate, 0)),
                    label: p.label,
                    amount: p.value,
                    tax: p.rate ? p.value * p.rate : 0,
                    showText: share > 0.075,
                };
            });
        },
        get slabTotals() {
            return this.report.slabs.reduce((a, r) => ({ amount: a.amount + r.amount, tax: a.tax + r.tax }), { amount: 0, tax: 0 });
        },
        get heat() { return this.report.charts.heatmap; },
        /** The return-form guide for exactly what is on screen (saved or not). */
        get guideUrl() {
            const f = this.form;
            const params = new URLSearchParams();
            ['year', 'category', 'gross_income', 'tds_paid', 'filing', 'disabled_children'].forEach((k) => {
                if (f[k] !== undefined && f[k] !== null && f[k] !== '') params.set(k, f[k]);
            });
            if (f.new_taxpayer) params.set('new_taxpayer', 1);
            Object.entries(f.investments || {}).forEach(([k, v]) => { if (Number(v)) params.set('investments[' + k + ']', v); });
            return this.routes.guide + '?' + params.toString();
        },
        kindLabel(kind) {
            return { invest: KH.t('Invest'), claim: KH.t('Claim'), file: KH.t('File'), check: KH.t('Check'), know: KH.t('Good to know') }[kind] || '';
        },
        raiseLabel(raise) {
            return raise === 0 ? KH.t('Today') : '+' + KH.num(Math.round(raise * 100)) + '%';
        },

        /* ---------- charts ---------- */
        renderCharts() {
            if (!window.Chart || !this.$refs.slabChart) return;
            const r = this.report;
            const red = KH.css('--red');
            const green = KH.css('--green');
            const turmeric = KH.css('--turmeric');
            const muted = KH.css('--muted');
            const line = KH.css('--line');

            // 1. Tax paid in each slab
            KH.chart(this.$refs.slabChart, {
                type: 'bar',
                data: {
                    labels: r.slabs.map((s) => KH.pct(s.rate, 0)),
                    datasets: [{ label: KH.t('Tax'), data: r.slabs.map((s) => s.tax), backgroundColor: r.slabs.map((s) => KH.slabColor(s.rate)), borderRadius: 6, maxBarThickness: 46 }],
                },
                options: {
                    plugins: { legend: { display: false }, tooltip: KH.tooltipMoney(KH.t('Tax')) },
                    scales: { y: KH.axisMoney({ beginAtZero: true }), x: { grid: { display: false } } },
                },
            });

            // 2. Rebate curve
            const curve = r.investments.curve;
            KH.chart(this.$refs.rebateChart, {
                type: 'line',
                data: {
                    labels: curve.map((p) => p.investment),
                    datasets: [
                        { label: KH.t('Rebate'), data: curve.map((p) => p.rebate), borderColor: green, backgroundColor: green, tension: 0, pointRadius: 0, borderWidth: 2.5 },
                        { label: KH.t('Tax payable'), data: curve.map((p) => p.tax), borderColor: red, backgroundColor: red, tension: 0, pointRadius: 0, borderWidth: 2.5 },
                    ],
                },
                options: {
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        tooltip: { callbacks: { title: (items) => KH.t('Investing :amount', { amount: KH.bdt(items[0].label) }), label: (c) => ' ' + c.dataset.label + ': ' + KH.bdt(c.parsed.y) } },
                    },
                    scales: { y: KH.axisMoney({ beginAtZero: true }), x: { ticks: { callback: function (v) { return KH.short(this.getLabelForValue(v)); }, maxTicksLimit: 6 }, grid: { display: false } } },
                },
            });

            // 3. Raise scenarios
            const sc = r.scenarios;
            KH.chart(this.$refs.scenarioChart, {
                type: 'bar',
                data: {
                    labels: sc.map((x) => this.raiseLabel(x.raise)),
                    datasets: [
                        { type: 'bar', label: KH.t('Tax'), data: sc.map((x) => x.tax), backgroundColor: sc.map((x) => KH.slabColor(x.top_rate)), borderRadius: 6, yAxisID: 'y', maxBarThickness: 40 },
                        { type: 'line', label: KH.t('Monthly take-home'), data: sc.map((x) => x.monthly_take_home), borderColor: green, backgroundColor: green, yAxisID: 'y2', tension: 0.25, pointRadius: 3, borderWidth: 2 },
                    ],
                },
                options: {
                    interaction: { mode: 'index', intersect: false },
                    plugins: { tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + KH.bdt(c.parsed.y) } } },
                    scales: {
                        y: KH.axisMoney({ beginAtZero: true, title: { display: true, text: KH.t('Tax per year') } }),
                        y2: { position: 'right', grid: { display: false }, ticks: { callback: (v) => KH.short(v) }, title: { display: true, text: KH.t('Take-home per month') } },
                        x: { grid: { display: false } },
                    },
                },
            });

            // 4. Average vs marginal rate across incomes (linear x axis so the "You" dot sits at the real income)
            const rc = r.charts.rate_curve;
            KH.chart(this.$refs.rateChart, {
                type: 'line',
                data: {
                    datasets: [
                        { label: KH.t('Marginal slab rate'), data: rc.points.map((p) => ({ x: p.income, y: p.marginal * 100 })), borderColor: turmeric, backgroundColor: turmeric, stepped: true, pointRadius: 0, borderWidth: 2 },
                        { label: KH.t('Average (effective) rate'), data: rc.points.map((p) => ({ x: p.income, y: p.effective * 100 })), borderColor: red, backgroundColor: red, tension: 0.3, pointRadius: 0, borderWidth: 2.5 },
                        { type: 'scatter', label: KH.t('You'), data: [{ x: rc.you.income, y: rc.you.effective * 100 }], backgroundColor: KH.css('--ink'), borderColor: KH.css('--surface'), borderWidth: 2, pointRadius: 7, pointHoverRadius: 9 },
                    ],
                },
                options: {
                    parsing: false,
                    interaction: { mode: 'nearest', axis: 'x', intersect: false },
                    plugins: { tooltip: { callbacks: { title: (items) => KH.t('Income :amount', { amount: KH.bdt(items[0].parsed.x) }), label: (c) => ' ' + c.dataset.label + ': ' + KH.num(c.parsed.y.toFixed(1)) + '%' } } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: (v) => KH.num(v) + '%' }, grid: { color: line } },
                        x: { type: 'linear', min: 0, max: rc.points[rc.points.length - 1].income, ticks: { callback: (v) => KH.short(v), maxTicksLimit: 8 }, grid: { display: false } },
                    },
                },
            });

            // 5. Waterfall: gross tax → payable
            const wf = r.charts.waterfall;
            let running = 0;
            const bars = wf.map((step) => {
                if (step.kind === 'total') { running = step.value; return [0, step.value]; }
                const start = running;
                running += step.value;
                return [start, running];
            });
            KH.chart(this.$refs.waterfallChart, {
                type: 'bar',
                data: {
                    labels: wf.map((w) => w.label),
                    datasets: [{
                        data: bars,
                        backgroundColor: wf.map((w) => w.kind === 'total' ? KH.css('--ink-2') : (w.kind === 'down' ? green : red)),
                        borderRadius: 5, maxBarThickness: 48,
                    }],
                },
                options: {
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + KH.bdt(wf[c.dataIndex].value) } } },
                    scales: { y: KH.axisMoney({ beginAtZero: true }), x: { grid: { display: false } } },
                },
            });

            // 6. Monthly paycheck split
            const pc = r.charts.paycheck;
            KH.chart(this.$refs.paycheckChart, {
                type: 'doughnut',
                data: {
                    labels: pc.map((p) => p.label),
                    datasets: [{ data: pc.map((p) => p.value), backgroundColor: [green, turmeric, red], borderColor: KH.css('--surface'), borderWidth: 3 }],
                },
                options: { cutout: '64%', plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => ' ' + KH.t(':label: :amount a month', { label: c.label, amount: KH.bdt(c.parsed) }) } } } },
            });

            // 7. Tax under each year's rules
            const fu = r.charts.future;
            KH.chart(this.$refs.futureChart, {
                type: 'bar',
                data: {
                    labels: fu.map((f) => f.label + (f.projected ? ' *' : '')),
                    datasets: [{
                        label: KH.t('Tax'), data: fu.map((f) => f.tax), borderRadius: 6, maxBarThickness: 54,
                        backgroundColor: fu.map((f) => f.current ? red : (f.projected ? KH.css('--slab-1') : KH.css('--exempt'))),
                    }],
                },
                options: {
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + KH.t('Tax: :amount', { amount: KH.bdt(c.parsed.y) }), afterLabel: (c) => ' ' + KH.t('Tax-free limit: :amount', { amount: KH.bdt(fu[c.dataIndex].threshold) }) } } },
                    scales: { y: KH.axisMoney({ beginAtZero: true }), x: { grid: { display: false }, ticks: { font: { size: 11 } } } },
                },
            });

            // 8. Tax by taxpayer category
            const cats = r.charts.categories;
            KH.chart(this.$refs.categoryChart, {
                type: 'bar',
                data: {
                    labels: cats.map((c) => ({ general: KH.t('General'), women_senior: KH.t('Woman or 65+'), disabled: KH.t('Disability'), third_gender: KH.t('Third gender'), freedom_fighter: KH.t('Freedom fighter') }[c.key] || c.label)),
                    datasets: [{ label: KH.t('Tax'), data: cats.map((c) => c.tax), backgroundColor: cats.map((c) => c.current ? red : KH.css('--slab-0')), borderRadius: 6, maxBarThickness: 22 }],
                },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ' ' + KH.t('Tax: :amount', { amount: KH.bdt(c.parsed.x) }), afterLabel: (c) => ' ' + KH.t('Tax-free limit: :amount', { amount: KH.bdt(cats[c.dataIndex].threshold) }) } } },
                    scales: { x: KH.axisMoney({ beginAtZero: true }), y: { grid: { display: false }, ticks: { color: muted, font: { size: 11 } } } },
                },
            });
        },
        heatStyle(v) {
            return 'background:' + KH.heatColor(v, this.heat.min, this.heat.max);
        },
    }));
});
