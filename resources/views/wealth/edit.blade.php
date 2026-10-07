<x-layout :title="__('Assets and liabilities').' · '.$boot['yearLabel']">
    @push('scripts')
        <script src="{{ asset('js/wealth.js') }}?v={{ @filemtime(public_path('js/wealth.js')) }}"></script>
    @endpush

    <div class="workspace" x-data="wealth(@js($boot))">
        <aside class="slip panel" aria-label="{{ __('Statement summary') }}">
            <div class="slip-head">
                <div>
                    <h1>{{ $boot['yearLabel'] }}</h1>
                    <p class="hint">{{ __('Position as on :date', ['date' => $boot['asOn']]) }}</p>
                </div>
                <a href="{{ route('wealth.index') }}" class="btn btn-quiet btn-sm">{{ __('All years') }}</a>
            </div>

            @if (! empty($boot['prefill']['carried']))
                <p class="callout" style="margin-bottom:10px">{{ __('Assets and liabilities are carried over from :year. Update anything that changed.', ['year' => $boot['prefill']['carried']]) }}</p>
            @endif
            @if (! empty($boot['prefill']['income_from']))
                <p class="callout" style="margin-bottom:10px">{{ __('Income and tax paid come from your saved calculation “:title”.', ['title' => $boot['prefill']['income_from']]) }}</p>
            @endif

            <dl class="wealth-figures">
                <div><dt>{{ __('Sources of fund') }}</dt><dd x-text="bdt(result.receipts)"></dd></div>
                <div><dt>{{ __('Expenses and losses') }}</dt><dd x-text="'−' + bdt(result.expenses)"></dd></div>
                <div><dt>{{ __('Total assets') }}</dt><dd x-text="bdt(total('assets'))"></dd></div>
                <div><dt>{{ __('Liabilities') }}</dt><dd x-text="'−' + bdt(total('liabilities'))"></dd></div>
                <div class="strong"><dt>{{ __('Net wealth this year') }}</dt><dd x-text="bdt(result.net)"></dd></div>
                <template x-if="result.previous !== null">
                    <div><dt>{{ __('Net wealth last year') }}</dt><dd x-text="bdt(result.previous)"></dd></div>
                </template>
                <template x-if="result.expected !== null">
                    <div><dt>{{ __('Expected from income') }}</dt><dd x-text="bdt(result.expected)"></dd></div>
                </template>
            </dl>

            <div class="insight" style="margin:14px 0">
                <span class="insight-icon" :class="statusTone" x-html="KH.icon(result.status === 'balanced' ? 'check' : 'alert')"></span>
                <div><h3 x-text="result.gap === null ? KH.t('No starting point yet') : (result.status === 'balanced' ? KH.t('It adds up') : KH.t('Gap of :amount', { amount: bdt(Math.abs(result.gap)) }))"></h3><p x-text="statusText"></p></div>
            </div>

            <template x-if="!hasPreviousStatement">
                <div class="field">
                    <label class="label" for="opening">{{ __('Net wealth at the end of last year') }}</label>
                    <div class="money"><span>৳</span><input id="opening" class="input" inputmode="numeric" autocomplete="off" x-init="openingInput($el)"></div>
                    <p class="hint">{{ __('Line 5 of last year’s IT-10B. Leave it blank on your first statement.') }}</p>
                </div>
            </template>

            <div class="field">
                <label class="label" for="w-notes">{{ __('Notes') }} <small>{{ __('Optional') }}</small></label>
                <textarea id="w-notes" class="input textarea" rows="2" maxlength="2000" x-model="notes" placeholder="{{ __('Borrower names, vehicle number, gold in bhori…') }}"></textarea>
            </div>

            <div class="slip-actions">
                <button type="button" class="btn btn-primary btn-block" @click="save()" :disabled="saving || (exists && !dirty)">
                    <x-icon name="save" size="18" /> <span x-text="saving ? KH.t('Saving…') : (exists && !dirty ? KH.t('Saved') : KH.t('Save statement'))"></span>
                </button>
                <a :href="routes.print" class="btn btn-ghost btn-block" x-show="exists"><x-icon name="print" size="18" /> {{ __('Print view') }}</a>
            </div>
        </aside>

        <section class="results" aria-label="{{ __('Statement lines') }}">
            <p class="hint" style="margin-bottom:18px">{{ __('Line numbers follow NBR’s IT-10B (2023) and IT-10BB (2023). Enter your own, your spouse’s (if not a taxpayer) and minor children’s figures, as the form asks.') }}</p>
            <template x-for="group in groups" :key="group.key">
                <section class="sec panel guide-table" style="padding:6px 16px 10px">
                    <div class="sec-head" style="margin:14px 0 6px"><h2 x-text="group.title"></h2></div>
                    <table class="table">
                        <tbody>
                            <template x-for="line in group.lines" :key="line.key">
                                <tr>
                                    <td class="guide-serial" x-text="KH.num(line.serial)"></td>
                                    <td><label :for="'w-' + group.key + '-' + line.key" x-text="line.label"></label><small x-show="line.hint" x-text="line.hint"></small></td>
                                    <td class="r" style="width:210px">
                                        <div class="money"><span>৳</span><input class="input" :id="'w-' + group.key + '-' + line.key" inputmode="numeric" autocomplete="off" :placeholder="KH.num(0)" x-init="money($el, group.key, line.key)"></div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot><tr><td></td><td>{{ __('Total') }}</td><td class="r" x-text="bdt(total(group.key))"></td></tr></tfoot>
                    </table>
                </section>
            </template>
        </section>
    </div>
</x-layout>
