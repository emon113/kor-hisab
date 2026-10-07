/* Salary certificate review: the user checks what was read, with a live tax estimate, then saves. */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('certificateReview', (boot) => ({
        options: boot.options,
        routes: boot.routes,
        data: JSON.parse(JSON.stringify(boot.data)),
        found: boot.found,
        evidence: boot.evidence,
        certificateTotal: boot.certificateTotal,
        estimate: null,
        loading: false,
        saving: false,
        errors: {},
        timer: null,
        seq: 0,

        init() {
            this.$watch('data', () => this.queue());
            this.queue(0);
        },

        bdt: (n) => KH.bdt(n),
        money(el, group, key) {
            const get = () => (group ? this.data[group][key] : this.data[key]) || 0;
            const set = (v) => { if (group) this.data[group][key] = v; else this.data[key] = v; };
            KH.bindMoney(el, get, set);
        },
        isFound(key) { return this.found.includes(key); },
        source(key) { return this.evidence[key] ? KH.t('Read from: :line', { line: this.evidence[key].replace(/\t/g, '  ') }) : ''; },

        sum(group) { return Object.values(this.data[group] || {}).reduce((a, v) => a + (Number(v) || 0), 0); },
        get gross() { return this.sum('components') + this.sum('perks'); },
        get totalMatches() { return this.certificateTotal && Math.abs(this.certificateTotal - this.gross) <= Math.max(10, this.certificateTotal * 0.005); },

        queue(delay = 300) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.refresh(), delay);
        },
        /* A quick estimate with the calculator's own endpoint; the report recomputes everything on the server. */
        async refresh() {
            const seq = ++this.seq;
            this.loading = true;
            try {
                const r = await KH.request(this.routes.calculate, 'POST', {
                    year: this.data.year, category: this.data.category, gross_income: this.gross,
                    investments: this.data.investments, tds_paid: this.data.tds, filing: this.data.filing,
                    disabled_children: this.data.disabled_children, new_taxpayer: this.data.new_taxpayer, grouping: KH.grouping(),
                });
                if (seq === this.seq) this.estimate = r.summary;
            } catch (e) {
                if (seq === this.seq) this.estimate = null;
            } finally {
                if (seq === this.seq) this.loading = false;
            }
        },

        async save() {
            if (this.saving) return;
            this.saving = true;
            this.errors = {};
            try {
                const res = await KH.request(this.routes.save, 'PUT', this.data);
                window.location.href = res.redirect;
            } catch (e) {
                this.errors = e.errors || {};
                KH.toast(e.message, 'error');
                this.saving = false;
            }
        },
    }));
});
