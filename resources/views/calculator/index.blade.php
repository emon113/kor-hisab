<x-layout :title="$calculation?->title ?? 'Income tax calculator'">
    @push('scripts')
        <script src="{{ asset('js/calculator.js') }}?v={{ @filemtime(public_path('js/calculator.js')) }}"></script>
    @endpush

    <div class="workspace" x-data="calculator(@js($boot))">

        {{-- ============ Inputs ============ --}}
        <aside class="slip panel" aria-label="Your details">
            <template x-if="calculation">
                <div class="saved-chip">
                    <x-icon name="folder" size="16" />
                    <strong x-text="calculation.title"></strong>
                    <span class="dirty-dot" x-show="dirty" title="Unsaved changes"></span>
                </div>
            </template>

            <div class="slip-head">
                <h1>Your details</h1>
                <a :href="routes.fresh" class="btn btn-quiet btn-sm" x-show="calculation || dirty">
                    <x-icon name="plus" size="16" /> New
                </a>
            </div>

            <div class="field">
                <div class="label">
                    <label for="income">Gross income</label>
                    <div class="seg" role="group" aria-label="Income period">
                        <button type="button" :aria-pressed="incomeMode === 'annual'" @click="incomeMode = 'annual'">Yearly</button>
                        <button type="button" :aria-pressed="incomeMode === 'monthly'" @click="incomeMode = 'monthly'">Monthly</button>
                    </div>
                </div>
                <div class="money money-lg">
                    <span>৳</span>
                    <input id="income" class="input" inputmode="numeric" autocomplete="off" x-init="money($el, 'income')" aria-describedby="income-hint">
                </div>
                <p class="hint" id="income-hint" x-text="incomeHint"></p>
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="label" for="year">Tax year</label>
                    <select id="year" class="select" x-model="form.year">
                        <template x-for="y in options.years" :key="y.key">
                            <option :value="y.key" x-text="y.label + (y.projected ? ' (roadmap)' : '')" :selected="y.key === form.year"></option>
                        </template>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="category">You are</label>
                    <select id="category" class="select" x-model="form.category">
                        <template x-for="c in options.categories" :key="c.key">
                            <option :value="c.key" x-text="c.label" :selected="c.key === form.category"></option>
                        </template>
                    </select>
                </div>
            </div>
            <p class="hint" style="margin-top:6px" x-text="'Income year ' + (yearInfo.income_year || '') + (yearInfo.projected ? '. Future rules from the enacted roadmap.' : '.')"></p>

            <div class="slip-group">
                <h2>Investments this year</h2>
                <p>Each counts up to its limit. The rebate is 10% of the total, capped at 3% of taxable income.</p>
                <template x-for="inst in options.instruments" :key="inst.key">
                    <div class="inv-row">
                        <label class="label" :for="'inv-' + inst.key">
                            <span x-text="inst.label"></span>
                            <small x-text="meter(inst).text"></small>
                        </label>
                        <div class="money">
                            <span>৳</span>
                            <input class="input" :id="'inv-' + inst.key" inputmode="numeric" autocomplete="off" placeholder="0" x-init="money($el, 'inv:' + inst.key)">
                        </div>
                        <div class="inv-meter" :class="{ over: meter(inst).over }" x-show="inst.cap"><i :style="'width:' + meter(inst).width + '%'"></i></div>
                    </div>
                </template>
            </div>

            <div class="slip-group">
                <h2>Tax already paid</h2>
                <p>TDS your employer deducted, plus any advance tax (car, interest, instalments).</p>
                <div class="money">
                    <span>৳</span>
                    <input class="input" id="tds" inputmode="numeric" autocomplete="off" placeholder="0" aria-label="Tax already paid" x-init="money($el, 'tds')">
                </div>
                <div class="field">
                    <label class="label" for="filing">When will you file?</label>
                    <select id="filing" class="select" x-model="form.filing">
                        <template x-for="f in options.filing" :key="f.key">
                            <option :value="f.key" x-text="f.label + ': ' + f.hint" :selected="f.key === form.filing"></option>
                        </template>
                    </select>
                </div>
                <details style="margin-top:14px">
                    <summary class="hint" style="cursor:pointer">More options</summary>
                    <div style="display:grid;gap:12px;margin-top:12px">
                        <label class="check"><input type="checkbox" x-model="form.new_taxpayer"> This is my first tax return</label>
                        <div class="field">
                            <label class="label" for="children">Disabled children or dependents <small>+৳50,000 tax-free each</small></label>
                            <input id="children" type="number" min="0" max="10" class="input" x-model.number="form.disabled_children">
                        </div>
                    </div>
                </details>
            </div>

            <div class="slip-actions">
                <button type="button" class="btn btn-primary btn-block" @click="openSave()" :disabled="calculation && !dirty">
                    <x-icon name="save" size="18" /> <span x-text="saveLabel"></span>
                </button>
                <button type="button" class="btn btn-ghost btn-block" x-show="calculation" @click="saveTitle = calculation.title + ' (copy)'; $refs.saveDialog.showModal()">
                    Save as a new calculation
                </button>
            </div>
        </aside>

        {{-- ============ Results ============ --}}
        <section class="results" aria-label="Results">
            <div class="loading-bar" :class="{ on: loading }" aria-hidden="true"></div>
            <div class="callout cost" x-show="error" x-text="error" role="alert" style="margin-bottom:16px"></div>

            <div class="verdict" aria-live="polite">
                <p class="verdict-lede" x-text="lede"></p>
                <div class="verdict-amount" :class="{ zero: s.liability <= 0 }" x-text="bdt(s.liability)"></div>
                <p class="verdict-sub" x-html="verdictSub"></p>

                <div class="ribbon-wrap" x-show="s.gross > 0">
                    <div class="ribbon" role="img" :aria-label="'Your income split: ' + ribbon.map(r => r.title + ' ' + bdt(r.amount)).join(', ')">
                        <template x-for="seg in ribbon" :key="seg.key">
                            <div class="ribbon-seg" :class="{ exempt: seg.exempt, 'light-text': seg.light }"
                                 :style="'flex-basis:' + seg.basis + (seg.exempt ? '' : ';background:' + seg.color)"
                                 :title="seg.label + ': ' + bdt(seg.amount) + (seg.tax ? ' (tax ' + bdt(seg.tax) + ')' : '')">
                                <b x-show="seg.showText" x-text="seg.title"></b>
                                <span x-show="seg.showText" x-text="bdt(seg.amount)"></span>
                            </div>
                        </template>
                    </div>
                    <div class="ribbon-legend">
                        <template x-for="seg in ribbon" :key="'l' + seg.key">
                            <span><i :style="'background:' + seg.color"></i><span x-text="seg.label + ' ' + bdt(seg.amount)"></span></span>
                        </template>
                    </div>
                    <div class="next-flag" x-show="!next.at_top && next.raise_needed">
                        <span x-show="next.rate_now > 0">Raises beyond <strong x-text="bdt(next.raise_needed)"></strong> are taxed in the <strong x-text="pct(next.rate_next, 0)"></strong> slab.</span>
                        <span x-show="next.rate_now == 0">You can earn <strong x-text="bdt(next.raise_needed)"></strong> more before any tax applies.</span>
                    </div>
                </div>
            </div>

            <dl class="ledger">
                <div><dt>Gross income</dt><dd x-text="bdt(s.gross)"></dd></div>
                <div><dt>Tax-free salary</dt><dd x-text="bdt(s.exemption)"></dd></div>
                <div><dt>Taxable income</dt><dd x-text="bdt(s.taxable)"></dd></div>
                <div><dt>Tax before rebate</dt><dd x-text="bdt(s.gross_tax)"></dd></div>
                <div><dt>Investment rebate</dt><dd class="tone-good" x-text="s.rebate ? '−' + bdt(s.rebate) : bdt(0)"></dd></div>
                <div>
                    <dt x-text="s.payable >= 0 ? 'Left to pay' : 'Refund due'"></dt>
                    <dd :class="s.payable > 0 ? 'tone-cost' : 'tone-good'" x-text="bdt(Math.abs(s.payable))"></dd>
                </div>
            </dl>

            {{-- Slabs --}}
            <section class="sec" aria-labelledby="h-slabs">
                <div class="sec-head">
                    <div>
                        <h2 id="h-slabs">Slab by slab</h2>
                        <p x-text="'Tax-free limit for you: ' + bdt(report.rules.threshold) + '. Only the part of your income inside each slab pays that slab’s rate.'"></p>
                    </div>
                </div>
                <div class="split">
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Slab</th><th>Rate</th><th class="r">Your income</th><th>Filled</th><th class="r">Tax</th></tr></thead>
                            <tbody>
                                <template x-for="row in report.slabs" :key="row.label + row.rate">
                                    <tr :class="{ 'is-top': row.is_top && s.taxable > 0 }">
                                        <td x-text="row.label"></td>
                                        <td><span class="rate-pill" :class="{ hot: row.rate >= 0.25 }" :style="'background:' + slabVar(row.rate)" x-text="pct(row.rate, 0)"></span></td>
                                        <td class="r" x-text="bdt(row.amount)"></td>
                                        <td><div class="fill"><i :style="'width:' + (row.fill * 100) + '%;background:' + slabVar(row.rate)"></i></div></td>
                                        <td class="r" x-text="bdt(row.tax)"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot><tr><td colspan="2">Total</td><td class="r" x-text="bdt(slabTotals.amount)"></td><td></td><td class="r" x-text="bdt(slabTotals.tax)"></td></tr></tfoot>
                        </table>
                    </div>
                    <div class="panel chart-card" style="grid-column:auto">
                        <h3>Tax from each slab</h3>
                        <p>Your top slab does most of the work.</p>
                        <div class="chart-box short"><canvas x-ref="slabChart" role="img" aria-label="Bar chart of tax paid in each slab"></canvas></div>
                    </div>
                </div>
            </section>

            {{-- Rebate optimiser --}}
            <section class="sec" aria-labelledby="h-rebate">
                <div class="sec-head">
                    <div>
                        <h2 id="h-rebate">Get the full rebate</h2>
                        <p x-text="'The rebate is the lowest of 10% of eligible investment, 3% of taxable income, and ' + bdt(report.rules.rebate_cap) + '.'"></p>
                    </div>
                </div>
                <div class="split">
                    <div class="panel optimizer">
                        <dl class="opt-figures">
                            <div><dt>Rebate now</dt><dd class="tone-good" x-text="bdt(inv.rebate)"></dd></div>
                            <div><dt>Maximum rebate</dt><dd x-text="bdt(inv.rebate_max)"></dd></div>
                            <div><dt>Invest in total</dt><dd x-text="bdt(inv.needed)"></dd></div>
                        </dl>
                        <div class="meter" role="progressbar" :aria-valuenow="Math.round(inv.progress * 100)" aria-valuemin="0" aria-valuemax="100"><i :style="'width:' + (inv.progress * 100) + '%'"></i></div>
                        <div class="meter-caption">
                            <span x-text="bdt(inv.eligible) + ' counted'"></span>
                            <span x-text="Math.round(inv.progress * 100) + '% of the way'"></span>
                        </div>

                        <div class="plan" x-show="inv.gap > 0">
                            <p><strong x-text="'Invest ' + bdt(inv.gap) + ' more to save ' + bdt(inv.extra_saving)"></strong></p>
                            <p class="hint">One way to fill it, using the instruments with room left first:</p>
                            <ul>
                                <template x-for="p in inv.plan" :key="p.key">
                                    <li><span x-text="p.label"></span><strong class="num" x-text="'+' + bdt(p.add)"></strong></li>
                                </template>
                            </ul>
                            <button type="button" class="btn btn-ghost btn-sm" @click="applyPlan()">Add this to my investments</button>
                        </div>
                        <div class="callout" style="margin-top:18px" x-show="inv.gap <= 0 && inv.needed > 0">You have unlocked the maximum rebate for this income.</div>
                        <div class="callout warn" style="margin-top:12px" x-show="inv.wasted > 0 && s.taxable > s.threshold" x-text="bdt(inv.wasted) + ' of your investment earns no rebate this year (above the limit or an instrument’s cap).'"></div>
                    </div>
                    <div class="panel chart-card" style="grid-column:auto">
                        <h3>Rebate grows, then stops</h3>
                        <p>Past the cap, more investment doesn’t lower tax.</p>
                        <div class="chart-box short"><canvas x-ref="rebateChart" role="img" aria-label="Line chart of rebate and tax against investment"></canvas></div>
                    </div>
                </div>
            </section>

            {{-- Predictions --}}
            <section class="sec" aria-labelledby="h-insights">
                <div class="sec-head">
                    <div>
                        <h2 id="h-insights">What to expect</h2>
                        <p>Predictions worked out from your numbers.</p>
                    </div>
                </div>
                <ul class="insights">
                    <template x-for="p in report.predictions" :key="p.key">
                        <li class="insight">
                            <span class="insight-icon" :class="p.tone" x-html="icon(p.icon)"></span>
                            <div><h3 x-text="p.title"></h3><p x-text="p.text"></p></div>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- Scenarios --}}
            <section class="sec" aria-labelledby="h-raise">
                <div class="sec-head">
                    <div>
                        <h2 id="h-raise">If your salary changes</h2>
                        <p>Same investments, higher salary. Red rows have moved into a higher slab.</p>
                    </div>
                </div>
                <div class="panel chart-card" style="margin-bottom:22px">
                    <div class="chart-box"><canvas x-ref="scenarioChart" role="img" aria-label="Tax and monthly take-home at different raises"></canvas></div>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Raise</th><th class="r">Gross income</th><th class="r">Tax</th><th class="r">Extra tax</th><th>Top slab</th><th>You keep of the raise</th><th class="r">Monthly take-home</th></tr></thead>
                        <tbody>
                            <template x-for="row in report.scenarios" :key="row.raise">
                                <tr>
                                    <td class="raise-badge" x-text="row.raise === 0 ? 'Today' : '+' + Math.round(row.raise * 100) + '%'"></td>
                                    <td class="r" x-text="bdt(row.gross)"></td>
                                    <td class="r" x-text="bdt(row.tax)"></td>
                                    <td class="r" x-text="row.raise === 0 ? '–' : '+' + bdt(row.extra_tax)"></td>
                                    <td :class="{ 'slab-up': row.slab_changed }" x-text="pct(row.top_rate, 0) + (row.slab_changed ? ' (higher)' : '')"></td>
                                    <td><template x-if="row.keep_pct !== null"><span><span class="keep-bar"><i :style="'width:' + (row.keep_pct * 100) + '%'"></i></span><span class="num" x-text="pct(row.keep_pct, 0)"></span></span></template><span x-show="row.keep_pct === null" class="muted">–</span></td>
                                    <td class="r" x-text="bdt(row.monthly_take_home)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Charts --}}
            <section class="sec" aria-labelledby="h-explore">
                <div class="sec-head">
                    <div>
                        <h2 id="h-explore">Explore the numbers</h2>
                        <p>How your tax responds to income, investment, filing date, tax year and category.</p>
                    </div>
                </div>
                <div class="charts">
                    <div class="panel chart-card wide">
                        <h3>Average vs marginal rate</h3>
                        <p>The stepped line is the slab rate on your next taka. The curve is the share of all your income that goes to tax. The dot is you.</p>
                        <div class="chart-box tall"><canvas x-ref="rateChart" role="img" aria-label="Average and marginal tax rate by income"></canvas></div>
                    </div>

                    <div class="panel chart-card wide">
                        <h3>Tax at different incomes and investments</h3>
                        <p>Each cell is the tax for that salary (rows) and eligible investment (columns). Your row and investment are outlined.</p>
                        <div class="table-wrap">
                            <table class="heat">
                                <thead>
                                    <tr>
                                        <th scope="col">Income ↓ / Invest →</th>
                                        <template x-for="(amount, j) in heat.investments" :key="'c' + j"><th scope="col" x-text="KH.short(amount)"></th></template>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, i) in heat.values" :key="'r' + i">
                                        <tr :class="{ you: i === heat.user_row }">
                                            <th scope="row" x-text="KH.short(heat.incomes[i])"></th>
                                            <template x-for="(v, j) in row" :key="'v' + i + '-' + j">
                                                <td :class="{ 'you-col': i === heat.user_row && j === heat.user_col }" :style="heatStyle(v)" :title="'Income ' + bdt(heat.incomes[i]) + ', investment ' + bdt(heat.investments[j]) + ': tax ' + bdt(v)" x-text="KH.short(v).replace('৳', '')"></td>
                                            </template>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="heat-legend"><span>Lower tax</span><i></i><span>Higher tax</span></div>
                    </div>

                    <div class="panel chart-card two-thirds">
                        <h3>From gross tax to what you pay</h3>
                        <p>Rebate and early filing bring it down; minimum tax and late filing push it up.</p>
                        <div class="chart-box"><canvas x-ref="waterfallChart" role="img" aria-label="Waterfall from gross tax to tax payable"></canvas></div>
                    </div>
                    <div class="panel chart-card third">
                        <h3>Your monthly salary</h3>
                        <p>Take-home, savings and tax each month.</p>
                        <div class="chart-box"><canvas x-ref="paycheckChart" role="img" aria-label="Monthly salary split into take-home, investments and tax"></canvas></div>
                    </div>

                    <div class="panel chart-card">
                        <h3>Same income, other years</h3>
                        <p>* Roadmap years use the enacted future tax-free limits.</p>
                        <div class="chart-box"><canvas x-ref="futureChart" role="img" aria-label="Tax for the same income in each tax year"></canvas></div>
                    </div>
                    <div class="panel chart-card">
                        <h3>Same income, other categories</h3>
                        <p>Higher tax-free limits apply to some taxpayers.</p>
                        <div class="chart-box"><canvas x-ref="categoryChart" role="img" aria-label="Tax for the same income in each taxpayer category"></canvas></div>
                    </div>
                </div>
            </section>
        </section>

        {{-- ============ Mobile summary bar ============ --}}
        <div class="mobile-bar">
            <div><small>Your tax</small><strong x-text="bdt(s.liability)"></strong></div>
            <button type="button" class="btn btn-primary btn-sm" @click="openSave()" :disabled="calculation && !dirty" x-text="saveLabel"></button>
        </div>

        {{-- ============ Dialogs ============ --}}
        <dialog class="sheet" x-ref="saveDialog" aria-labelledby="save-title">
            <form class="sheet-body" @submit.prevent="save(false)">
                <h2 id="save-title" x-text="calculation ? 'Save changes' : 'Save this calculation'"></h2>
                <p>Saved calculations live under Saved, where you can reopen and compare them.</p>
                <div class="field">
                    <label class="label" for="calc-title">Name</label>
                    <input id="calc-title" x-ref="titleInput" class="input" maxlength="120" x-model="saveTitle" required>
                </div>
                <div class="field">
                    <label class="label" for="calc-notes">Notes <small>Optional</small></label>
                    <textarea id="calc-notes" class="input textarea" rows="3" maxlength="2000" x-model="saveNotes" placeholder="Offer letter, increment, what-if…"></textarea>
                </div>
                <div class="sheet-actions">
                    <button type="button" class="btn btn-quiet" @click="$refs.saveDialog.close()">Cancel</button>
                    <button type="button" class="btn btn-ghost" x-show="calculation" @click="save(true)" :disabled="saving">Save as new</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving" x-text="saving ? 'Saving…' : (calculation ? 'Save changes' : 'Save')"></button>
                </div>
            </form>
        </dialog>

        <dialog class="sheet" x-ref="authDialog" aria-labelledby="auth-title">
            <div class="sheet-body">
                <h2 id="auth-title">Sign in to save</h2>
                <p>Your numbers stay in this browser while you sign in or create an account, so nothing is lost.</p>
                <div class="sheet-actions">
                    <button type="button" class="btn btn-quiet" @click="$refs.authDialog.close()">Not now</button>
                    <a :href="routes.login" class="btn btn-ghost">Sign in</a>
                    <a :href="routes.register" class="btn btn-primary">Create account</a>
                </div>
            </div>
        </dialog>
    </div>
</x-layout>
