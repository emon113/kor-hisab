<x-layout :title="__('Monthly TDS planner')">
    @push('scripts')
        <script src="{{ asset('js/tds.js') }}?v={{ @filemtime(public_path('js/tds.js')) }}"></script>
    @endpush

    <div x-data="tdsPlanner(@js($boot))">
        <div class="page-head reveal">
            <div>
                <h1>{{ __('Monthly TDS planner') }}</h1>
                <p>{{ __('Your salary, bonuses and perks for the year, the investments you plan, and the TDS already deducted. The planner finds your lowest legal tax and how much to deduct each month.') }}</p>
            </div>
            <div class="head-actions">
                <div class="loading-dot" :class="{ on: loading }" aria-hidden="true"></div>
            </div>
        </div>

        {{-- ============ Three outcomes ============ --}}
        <section class="outcomes reveal" style="--i:1" aria-label="{{ __('Your tax this year') }}">
            <div class="outcome">
                <span>{{ __('Tax with your plan') }}</span>
                <strong x-effect="KH.tween($el, plan.outcomes.current)"></strong>
                <small x-text="KH.t('Without any investment: :amount', { amount: bdt(plan.outcomes.none) })"></small>
            </div>
            <div class="outcome best">
                <span>{{ __('Lowest legal tax') }}</span>
                <strong x-effect="KH.tween($el, plan.outcomes.best)"></strong>
                <small x-show="plan.outcomes.saving > 0" x-text="KH.t(':amount less, with the full investment rebate', { amount: bdt(plan.outcomes.saving) })"></small>
                <small x-show="plan.outcomes.saving <= 0">{{ __('You already get the full rebate') }}</small>
            </div>
            <div class="outcome">
                <span>{{ __('Monthly TDS from now') }}</span>
                <strong x-text="plan.open_months ? (plan.range.low === plan.range.high ? bdt(plan.range.low) : bdt(plan.range.low) + ' – ' + bdt(plan.range.high)) : '–'"></strong>
                <small>{{ __('From full rebate to no investment') }}</small>
            </div>
        </section>

        {{-- ============ Strategy ============ --}}
        <section class="strategy reveal" style="--i:2" aria-labelledby="h-strategy">
            <h2 id="h-strategy" class="sr-only">{{ __('Deduction strategy') }}</h2>
            <div class="choice-grid strategy-grid" role="radiogroup">
                <label class="choice" :class="{ on: form.strategy === 'current' }">
                    <input type="radio" class="sr-only" value="current" x-model="form.strategy">
                    <b>{{ __('Cover the tax as planned') }}</b>
                    <small>{{ __('Deduct enough for the tax with the investments entered.') }}</small>
                </label>
                <label class="choice" :class="{ on: form.strategy === 'full_rebate' }">
                    <input type="radio" class="sr-only" value="full_rebate" x-model="form.strategy">
                    <b>{{ __('I will invest for the full rebate') }}</b>
                    <small>{{ __('Deduct for the lowest legal tax. Declare the investments to HR.') }}</small>
                </label>
                <label class="choice" :class="{ on: form.strategy === 'cap' }">
                    <input type="radio" class="sr-only" value="cap" x-model="form.strategy">
                    <b>{{ __('Keep monthly TDS low') }}</b>
                    <small>{{ __('Set a monthly limit and pay the rest with the return.') }}</small>
                </label>
            </div>
            <div class="cap-field" x-show="form.strategy === 'cap'" x-transition.opacity>
                <label class="label" for="tds-cap">{{ __('Deduct at most') }}</label>
                <div class="money"><span>৳</span><input id="tds-cap" class="input" inputmode="numeric" autocomplete="off" x-init="money($el, () => form.monthly_cap, (v) => form.monthly_cap = v)"></div>
                <span class="hint">{{ __('per month') }}</span>
            </div>
        </section>

        <div class="workspace">
            {{-- ============ Inputs ============ --}}
            <aside class="slip slip-free panel" aria-label="{{ __('Plan inputs') }}">
                <div class="field-row">
                    <div class="field">
                        <label class="label" for="tds-year">{{ __('Tax year') }}</label>
                        <select id="tds-year" class="select" x-model="form.year">
                            <template x-for="y in options.years" :key="y.key"><option :value="y.key" x-text="y.label" :selected="y.key === form.year"></option></template>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="tds-cat">{{ __('You are') }}</label>
                        <select id="tds-cat" class="select" x-model="form.category">
                            <template x-for="c in options.categories" :key="c.key"><option :value="c.key" x-text="c.label" :selected="c.key === form.category"></option></template>
                        </select>
                    </div>
                </div>
                <p class="hint" style="margin-top:6px" x-text="KH.t('Income year :period.', { period: yearInfo.income_year || '' })"></p>

                <div class="slip-group">
                    <h2>{{ __('Fill every month') }}</h2>
                    <p>{{ __('Same salary each month? Enter it once.') }}</p>
                    <div style="display:flex;gap:8px">
                        <div class="money" style="flex:1"><span>৳</span><input class="input" inputmode="numeric" autocomplete="off" aria-label="{{ __('Monthly salary') }}" x-init="money($el, () => quickSalary, (v) => quickSalary = v)"></div>
                        <button type="button" class="btn btn-ghost" @click="fillSalary()">{{ __('Apply') }}</button>
                    </div>
                </div>

                <div class="slip-group">
                    <h2>{{ __('Investments this year') }}</h2>
                    <p x-text="KH.t(':eligible of :needed needed for the full rebate', { eligible: bdt(plan.investments.eligible), needed: bdt(plan.investments.needed) })"></p>
                    <div class="meter" role="progressbar" :aria-valuenow="Math.round(plan.investments.progress * 100)" aria-valuemin="0" aria-valuemax="100" style="margin-bottom:6px"><i :style="'width:' + (plan.investments.progress * 100) + '%'"></i></div>
                    <template x-for="inst in options.instruments.filter((i) => i.common)" :key="inst.key">
                        <div class="inv-row">
                            <label class="label" :for="'ti-' + inst.key"><span x-text="inst.label"></span><small x-show="inst.cap" x-text="KH.t('Up to :cap', { cap: bdt(inst.cap) })"></small></label>
                            <div class="money"><span>৳</span><input class="input" :id="'ti-' + inst.key" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el, () => form.investments[inst.key], (v) => form.investments[inst.key] = v)"></div>
                        </div>
                    </template>
                    <details class="more-inv" :open="options.instruments.some((i) => !i.common && Number(form.investments[i.key]))">
                        <summary>{{ __('Provident fund, insurance, pension, zakat') }}</summary>
                        <template x-for="inst in options.instruments.filter((i) => !i.common)" :key="inst.key">
                            <div class="inv-row">
                                <label class="label" :for="'ti-' + inst.key"><span x-text="inst.label"></span></label>
                                <div class="money"><span>৳</span><input class="input" :id="'ti-' + inst.key" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el, () => form.investments[inst.key], (v) => form.investments[inst.key] = v)"></div>
                                <p class="hint" x-text="inst.hint"></p>
                            </div>
                        </template>
                    </details>
                </div>

                <div class="slip-group">
                    <h2>{{ __('Perks and other taxable pay') }}</h2>
                    <p x-text="KH.t('For the whole year, as your salary certificate shows them. Now :amount.', { amount: bdt(plan.perk_total) })"></p>
                    <template x-for="perk in options.perks" :key="perk.key">
                        <div class="inv-row">
                            <label class="label" :for="'tp-' + perk.key"><span x-text="perk.label"></span></label>
                            <div class="money"><span>৳</span><input class="input" :id="'tp-' + perk.key" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el, () => form.perks[perk.key], (v) => form.perks[perk.key] = v)"></div>
                            <p class="hint" x-show="perk.hint" x-text="perk.hint"></p>
                        </div>
                    </template>
                </div>
            </aside>

            {{-- ============ Results ============ --}}
            <section class="results" aria-live="polite">
                <div class="loading-bar" :class="{ on: loading }" aria-hidden="true"></div>
                <div class="callout cost" x-show="error" x-text="error" role="alert" style="margin-bottom:16px"></div>

                <div class="verdict reveal" style="--i:3">
                    <p class="verdict-lede" x-text="plan.open_months > 0 ? KH.t('From now on, ask for this much TDS each month') : KH.t('No months left to deduct')"></p>
                    <div class="verdict-amount" :class="{ zero: plan.next_monthly <= 0 }" x-effect="KH.tween($el, plan.next_monthly)"></div>
                    <p class="verdict-sub" x-show="plan.open_months > 0" x-html="verdictSub"></p>
                </div>

                <div class="insight status-card" :class="'tone-' + statusTone">
                    <span class="insight-icon" :class="statusTone" x-html="KH.icon(plan.status === 'on_track' ? 'check' : (plan.status === 'refund' ? 'receipt' : 'alert'))"></span>
                    <div><h3 x-text="statusTitle"></h3><p x-text="statusText"></p></div>
                </div>

                <section class="sec" aria-labelledby="h-advice">
                    <div class="sec-head"><div><h2 id="h-advice">{{ __('What to do') }}</h2><p>{{ __('Worked out from your numbers. Every suggestion is within the law.') }}</p></div></div>
                    <ul class="insights">
                        <template x-for="(p, i) in plan.insights" :key="p.key">
                            <li class="insight reveal" :style="'--i:' + i">
                                <span class="insight-icon" :class="p.tone" x-html="KH.icon(p.icon)"></span>
                                <div>
                                    <h3 x-text="p.title"></h3>
                                    <p x-text="p.text"></p>
                                    <button type="button" class="btn btn-ghost btn-sm insight-action" x-show="p.action" @click="act(p.action)" x-text="p.action?.label"></button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </section>

                <section class="sec" aria-labelledby="h-months">
                    <div class="sec-head">
                        <div><h2 id="h-months">{{ __('Month by month') }}</h2><p>{{ __('Tick the months already paid and enter the TDS actually deducted. Bonus months raise the year’s tax, so the plan adjusts every month after them.') }}</p></div>
                    </div>
                    <div class="table-wrap panel" style="padding:6px 14px">
                        <table class="table tds-table">
                            <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Paid') }}</th><th class="r">{{ __('Salary') }}</th><th class="r">{{ __('Bonus') }}</th><th class="r">{{ __('TDS') }}</th><th class="r">{{ __('Total so far') }}</th></tr></thead>
                            <tbody>
                                <template x-for="(row, i) in plan.months" :key="row.key">
                                    <tr :class="{ 'is-done': form.months[row.key].done }">
                                        <td x-text="row.label"></td>
                                        <td><input type="checkbox" class="tds-check" :aria-label="KH.t(':month already paid', { month: row.label })" x-model="form.months[row.key].done"></td>
                                        <td class="r"><div class="money money-sm"><span>৳</span><input class="input" inputmode="numeric" autocomplete="off" :aria-label="KH.t(':month salary', { month: row.label })" x-init="money($el, () => form.months[row.key].salary, (v) => form.months[row.key].salary = v)"></div></td>
                                        <td class="r"><div class="money money-sm"><span>৳</span><input class="input" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" :aria-label="KH.t(':month bonus', { month: row.label })" x-init="money($el, () => form.months[row.key].bonus, (v) => form.months[row.key].bonus = v)"></div></td>
                                        <td class="r">
                                            <div class="money money-sm" x-show="form.months[row.key].done"><span>৳</span><input class="input" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" :aria-label="KH.t(':month TDS deducted', { month: row.label })" x-init="money($el, () => form.months[row.key].tds, (v) => form.months[row.key].tds = v)"></div>
                                            <strong class="tone-good" x-show="!form.months[row.key].done" x-text="bdt(row.suggested)"></strong>
                                        </td>
                                        <td class="r muted" x-text="bdt(row.cumulative_planned)"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2">{{ __('Total') }}</td>
                                    <td class="r" x-text="bdt(plan.salary_total)"></td>
                                    <td class="r" x-text="bdt(plan.bonus_total)"></td>
                                    <td class="r" x-text="bdt(plan.deducted + plan.months.reduce((a, r) => a + (form.months[r.key].done ? 0 : r.suggested), 0))"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="hint" style="margin-top:8px">{{ __('Green amounts are the suggested deductions for months not yet paid.') }}</p>
                </section>

                <section class="sec" aria-labelledby="h-pace">
                    <div class="split">
                        <div class="panel chart-card" style="grid-column:auto">
                            <h3 id="h-pace">{{ __('Deducted vs due') }}</h3>
                            <p>{{ __('The dashed line is the tax due by each month, in step with what you have earned.') }}</p>
                            <div class="chart-box"><canvas x-ref="paceChart" role="img" aria-label="{{ __('Cumulative TDS against tax due by month') }}"></canvas></div>
                        </div>
                        <div class="panel" style="padding:20px">
                            <h3 style="margin-bottom:8px">{{ __('Message for HR') }}</h3>
                            <div class="copy-block" x-text="plan.hr_text"></div>
                            <button type="button" class="btn btn-ghost btn-sm" style="margin-top:10px" @click="copy()"><x-icon name="copy" size="16" /> {{ __('Copy message') }}</button>
                        </div>
                    </div>
                </section>
            </section>
        </div>
    </div>
</x-layout>
