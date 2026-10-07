<x-layout :title="__('Do I need to file a return?')">
    @push('scripts')
        <script src="{{ asset('js/filing.js') }}?v={{ @filemtime(public_path('js/filing.js')) }}"></script>
    @endpush

    <div x-data="filingCheck(@js($boot))">
        <div class="page-head">
            <div>
                <h1>{{ __('Do I need to file a return?') }}</h1>
                <p>{{ __('Answer a few questions. The rules come from section 166 of the Income Tax Act 2023; one “yes” is enough to make filing compulsory.') }}</p>
            </div>
        </div>

        <div class="workspace">
            <section class="slip slip-free panel" aria-label="{{ __('Questions') }}">
                <div class="slip-head"><h2 style="font-size:var(--t-lg)">{{ __('Your situation') }}</h2><span class="hint" x-text="KH.t(':done of :total answered', { done: KH.num(answered), total: KH.num(total) })"></span></div>

                <div class="field">
                    <label class="label" for="fc-cat">{{ __('You are') }}</label>
                    <select id="fc-cat" class="select" x-model="category">
                        <template x-for="c in categories" :key="c.key"><option :value="c.key" x-text="c.label"></option></template>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="fc-income">{{ __('Total income this year') }} <small x-text="@js($boot['yearLabel'])"></small></label>
                    <div class="money"><span>৳</span><input id="fc-income" class="input" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el)"></div>
                    <p class="hint" x-text="KH.t('Salary, bonuses and other income. For you, tax starts above :limit.', { limit: bdt(cat.starts_at) })"></p>
                </div>

                <ol class="questions">
                    <template x-for="o in obligations" :key="o.id">
                        <li>
                            <p x-text="o.question"></p>
                            <small class="hint" x-show="o.hint" x-text="o.hint"></small>
                            <div class="seg" role="group" :aria-label="o.question">
                                <button type="button" :aria-pressed="answers[o.id] === true" @click="answer(o.id, true)">{{ __('Yes') }}</button>
                                <button type="button" :aria-pressed="answers[o.id] === false" @click="answer(o.id, false)">{{ __('No') }}</button>
                            </div>
                        </li>
                    </template>
                </ol>
            </section>

            <section class="results" aria-live="polite">
                <div class="verdict filing-verdict" :class="verdict">
                    <p class="verdict-lede">{{ __('Based on your answers') }}</p>
                    <div class="verdict-amount" :class="{ zero: verdict !== 'must' }"
                         x-text="verdict === 'must' ? KH.t('Yes, you must file') : (verdict === 'clear' ? KH.t('Not required') : KH.t('Keep going'))"></div>
                    <p class="verdict-sub" x-show="verdict === 'open'">{{ __('Answer the remaining questions. So far nothing makes filing compulsory.') }}</p>
                    <p class="verdict-sub" x-show="verdict === 'clear'">{{ __('None of the section 166 conditions apply to you, so the law does not require a return this year. You are also exempt from giving proof of return submission.') }}</p>
                </div>

                <template x-if="reasons.length">
                    <ul class="insights" style="margin-bottom:24px">
                        <template x-for="(r, i) in reasons" :key="i">
                            <li class="insight"><span class="insight-icon cost" x-html="KH.icon('alert')"></span><div><p x-text="r"></p></div></li>
                        </template>
                    </ul>
                </template>

                <div class="split split-even">
                    <section class="panel" style="padding:20px">
                        <h3 style="margin-bottom:6px" x-text="verdict === 'must' ? KH.t('Your return also unlocks these') : KH.t('Filing anyway helps with these')"></h3>
                        <p class="hint" style="margin-bottom:12px">{{ __('Common services that ask for proof of return submission (PSR).') }}</p>
                        <ul class="checklist">
                            <template x-for="(s, i) in psr" :key="'p' + i"><li style="font-size:var(--t-sm)" x-text="s.service"></li></template>
                        </ul>
                    </section>
                    <section class="panel" style="padding:20px">
                        <h3 style="margin-bottom:6px">{{ __('A TIN is enough for these') }}</h3>
                        <p class="hint" style="margin-bottom:12px">{{ __('Since the Finance Ordinance 2025, these accept your TIN instead of proof of return.') }}</p>
                        <ul class="checklist">
                            <template x-for="(s, i) in tinOnly" :key="'t' + i"><li style="font-size:var(--t-sm)" x-text="s.service"></li></template>
                        </ul>
                    </section>
                </div>

                <section class="sec" style="margin-top:28px" x-show="verdict !== 'clear'">
                    <div class="sec-head"><h2>{{ __('What to do next') }}</h2></div>
                    <ol class="steps">
                        <template x-for="(s, i) in steps" :key="'s' + i"><li><div><b x-text="s.title"></b><p x-text="s.text"></p></div></li></template>
                    </ol>
                    <div class="result-links" style="margin-top:18px">
                        <a :href="routes.calculator" class="btn btn-primary btn-sm">{{ __('Open the calculator') }}</a>
                        <a :href="routes.guide" class="btn btn-ghost btn-sm">{{ __('Return form guide') }}</a>
                    </div>
                </section>

                <section class="sources" style="margin-top:28px">
                    <p>{{ __('Checked against these sources on :date. Section 264 lists more services and changes with each budget: confirm with NBR or a tax practitioner.', ['date' => $checkedAt]) }}</p>
                    <ul>
                        @foreach ($sources as $source)
                            <li><a href="{{ $source['url'] }}" rel="noopener" target="_blank">{{ __($source['label']) }}</a></li>
                        @endforeach
                    </ul>
                </section>
            </section>
        </div>
    </div>
</x-layout>
