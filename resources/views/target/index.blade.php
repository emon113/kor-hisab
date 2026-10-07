<x-layout title="Target tax">
    @push('scripts')
        <script src="{{ asset('js/target.js') }}?v={{ @filemtime(public_path('js/target.js')) }}"></script>
    @endpush

    <div x-data="targetTax(@js($boot))">
        <div class="page-head">
            <div>
                <h1>Work backwards from a tax amount</h1>
                <p>Type the tax you want to pay. You’ll see the gross salary that produces it, split into salary components by your own ratios.</p>
            </div>
        </div>

        <div class="workspace">
            <aside class="slip slip-free panel" aria-label="Target and assumptions">
                <div class="field">
                    <label class="label" for="target">Tax I want to pay</label>
                    <div class="money money-lg"><span>৳</span><input id="target" class="input" inputmode="numeric" autocomplete="off" x-init="money($el, 'target_tax')"></div>
                    <p class="hint">Per year, after any investment rebate.</p>
                </div>

                <div class="field">
                    <span class="label">Investment rebate</span>
                    <div class="seg" role="group" aria-label="Investment rebate assumption" style="width:100%">
                        <button type="button" style="flex:1" :aria-pressed="form.rebate_mode === 'none'" @click="form.rebate_mode = 'none'">None</button>
                        <button type="button" style="flex:1" :aria-pressed="form.rebate_mode === 'custom'" @click="form.rebate_mode = 'custom'">Custom</button>
                        <button type="button" style="flex:1" :aria-pressed="form.rebate_mode === 'max'" @click="form.rebate_mode = 'max'">Maximum</button>
                    </div>
                    <div class="money" x-show="form.rebate_mode === 'custom'" style="margin-top:8px">
                        <span>৳</span><input class="input" inputmode="numeric" autocomplete="off" placeholder="Eligible investment" aria-label="Eligible investment" x-init="money($el, 'investment')">
                    </div>
                    <p class="hint" x-show="form.rebate_mode === 'max'">Assumes you invest enough for the full rebate.</p>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="label" for="t-year">Tax year</label>
                        <select id="t-year" class="select" x-model="form.year">
                            <template x-for="y in options.years" :key="y.key"><option :value="y.key" x-text="y.label" :selected="y.key === form.year"></option></template>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="t-cat">You are</label>
                        <select id="t-cat" class="select" x-model="form.category">
                            <template x-for="c in options.categories" :key="c.key"><option :value="c.key" x-text="c.label" :selected="c.key === form.category"></option></template>
                        </select>
                    </div>
                </div>

                <div class="slip-group">
                    <h2>Salary split</h2>
                    <p>Share of gross salary per component. Basic takes any rounding so the total is exact.</p>
                    <table class="table">
                        <tbody>
                            <template x-for="(label, key) in labels" :key="key">
                                <tr>
                                    <td><label :for="'r-' + key" x-text="label"></label></td>
                                    <td class="r">
                                        <span class="ratio-cell">
                                            <input :id="'r-' + key" type="number" min="0" max="100" step="0.0001" class="input ratio-input" x-model.number="form.ratios[key]">
                                            <span>%</span>
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot><tr><td>Total</td><td class="r" :class="{ 'tone-watch': Math.abs(ratioTotal - 100) > 0.01 }" x-text="ratioTotal.toFixed(2) + '%'"></td></tr></tfoot>
                    </table>
                    <p class="hint" x-show="Math.abs(ratioTotal - 100) > 0.01">Ratios are scaled to add up to 100%.</p>
                    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
                        <button type="button" class="btn btn-ghost btn-sm" @click="resetRatios()">Reset to default</button>
                        <button type="button" class="btn btn-ghost btn-sm" x-show="auth" @click="saveRatios()">Save as my default</button>
                    </div>
                </div>
            </aside>

            <section class="results" aria-live="polite">
                <div class="loading-bar" :class="{ on: loading }" aria-hidden="true"></div>
                <div class="callout cost" x-show="error" x-text="error" role="alert" style="margin-bottom:16px"></div>

                <div class="panel target-result">
                    <p class="verdict-lede" x-text="'To pay ' + bdt(form.target_tax) + ' in tax, your gross salary is'"></p>
                    <div class="target-amount" x-text="bdt(result.gross)"></div>
                    <p class="verdict-sub" style="margin-top:8px" x-html="'That is <strong>' + bdt(result.monthly) + '</strong> a month. Tax on it works out to <strong>' + bdt(result.tax) + '</strong>.'"></p>
                    <div class="callout warn" style="margin-top:16px" x-show="result.warning" x-text="result.warning"></div>
                    <dl class="ledger" style="margin-bottom:0">
                        <div><dt>Tax-free salary</dt><dd x-text="bdt(result.exemption)"></dd></div>
                        <div><dt>Taxable income</dt><dd x-text="bdt(result.taxable)"></dd></div>
                        <div><dt>Tax before rebate</dt><dd x-text="bdt(result.gross_tax)"></dd></div>
                        <div><dt>Rebate</dt><dd class="tone-good" x-text="result.rebate ? '−' + bdt(result.rebate) : bdt(0)"></dd></div>
                        <div><dt>Tax</dt><dd class="tone-cost" x-text="bdt(result.tax)"></dd></div>
                        <div><dt x-text="result.investment_needed ? 'Invest for this' : 'Tax-free up to'"></dt><dd x-text="bdt(result.investment_needed || result.tax_free_gross)"></dd></div>
                    </dl>
                </div>

                <section class="sec" aria-labelledby="h-breakdown">
                    <div class="sec-head">
                        <div><h2 id="h-breakdown">Salary breakdown</h2><p>Split by your ratios.</p></div>
                        <button type="button" class="btn btn-ghost btn-sm" @click="copy()">Copy breakdown</button>
                    </div>
                    <div class="split">
                        <div class="table-wrap">
                            <table class="table">
                                <thead><tr><th>#</th><th>Component</th><th class="r">Share</th><th class="r">Per year</th><th class="r">Per month</th></tr></thead>
                                <tbody>
                                    <template x-for="(c, i) in result.components" :key="c.key">
                                        <tr>
                                            <td class="muted" x-text="i + 1"></td>
                                            <td x-text="c.label"></td>
                                            <td class="r" x-text="pct(c.ratio)"></td>
                                            <td class="r" x-text="'TK. ' + group(c.amount) + '/-'"></td>
                                            <td class="r" x-text="bdt(c.monthly)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot><tr><td></td><td>Total</td><td class="r">100%</td><td class="r" x-text="'TK. ' + group(result.gross) + '/-'"></td><td class="r" x-text="bdt(result.monthly)"></td></tr></tfoot>
                            </table>
                        </div>
                        <div class="copy-block" x-text="result.copy_text" aria-label="Copy-ready breakdown"></div>
                    </div>
                </section>

                <section class="sec" aria-labelledby="h-target-charts">
                    <div class="sec-head"><div><h2 id="h-target-charts">Visual breakdown</h2></div></div>
                    <div class="charts">
                        <div class="panel chart-card third">
                            <h3>Salary composition</h3>
                            <p>Share of each component.</p>
                            <div class="chart-box"><canvas x-ref="splitChart" role="img" aria-label="Doughnut chart of salary components"></canvas></div>
                        </div>
                        <div class="panel chart-card two-thirds">
                            <h3>Monthly amounts</h3>
                            <p>What each line looks like on a payslip.</p>
                            <div class="chart-box"><canvas x-ref="monthlyChart" role="img" aria-label="Bar chart of monthly salary components"></canvas></div>
                        </div>
                        <div class="panel chart-card wide">
                            <h3>Where your target sits on the tax curve</h3>
                            <p>Tax at every income with your rebate assumption. The dot is the salary you need.</p>
                            <div class="chart-box"><canvas x-ref="curveChart" role="img" aria-label="Tax by income with the target marked"></canvas></div>
                        </div>
                    </div>
                </section>
            </section>
        </div>
    </div>
</x-layout>
