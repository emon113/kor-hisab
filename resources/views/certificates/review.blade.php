@use('App\Models\SalaryCertificate')
<x-layout :title="__('Check the figures')">
    @push('scripts')
        <script src="{{ asset('js/certificate-review.js') }}?v={{ @filemtime(public_path('js/certificate-review.js')) }}"></script>
    @endpush

    <div x-data="certificateReview(@js($boot))">
        <div class="page-head reveal">
            <div>
                <h1>{{ __('Check the figures') }}</h1>
                <p>{{ __('Compare each figure with your certificate and fix anything that was misread. The report is made from what you confirm here.') }}</p>
            </div>
            <div class="head-actions">
                <a href="{{ route('certificates.index') }}" class="btn btn-quiet">{{ __('All certificates') }}</a>
                @if ($certificate->status === SalaryCertificate::CONFIRMED)
                    <a href="{{ route('certificates.report', $certificate) }}" class="btn btn-ghost">{{ __('Open report') }}</a>
                @endif
            </div>
        </div>

        @if ($certificate->status === SalaryCertificate::READ && ! $certificate->data)
            <div class="callout reveal" style="--i:1;margin-bottom:20px">
                <strong>{{ __('Read :count figures from your certificate.', ['count' => \App\Support\Money::digits((string) count($boot['found']))]) }}</strong>
                {{ __('They are marked with a green dot; hover one to see the line it came from.') }}
            </div>
        @elseif ($certificate->ocr_error && ! $certificate->data)
            <div class="callout warn reveal" style="--i:1;margin-bottom:20px;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
                <span>{{ $certificate->ocr_error }}</span>
                <form method="POST" action="{{ route('certificates.reread', $certificate) }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">{{ __('Try reading again') }}</button></form>
            </div>
        @endif

        <div class="review">
            {{-- ============ The document ============ --}}
            <aside class="doc-viewer panel reveal" style="--i:2" aria-label="{{ __('Your certificate') }}">
                <header>
                    <span class="truncate">{{ $certificate->original_name }}</span>
                    <a href="{{ route('certificates.file', $certificate) }}" target="_blank" rel="noopener" class="btn btn-quiet btn-sm"><x-icon name="open" size="14" /> {{ __('Open') }}</a>
                </header>
                @if ($certificate->isPdf())
                    <iframe src="{{ route('certificates.file', $certificate) }}#view=FitH" title="{{ __('Your certificate') }}"></iframe>
                @else
                    <a href="{{ route('certificates.file', $certificate) }}" target="_blank" rel="noopener" class="doc-image">
                        <img src="{{ route('certificates.file', $certificate) }}" alt="{{ __('Your certificate') }}">
                    </a>
                @endif
                @if ($certificate->ocr_text)
                    <details class="ocr-text">
                        <summary>{{ __('Text read from the file') }}</summary>
                        <pre>{{ $certificate->ocr_text }}</pre>
                    </details>
                @endif
            </aside>

            {{-- ============ The figures ============ --}}
            <form class="review-form" @submit.prevent="save()">
                <section class="panel review-card reveal" style="--i:3">
                    <h2>{{ __('About this certificate') }}</h2>
                    <div class="field-row">
                        <div class="field">
                            <label class="label" for="c-year">{{ __('Tax year') }}</label>
                            <select id="c-year" class="select" x-model="data.year">
                                <template x-for="y in options.years" :key="y.key"><option :value="y.key" x-text="y.label + ' · ' + y.income_year" :selected="y.key === data.year"></option></template>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="c-cat">{{ __('You are') }}</label>
                            <select id="c-cat" class="select" x-model="data.category">
                                <template x-for="c in options.categories" :key="c.key"><option :value="c.key" x-text="c.label" :selected="c.key === data.category"></option></template>
                            </select>
                        </div>
                    </div>
                    <div class="field-row" style="margin-top:14px">
                        <div class="field"><label class="label" for="c-employer">{{ __('Employer') }}</label><input id="c-employer" class="input" maxlength="120" x-model="data.employer"></div>
                        <div class="field"><label class="label" for="c-employee">{{ __('Employee') }}</label><input id="c-employee" class="input" maxlength="120" x-model="data.employee"></div>
                    </div>
                    <div class="field-row" style="margin-top:14px">
                        <div class="field">
                            <label class="label" for="c-tin">{{ __('TIN') }} <small>{{ __('Optional') }}</small></label>
                            <input id="c-tin" class="input" inputmode="numeric" maxlength="20" x-model="data.tin">
                            <p class="error" x-show="errors.tin" x-text="errors.tin?.[0]"></p>
                        </div>
                        <div class="field">
                            <span class="label">{{ __('First return') }}</span>
                            <label class="switch"><input type="checkbox" x-model="data.new_taxpayer"><span class="switch-track" aria-hidden="true"></span><span>{{ __('This is my first tax return') }}</span></label>
                        </div>
                    </div>
                </section>

                <section class="panel review-card reveal" style="--i:4">
                    <h2>{{ __('Salary') }}</h2>
                    <p class="hint">{{ __('Yearly amounts, as on the certificate.') }}</p>
                    <template x-for="c in options.components" :key="c.key">
                        <div class="review-row">
                            <label :for="'cc-' + c.key"><span class="found-dot" x-show="isFound(c.key)" :title="source(c.key)"></span><span x-text="c.label"></span></label>
                            <div class="money"><span>৳</span><input class="input" :id="'cc-' + c.key" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el, 'components', c.key)"></div>
                        </div>
                    </template>
                </section>

                <section class="panel review-card reveal" style="--i:5">
                    <h2>{{ __('Perks and other taxable pay') }}</h2>
                    <p class="hint">{{ __('Only what appears on the certificate. Leave the rest at zero.') }}</p>
                    <template x-for="p in options.perks" :key="p.key">
                        <div class="review-row">
                            <label :for="'cp-' + p.key"><span class="found-dot" x-show="isFound(p.key)" :title="source(p.key)"></span><span x-text="p.label"></span><small x-show="p.hint" x-text="p.hint"></small></label>
                            <div class="money"><span>৳</span><input class="input" :id="'cp-' + p.key" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el, 'perks', p.key)"></div>
                        </div>
                    </template>
                </section>

                <section class="panel review-card reveal" style="--i:6">
                    <h2>{{ __('Tax deducted at source') }}</h2>
                    <div class="review-row">
                        <label for="c-tds"><span class="found-dot" x-show="isFound('tds')" :title="source('tds')"></span><span>{{ __('TDS deducted by the employer this year') }}</span></label>
                        <div class="money"><span>৳</span><input class="input" id="c-tds" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el, null, 'tds')"></div>
                    </div>
                </section>

                <section class="panel review-card reveal" style="--i:7">
                    <h2>{{ __('Investments this year') }}</h2>
                    <p class="hint">{{ __('Add what you invested; it lowers your tax through the rebate. Provident fund contributions from the certificate are already counted.') }}</p>
                    <template x-for="inst in options.instruments" :key="inst.key">
                        <div class="review-row">
                            <label :for="'ci-' + inst.key"><span class="found-dot" x-show="inst.key === 'provident_fund' && (isFound('employer_pf') || isFound('employee_pf'))"></span><span x-text="inst.label"></span><small x-text="inst.hint"></small></label>
                            <div class="money"><span>৳</span><input class="input" :id="'ci-' + inst.key" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el, 'investments', inst.key)"></div>
                        </div>
                    </template>
                </section>

                <section class="panel review-card reveal" style="--i:8">
                    <h2>{{ __('Notes') }} <small class="hint">{{ __('Optional') }}</small></h2>
                    <textarea class="input textarea" rows="3" maxlength="2000" x-model="data.notes" placeholder="{{ __('Anything to remember about this year') }}"></textarea>
                </section>

                {{-- ============ Live totals ============ --}}
                <div class="review-bar">
                    <dl>
                        <div><dt>{{ __('Salary for tax') }}</dt><dd x-text="bdt(gross)"></dd></div>
                        <div x-show="certificateTotal">
                            <dt>{{ __('Certificate total') }}</dt>
                            <dd :class="totalMatches ? 'tone-good' : 'tone-watch'"><span x-text="bdt(certificateTotal)"></span> <span x-text="totalMatches ? '✓' : '≠'"></span></dd>
                        </div>
                        <div><dt>{{ __('Estimated tax') }}</dt><dd :class="{ 'is-loading': loading }" x-text="estimate ? bdt(estimate.liability) : '…'"></dd></div>
                        <div x-show="estimate">
                            <dt x-text="estimate && estimate.payable >= 0 ? KH.t('Left to pay') : KH.t('Refund due')"></dt>
                            <dd :class="estimate && estimate.payable > 0 ? 'tone-cost' : 'tone-good'" x-text="estimate ? bdt(Math.abs(estimate.payable)) : ''"></dd>
                        </div>
                    </dl>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-text="saving ? KH.t('Building your report…') : KH.t('Confirm and see the report')"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layout>
