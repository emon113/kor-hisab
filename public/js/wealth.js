/* Assets & liabilities statement: live IT-10B arithmetic while typing, saved on demand. */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('wealth', (boot) => ({
        groups: boot.groups,
        routes: boot.routes,
        data: JSON.parse(JSON.stringify(boot.data)),
        notes: boot.notes || '',
        opening: boot.opening,
        hasPreviousStatement: boot.hasPreviousStatement,
        previousFromStatement: boot.hasPreviousStatement ? boot.previous : null,
        tolerance: boot.tolerance,
        exists: boot.exists,
        saving: false,
        snapshot: '',

        init() {
            this.snapshot = this.signature();
            window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); this.save(); }
            });
        },

        bdt: (n) => KH.bdt(n),
        signature() { return JSON.stringify([this.data, this.notes, this.opening]); },
        get dirty() { return this.signature() !== this.snapshot; },

        money(el, group, line) {
            KH.bindMoney(el, () => this.data[group][line] || 0, (v) => { this.data[group][line] = v; });
        },
        openingInput(el) {
            KH.bindMoney(el, () => this.opening || 0, (v) => { this.opening = v || null; });
        },

        total(group) {
            return Object.values(this.data[group] || {}).reduce((a, v) => a + (Number(v) || 0), 0);
        },
        /* Same formula as App\Services\Wealth\WealthReconciler; the server recomputes on save. */
        get result() {
            const receipts = this.total('receipts');
            const expenses = this.total('expenses');
            const net = this.total('assets') - this.total('liabilities');
            const previous = this.hasPreviousStatement ? this.previousFromStatement : (this.opening === null || this.opening === '' ? null : Number(this.opening));
            const expected = previous === null ? null : previous + receipts - expenses;
            const gap = expected === null ? null : net - expected;
            const tolerance = Math.max(this.tolerance.min, receipts * this.tolerance.share_of_sources);
            let status = 'no_baseline';
            if (gap !== null) status = Math.abs(gap) <= tolerance ? 'balanced' : (gap > 0 ? 'unexplained' : 'understated');
            return { receipts, expenses, net, previous, expected, gap, status };
        },
        get statusText() {
            const r = this.result;
            const gap = KH.bdt(Math.abs(r.gap || 0));
            return {
                balanced: KH.t('Balanced. Your income and expenses explain the change in your net wealth.'),
                unexplained: KH.t('Your net wealth grew :gap more than your income explains. Check for income, gifts or loans you have not entered, or assets valued above cost.', { gap }),
                understated: KH.t('Your net wealth is :gap lower than your income and expenses imply. Check for expenses, losses or assets you have not entered.', { gap }),
                no_baseline: KH.t('Enter last year’s net wealth to check whether this year adds up.'),
            }[r.status];
        },
        get statusTone() {
            return { balanced: 'good', unexplained: 'cost', understated: 'watch', no_baseline: 'info' }[this.result.status];
        },

        async save() {
            if (this.saving) return;
            this.saving = true;
            try {
                const data = await KH.request(this.routes.save, 'PUT', { ...this.data, notes: this.notes || null, opening_net_wealth: this.hasPreviousStatement ? null : this.opening });
                this.snapshot = this.signature();
                this.exists = true;
                KH.toast(data.message);
            } catch (e) {
                KH.toast(e.message, 'error');
            } finally {
                this.saving = false;
            }
        },
    }));
});
