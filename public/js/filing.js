/* "Do I need to file?": section 166 conditions, evaluated in the browser from server-provided rules. */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('filingCheck', (boot) => ({
        categories: boot.categories,
        obligations: boot.obligations,
        psr: boot.psr,
        tinOnly: boot.tinOnly,
        steps: boot.steps,
        routes: boot.routes,
        category: boot.category || 'general',
        income: 0,
        incomeKnown: false,
        answers: Object.fromEntries(boot.obligations.map((o) => [o.id, null])),

        bdt: (n) => KH.bdt(n),
        money(el) { KH.bindMoney(el, () => this.income, (v) => { this.income = v; this.incomeKnown = true; }); },

        get cat() { return this.categories.find((c) => c.key === this.category) || this.categories[0]; },
        get incomeOver() { return this.incomeKnown && this.income > this.cat.starts_at; },
        get answered() { return Object.values(this.answers).filter((a) => a !== null).length + (this.incomeKnown ? 1 : 0); },
        get total() { return this.obligations.length + 1; },
        get reasons() {
            const out = [];
            if (this.incomeOver) {
                out.push(KH.t('Your income of :income is above :limit, where tax starts for you.', { income: KH.bdt(this.income), limit: KH.bdt(this.cat.starts_at) }));
            }
            this.obligations.forEach((o) => { if (this.answers[o.id] === true) out.push(o.reason); });
            return out;
        },
        /* must: a condition applies; clear: everything answered and none applies; open: keep answering. */
        get verdict() {
            if (this.reasons.length) return 'must';
            return this.answered === this.total ? 'clear' : 'open';
        },
        answer(id, value) { this.answers[id] = this.answers[id] === value ? null : value; },
    }));
});
