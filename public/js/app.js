/* Kor Hishab — shared helpers (no build step). Exposed as window.KH. */
(function () {
    'use strict';

    const KH = (window.KH = {});
    const store = {
        get(key, fallback = null) { try { return localStorage.getItem(key) ?? fallback; } catch (e) { return fallback; } },
        set(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* private mode */ } },
        remove(key) { try { localStorage.removeItem(key); } catch (e) { /* ignore */ } },
    };

    /* ---------- language ---------- */
    // window.KH_LANG is loaded from /lang/<locale>.js on non-English pages.
    KH.locale = () => document.documentElement.lang || 'en';
    /** Translate English text and fill :placeholders, mirroring Laravel's __(). */
    KH.t = (text, replace = {}) => {
        let out = (window.KH_LANG && window.KH_LANG[text]) || text;
        Object.keys(replace).sort((a, b) => b.length - a.length).forEach((key) => {
            out = out.split(':' + key).join(String(replace[key]));
        });
        return out;
    };

    /* ---------- numbers ---------- */
    const BN_DIGITS = '০১২৩৪৫৬৭৮৯';
    KH.digitStyle = () => (KH.locale() === 'bn' && store.get('kh:digits', 'bn') !== 'latin' ? 'bn' : 'latin');
    /** Swap 0-9 for ০-৯ when Bangla digits are on. */
    KH.num = (text) => (KH.digitStyle() === 'bn' ? String(text).replace(/[0-9]/g, (d) => BN_DIGITS[d]) : String(text));
    const toLatin = (text) => String(text ?? '').replace(/[০-৯]/g, (d) => String(BN_DIGITS.indexOf(d)));
    const isDigit = (ch) => /[0-9০-৯]/.test(ch);

    KH.grouping = () => store.get('kh:grouping', 'intl');
    const formatters = {};
    KH.group = (n) => {
        const style = KH.grouping();
        formatters[style] ??= new Intl.NumberFormat(style === 'lakh' ? 'en-IN' : 'en-US', { maximumFractionDigits: 0 });
        return KH.num(formatters[style].format(Math.round(Number(n) || 0)));
    };
    KH.bdt = (n) => {
        const v = Math.round(Number(n) || 0);
        return (v < 0 ? '−' : '') + '৳' + KH.group(Math.abs(v));
    };
    KH.short = (n) => {
        const v = Math.abs(Number(n) || 0);
        if (v >= 10000000) return '৳' + KH.num((v / 10000000).toFixed(v >= 100000000 ? 0 : 1)) + ' ' + KH.t('cr');
        if (v >= 100000) return '৳' + KH.num((v / 100000).toFixed(v >= 1000000 ? 0 : 1)) + ' ' + KH.t('lakh');
        if (v >= 1000) return '৳' + KH.num(Math.round(v / 1000)) + KH.t('k');
        return '৳' + KH.num(Math.round(v));
    };
    KH.pct = (r, d = 1) => KH.num(((Number(r) || 0) * 100).toFixed(d)) + '%';
    /** Read a typed amount; accepts Latin and Bangla digits and ignores separators. */
    KH.parse = (s) => {
        const digits = toLatin(s).replace(/[^0-9]/g, '');
        return digits ? Math.min(Number(digits), 1e10) : 0;
    };

    /**
     * Reformat a money input while typing without throwing the caret to the end:
     * count the digits before the caret, reformat, then put the caret back after
     * the same number of digits.
     */
    KH.reformat = (el, set) => {
        const caret = el.selectionStart ?? el.value.length;
        const digitsBefore = [...el.value.slice(0, caret)].filter(isDigit).length;
        const value = KH.parse(el.value);
        set(value);
        const text = [...el.value].some(isDigit) ? KH.group(value) : '';
        el.value = text;
        let i = 0;
        let seen = 0;
        while (i < text.length && seen < digitsBefore) {
            if (isDigit(text[i])) seen++;
            i++;
        }
        try { el.setSelectionRange(i, i); } catch (e) { /* not focusable */ }
    };

    /** Wire an <input> to a numeric getter/setter, staying in sync with outside changes. */
    KH.bindMoney = (el, get, set) => {
        const show = () => { const v = get(); el.value = v ? KH.group(v) : ''; };
        show();
        el.addEventListener('input', () => KH.reformat(el, set));
        el.addEventListener('blur', show);
        if (window.Alpine) {
            window.Alpine.effect(() => { get(); if (document.activeElement !== el) show(); });
        }
    };

    /* ---------- requests ---------- */
    KH.csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    KH.request = async (url, method = 'GET', body = undefined, { signal } = {}) => {
        const res = await fetch(url, {
            method,
            signal,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': KH.csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        let data = null;
        try { data = await res.json(); } catch (e) { /* empty body */ }
        if (!res.ok) {
            const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
            const message = firstError || ({
                401: KH.t('Sign in to do that.'),
                419: KH.t('Your session expired. Refresh the page and try again.'),
                429: KH.t('That was a lot of requests. Wait a few seconds and try again.'),
            }[res.status]) || data?.message || KH.t('The server could not complete that. Try again.');
            const err = new Error(message);
            err.status = res.status;
            err.errors = data?.errors ?? null;
            throw err;
        }
        return data;
    };

    /* ---------- toasts ---------- */
    KH.toast = (message, kind = '') => {
        const host = document.getElementById('toasts');
        if (!host || !message) return;
        const el = document.createElement('div');
        el.className = 'toast ' + kind;
        el.setAttribute('role', kind === 'error' ? 'alert' : 'status');
        el.textContent = message;
        host.appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, 3800);
        setTimeout(() => el.remove(), 4200);
    };

    /* ---------- drafts (calculator inputs survive reloads and sign-in) ---------- */
    KH.draft = {
        key: 'kh:draft',
        load() { try { return JSON.parse(store.get(this.key) || 'null'); } catch (e) { return null; } },
        save(value) { store.set(this.key, JSON.stringify(value)); },
        clear() { store.remove(this.key); },
    };

    /* ---------- theme & grouping ---------- */
    KH.css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    KH.slabColor = (rate) => {
        const map = { 0: '--slab-0', 0.1: '--slab-1', 0.15: '--slab-2', 0.2: '--slab-3', 0.25: '--slab-4', 0.3: '--slab-5', 0.35: '--slab-6' };
        const key = Object.keys(map).reduce((best, k) => Math.abs(k - rate) < Math.abs(best - rate) ? k : best, 0);
        return KH.css(map[key]);
    };
    // For HTML elements: a var() reference follows theme switches without re-rendering.
    KH.slabVar = (rate) => {
        const steps = [0, 0.1, 0.15, 0.2, 0.25, 0.3, 0.35];
        const i = steps.reduce((best, k, idx) => Math.abs(k - rate) < Math.abs(steps[best] - rate) ? idx : best, 0);
        return 'var(--slab-' + i + ')';
    };
    KH.isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';

    function initToggles() {
        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => btn.addEventListener('click', () => {
            const next = KH.isDark() ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            store.set('kh:theme', next);
            applyChartDefaults();
            window.dispatchEvent(new CustomEvent('kh:theme'));
        }));

        const current = KH.grouping();
        document.querySelectorAll('[data-grouping]').forEach((btn) => {
            btn.setAttribute('aria-pressed', String(btn.dataset.grouping === current));
            btn.addEventListener('click', () => {
                if (btn.dataset.grouping === KH.grouping()) return;
                store.set('kh:grouping', btn.dataset.grouping);
                document.cookie = 'kh_grouping=' + btn.dataset.grouping + ';path=/;max-age=31536000;samesite=lax';
                location.reload();
            });
        });
        // Keep the server's cookie in step with the stored preference.
        if (!document.cookie.includes('kh_grouping=' + current)) {
            document.cookie = 'kh_grouping=' + current + ';path=/;max-age=31536000;samesite=lax';
        }

        // Bangla or Latin digits (only offered on Bangla pages). The server reads kh_digits too.
        const digits = store.get('kh:digits', 'bn');
        document.querySelectorAll('[data-digits]').forEach((btn) => {
            btn.setAttribute('aria-pressed', String(btn.dataset.digits === digits));
            btn.addEventListener('click', () => {
                if (btn.dataset.digits === store.get('kh:digits', 'bn')) return;
                store.set('kh:digits', btn.dataset.digits);
                document.cookie = 'kh_digits=' + btn.dataset.digits + ';path=/;max-age=31536000;samesite=lax';
                location.reload();
            });
        });
        if (!document.cookie.includes('kh_digits=' + digits)) {
            document.cookie = 'kh_digits=' + digits + ';path=/;max-age=31536000;samesite=lax';
        }
    }

    /* ---------- charts ---------- */
    function applyChartDefaults() {
        if (!window.Chart) return;
        const C = window.Chart;
        C.defaults.font.family = '"Hind Siliguri", system-ui, sans-serif';
        C.defaults.locale = KH.digitStyle() === 'bn' ? 'bn-BD' : 'en-US';
        C.defaults.font.size = 12;
        C.defaults.color = KH.css('--muted');
        C.defaults.borderColor = KH.css('--line');
        C.defaults.maintainAspectRatio = false;
        C.defaults.plugins.legend.labels.usePointStyle = true;
        C.defaults.plugins.legend.labels.boxWidth = 8;
        C.defaults.plugins.legend.labels.boxHeight = 8;
        C.defaults.plugins.tooltip.backgroundColor = KH.css('--ink');
        C.defaults.plugins.tooltip.titleColor = KH.css('--paper');
        C.defaults.plugins.tooltip.bodyColor = KH.css('--paper');
        C.defaults.plugins.tooltip.padding = 10;
        C.defaults.plugins.tooltip.cornerRadius = 8;
        C.defaults.animation.duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 450;
    }

    const charts = new WeakMap();
    /** Create a chart, or update it in place if one already lives on this canvas. */
    KH.chart = (canvas, config) => {
        if (!canvas || !window.Chart) return null;
        const existing = charts.get(canvas);
        if (existing && existing.config.type === config.type) {
            existing.data = config.data;
            existing.options = config.options || {};
            existing.update();
            return existing;
        }
        existing?.destroy();
        const chart = new window.Chart(canvas, config);
        charts.set(canvas, chart);
        return chart;
    };
    KH.axisMoney = (axis = {}) => ({ ...axis, ticks: { ...(axis.ticks || {}), callback: (v) => KH.short(v) }, grid: { color: KH.css('--line') } });
    KH.tooltipMoney = (label) => ({ callbacks: { label: (ctx) => ' ' + (label ? label + ': ' : (ctx.dataset.label ? ctx.dataset.label + ': ' : '')) + KH.bdt(ctx.parsed.y ?? ctx.parsed) } });

    KH.heatColor = (value, min, max) => {
        const t = max > min ? (value - min) / (max - min) : 0;
        const stops = [[79, 174, 126], [233, 196, 106], [217, 71, 90]];
        const seg = t < 0.5 ? 0 : 1;
        const local = t < 0.5 ? t / 0.5 : (t - 0.5) / 0.5;
        const c = stops[seg].map((a, i) => Math.round(a + (stops[seg + 1][i] - a) * local));
        return `rgb(${c[0]}, ${c[1]}, ${c[2]})`;
    };

    /* ---------- icons used in predictions ---------- */
    const ICONS = {
        'trending-up': '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
        'sprout': '<path d="M7 20h10M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8zM14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2z"/>',
        'mountain': '<path d="m8 3 4 8 5-5 5 15H2L8 3z"/>',
        'coins': '<circle cx="8" cy="8" r="6"/><path d="M18.1 10.4A6 6 0 1 1 10.3 18M7 6h1v4M16.7 13.9l.7.7-2.8 2.8"/>',
        'shield': '<path d="M20 13c0 5-3.5 7.5-7.7 9a1 1 0 0 1-.7 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1.2 1.2 0 0 1 1.6 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"/>',
        'target': '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'check': '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'alert': '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/>',
        'percent': '<path d="M19 5 5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'calendar': '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'gift': '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
        'wallet': '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
        'clock': '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'receipt': '<path d="M4 2v20l3-2 3 2 2-2 2 2 3-2 3 2V2l-3 2-3-2-2 2-2-2-3 2z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    };
    KH.icon = (name, size = 18) => `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name] || ICONS.check}</svg>`;

    /* ---------- boot ---------- */
    document.addEventListener('DOMContentLoaded', () => {
        applyChartDefaults();
        initToggles();
        if (window.__flash) KH.toast(window.__flash);
        // Close the account menu when clicking elsewhere.
        document.addEventListener('click', (e) => {
            document.querySelectorAll('details.menu[open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
        });
    });
})();
