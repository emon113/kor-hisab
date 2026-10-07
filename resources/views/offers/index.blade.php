<x-layout :title="__('Compare job offers')">
    @push('scripts')
        <script src="{{ asset('js/offers.js') }}?v={{ @filemtime(public_path('js/offers.js')) }}"></script>
    @endpush

    <div x-data="offerCompare(@js($boot))">
        <div class="page-head">
            <div>
                <h1>{{ __('Compare job offers') }}</h1>
                <p>{{ __('Two offers with the same gross can leave different money in your pocket. Enter each offer the way it is written in the letter; tax is worked out with the same rules as the calculator.') }}</p>
            </div>
        </div>

        <div class="panel offer-settings">
            <div class="field">
                <label class="label" for="o-year">{{ __('Tax year') }}</label>
                <select id="o-year" class="select" x-model="form.year">
                    <template x-for="y in options.years" :key="y.key"><option :value="y.key" x-text="y.label" :selected="y.key === form.year"></option></template>
                </select>
            </div>
            <div class="field">
                <label class="label" for="o-cat">{{ __('You are') }}</label>
                <select id="o-cat" class="select" x-model="form.category">
                    <template x-for="c in options.categories" :key="c.key"><option :value="c.key" x-text="c.label" :selected="c.key === form.category"></option></template>
                </select>
            </div>
            <div class="field">
                <span class="label">{{ __('Investment rebate') }}</span>
                <div class="seg" role="group" aria-label="{{ __('Investment rebate assumption') }}">
                    <button type="button" :aria-pressed="form.investment_mode === 'none'" @click="form.investment_mode = 'none'">{{ __('None') }}</button>
                    <button type="button" :aria-pressed="form.investment_mode === 'same'" @click="form.investment_mode = 'same'">{{ __('Same amount') }}</button>
                    <button type="button" :aria-pressed="form.investment_mode === 'max'" @click="form.investment_mode = 'max'">{{ __('Maximum') }}</button>
                </div>
            </div>
            <div class="field" x-show="form.investment_mode === 'same'">
                <label class="label" for="o-inv">{{ __('Eligible investment') }}</label>
                <div class="money"><span>৳</span><input id="o-inv" class="input" inputmode="numeric" autocomplete="off" x-init="money($el, () => form.investment, (v) => form.investment = v)"></div>
            </div>
        </div>

        <div class="offer-grid" :style="'--cols:' + form.offers.length">
            <template x-for="(offer, i) in form.offers" :key="i">
                <section class="panel offer-card" :class="{ best: result.offers[i]?.best }" :aria-label="offer.name">
                    <div class="offer-head">
                        <input class="input offer-name" maxlength="60" x-model="offer.name" :aria-label="KH.t('Name of offer :n', { n: KH.num(i + 1) })">
                        <span class="pill tone-good" x-show="result.offers[i]?.best">{{ __('Best take-home') }}</span>
                        <button type="button" class="icon-btn" x-show="form.offers.length > 2" @click="removeOffer(i)" :aria-label="KH.t('Remove :name', { name: offer.name })"><x-icon name="trash" size="16" /></button>
                    </div>
                    <div class="field">
                        <label class="label">{{ __('Monthly salary') }} <small>{{ __('Gross, before tax') }}</small></label>
                        <div class="money"><span>৳</span><input class="input" inputmode="numeric" autocomplete="off" x-init="money($el, () => offer.monthly, (v) => offer.monthly = v)"></div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label class="label">{{ __('Basic, % of salary') }}</label>
                            <input type="number" class="input" min="1" max="100" step="1" x-model.number="offer.basic_pct">
                        </div>
                        <div class="field">
                            <label class="label">{{ __('Bonuses a year') }}</label>
                            <input type="number" class="input" min="0" max="12" step="0.5" x-model.number="offer.bonus_count">
                        </div>
                    </div>
                    <div class="field">
                        <span class="label">{{ __('Each bonus is one month of') }}</span>
                        <div class="seg" role="group">
                            <button type="button" :aria-pressed="offer.bonus_base === 'basic'" @click="offer.bonus_base = 'basic'">{{ __('Basic') }}</button>
                            <button type="button" :aria-pressed="offer.bonus_base === 'gross'" @click="offer.bonus_base = 'gross'">{{ __('Gross') }}</button>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">{{ __('Employer PF per month') }} <small>{{ __('Optional') }}</small></label>
                        <div class="money"><span>৳</span><input class="input" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el, () => offer.employer_pf, (v) => offer.employer_pf = v)"></div>
                    </div>
                    <div class="field">
                        <label class="label">{{ __('Other cash per year') }} <small>{{ __('Performance bonus, profit share') }}</small></label>
                        <div class="money"><span>৳</span><input class="input" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el, () => offer.other_annual, (v) => offer.other_annual = v)"></div>
                    </div>
                </section>
            </template>
            <button type="button" class="offer-add" x-show="form.offers.length < 3" @click="addOffer()"><x-icon name="plus" size="22" /> <span>{{ __('Add a third offer') }}</span></button>
        </div>

        <section class="results" aria-live="polite" style="margin-top:28px">
            <div class="loading-bar" :class="{ on: loading }" aria-hidden="true"></div>
            <div class="callout cost" x-show="error" x-text="error" role="alert" style="margin-bottom:16px"></div>
            <p class="offer-verdict" x-text="verdict"></p>

            <div class="table-wrap panel" style="padding:6px 16px;margin-bottom:22px">
                <table class="table">
                    <thead>
                        <tr>
                            <th></th>
                            <template x-for="o in result.offers" :key="'h' + o.index"><th class="r" x-text="o.name"></th></template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in rows" :key="row.key">
                            <tr :class="{ 'row-strong': row.strong }">
                                <td x-text="row.label"></td>
                                <template x-for="o in result.offers" :key="row.key + o.index">
                                    <td class="r">
                                        <span x-text="bdt(o[row.key])"></span>
                                        <small class="offer-diff" :class="diffClass(row, o)" x-show="diffText(row, o)" x-text="diffText(row, o)"></small>
                                    </td>
                                </template>
                            </tr>
                        </template>
                        <tr>
                            <td>{{ __('Effective tax rate') }}</td>
                            <template x-for="o in result.offers" :key="'e' + o.index"><td class="r" x-text="pct(o.effective_rate)"></td></template>
                        </tr>
                        <tr>
                            <td>{{ __('Top slab') }}</td>
                            <template x-for="o in result.offers" :key="'m' + o.index"><td class="r" x-text="pct(o.marginal_rate, 0)"></td></template>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="split">
                <div class="panel chart-card" style="grid-column:auto">
                    <h3>{{ __('Where each offer’s money goes') }}</h3>
                    <p>{{ __('Take-home, PF savings and tax for a year.') }}</p>
                    <div class="chart-box"><canvas x-ref="offerChart" role="img" aria-label="{{ __('Stacked bars of take-home, employer PF and tax for each offer') }}"></canvas></div>
                </div>
                <div class="panel" style="padding:20px">
                    <h3 style="margin-bottom:8px">{{ __('How this is worked out') }}</h3>
                    <ul class="checklist" style="font-size:var(--t-sm)">
                        <li>{{ __('Salary for tax is twelve months of salary, the festival bonuses, other cash and the employer’s PF contribution, as Schedule 1 of the return counts it.') }}</li>
                        <li>{{ __('One-third of that salary is tax-free up to ৳5,00,000, whatever the split between basic and allowances.') }}</li>
                        <li>{{ __('Take-home is cash after tax, before your own PF contribution. Total yearly value adds the employer’s PF.') }}</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-layout>
