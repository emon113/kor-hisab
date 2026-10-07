<x-layout :title="__('Monthly TDS planner')">
    @push('scripts')
        <script src="{{ asset('js/tds.js') }}?v={{ @filemtime(public_path('js/tds.js')) }}"></script>
    @endpush

    <div x-data="tdsPlanner(@js($boot))">
        <div class="page-head">
            <div>
                <h1>{{ __('Monthly TDS planner') }}</h1>
                <p>{{ __('Tick the months already paid and enter the TDS your employer actually deducted. The rest of the year’s tax is spread over the months left, so June ends with nothing to pay.') }}</p>
            </div>
        </div>

        <div class="workspace">
            <aside class="slip slip-free panel" aria-label="{{ __('Plan summary') }}">
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

                <div class="field">
                    <label class="label" for="tds-inv">{{ __('Eligible investment this year') }}</label>
                    <div class="money"><span>৳</span><input id="tds-inv" class="input" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el, () => form.investment, (v) => form.investment = v)"></div>
                    <p class="hint">{{ __('DPS, Sanchaypatra and other investments you will make. They lower the tax through the rebate.') }}</p>
                </div>

                <div class="slip-group">
                    <h2>{{ __('Fill every month') }}</h2>
                    <p>{{ __('Same salary each month? Enter it once.') }}</p>
                    <div style="display:flex;gap:8px">
                        <div class="money" style="flex:1"><span>৳</span><input class="input" inputmode="numeric" autocomplete="off" aria-label="{{ __('Monthly salary') }}" x-init="money($el, () => quickSalary, (v) => quickSalary = v)"></div>
                        <button type="button" class="btn btn-ghost" @click="fillSalary()">{{ __('Apply') }}</button>
                    </div>
                </div>

                <dl class="wealth-figures" style="margin-top:18px">
                    <div><dt>{{ __('Salary for the year') }}</dt><dd x-text="bdt(plan.gross)"></dd></div>
                    <div><dt>{{ __('Tax for the year') }}</dt><dd x-text="bdt(plan.liability)"></dd></div>
                    <div><dt>{{ __('Deducted so far') }}</dt><dd x-text="bdt(plan.deducted)"></dd></div>
                    <div class="strong"><dt x-text="plan.remaining >= 0 ? KH.t('Still to deduct') : KH.t('Refund due')"></dt><dd x-text="bdt(Math.abs(plan.remaining))"></dd></div>
                </dl>

                <div class="insight" style="margin:10px 0 0">
                    <span class="insight-icon" :class="statusTone" x-html="KH.icon(plan.status === 'on_track' ? 'check' : (plan.status === 'refund' ? 'receipt' : 'alert'))"></span>
                    <div><h3 x-text="statusTitle"></h3><p x-text="statusText"></p></div>
                </div>
            </aside>

            <section class="results" aria-live="polite">
                <div class="loading-bar" :class="{ on: loading }" aria-hidden="true"></div>
                <div class="callout cost" x-show="error" x-text="error" role="alert" style="margin-bottom:16px"></div>

                <div class="verdict">
                    <p class="verdict-lede" x-text="plan.open_months > 0 ? KH.t('From now on, ask for this much TDS each month') : KH.t('No months left to deduct')"></p>
                    <div class="verdict-amount" :class="{ zero: plan.next_monthly <= 0 }" x-text="bdt(plan.next_monthly)"></div>
                    <p class="verdict-sub" x-show="plan.open_months > 0" x-text="KH.t('For the :count months left that pay a salary.', { count: KH.num(plan.open_months) })"></p>
                </div>

                <section class="sec" aria-labelledby="h-months">
                    <div class="sec-head">
                        <div><h2 id="h-months">{{ __('Month by month') }}</h2><p>{{ __('Bonus months raise the year’s tax, so the plan adjusts every month after them.') }}</p></div>
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
