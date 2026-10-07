@use('App\Support\Money')
@php
    $rep = $r['report'];
    $s = $rep['summary'];
    $inv = $rep['investments'];
    $d = $r['data'];
    $tin = $d['tin'] ? str_repeat('•', max(0, strlen(preg_replace('/\D/', '', $d['tin'])) - 4)).substr(preg_replace('/\D/', '', $d['tin']), -4) : null;
    $generated = Money::digits(now('Asia/Dhaka')->translatedFormat('j F Y'));
@endphp
<x-layout :title="__('Tax report').' · '.$rep['rules']['label']" body-class="report-page">
    @push('scripts')
        <script>window.KH_REPORT = @js(['charts' => $rep['charts'], 'slabs' => $rep['slabs'], 'salary' => $r['salary'], 'summary' => $s, 'investments' => ['curve' => $inv['curve']]]);</script>
        <script src="{{ asset('js/report.js') }}?v={{ @filemtime(public_path('js/report.js')) }}"></script>
    @endpush

    <article class="report">
        {{-- ============ Cover ============ --}}
        <header class="report-cover reveal">
            <div class="report-brand"><x-logo /><span class="print-only">{{ __('Generated on :date', ['date' => $generated]) }}</span></div>
            <div class="report-title">
                <p class="eyebrow">{{ __('Income tax report') }} · {{ $rep['rules']['label'] }} · {{ __('Income year :period', ['period' => $rep['rules']['income_year']]) }}</p>
                <h1>{{ $d['employee'] ?: auth()->user()->name }}</h1>
                <p class="report-meta">
                    @if ($d['employer'])<span><x-icon name="folder" size="14" /> {{ $d['employer'] }}</span>@endif
                    @if ($tin)<span>{{ __('TIN') }} {{ $tin }}</span>@endif
                    <span>{{ $rep['rules']['category'] }}</span>
                </p>
            </div>
            <div class="head-actions no-print">
                <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="download" size="18" /> {{ __('Download PDF') }}</button>
                <a href="{{ route('certificates.show', $certificate) }}" class="btn btn-ghost">{{ __('Edit figures') }}</a>
                <a href="{{ route('certificates.index') }}" class="btn btn-quiet">{{ __('All certificates') }}</a>
            </div>
            <p class="hint no-print pdf-hint">{{ __('Download PDF opens the print dialog: choose “Save as PDF” as the printer.') }}</p>
        </header>

        {{-- ============ Key figures ============ --}}
        <section class="report-kpis reveal" style="--i:1" aria-label="{{ __('Key figures') }}">
            <div><span>{{ __('Salary for tax') }}</span><strong>{{ Money::bdt($s['gross']) }}</strong><small>{{ __(':amount a month', ['amount' => Money::bdt($s['gross'] / 12)]) }}</small></div>
            <div><span>{{ __('Tax for the year') }}</span><strong class="tone-cost">{{ Money::bdt($s['liability']) }}</strong><small>{{ __(':rate of salary', ['rate' => Money::pct($s['effective_rate'])]) }}</small></div>
            <div><span>{{ __('TDS deducted') }}</span><strong>{{ Money::bdt($s['tds_paid']) }}</strong><small>{{ __(':amount a month', ['amount' => Money::bdt($s['tds_paid'] / 12)]) }}</small></div>
            <div class="{{ $s['payable'] > 0 ? 'is-cost' : 'is-good' }}">
                <span>{{ $s['payable'] >= 0 ? __('Left to pay') : __('Refund due') }}</span>
                <strong>{{ Money::bdt(abs($s['payable'])) }}</strong>
                <small>{{ $s['payable'] > 0 ? __('Pay with your return') : ($s['payable'] < 0 ? __('Claim it in your return') : __('Nothing more to pay')) }}</small>
            </div>
        </section>

        @foreach ($r['checks'] as $check)
            <p class="callout {{ $check['tone'] === 'watch' ? 'warn' : '' }}" style="margin-bottom:18px">{{ $check['text'] }}</p>
        @endforeach

        {{-- ============ 1. Salary ============ --}}
        <section class="report-section reveal" style="--i:2" aria-labelledby="r-salary">
            <h2 id="r-salary"><span class="sec-no">{{ Money::digits('1') }}</span> {{ __('Salary for the year') }}</h2>
            <div class="split">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>{{ __('Component') }}</th><th class="r">{{ __('Per year') }}</th><th class="r">{{ __('Per month') }}</th><th class="r">{{ __('Share') }}</th></tr></thead>
                        <tbody>
                            @foreach ($r['salary'] as $row)
                                <tr><td>{{ $row['label'] }} @if ($row['perk'])<span class="pill">{{ __('Perk') }}</span>@endif</td><td class="r">{{ Money::bdt($row['amount']) }}</td><td class="r">{{ Money::bdt($row['monthly']) }}</td><td class="r">{{ Money::pct($row['share']) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><td>{{ __('Total salary received') }}</td><td class="r">{{ Money::bdt($s['gross']) }}</td><td class="r">{{ Money::bdt($s['gross'] / 12) }}</td><td class="r">{{ Money::digits('100') }}%</td></tr>
                        </tfoot>
                    </table>
                </div>
                <div class="chart-card" style="grid-column:auto">
                    <h3>{{ __('Salary composition') }}</h3>
                    <div class="chart-box short"><canvas id="rc-salary" role="img" aria-label="{{ __('Doughnut chart of salary components') }}"></canvas></div>
                </div>
            </div>
        </section>

        {{-- ============ 2. Tax computation ============ --}}
        <section class="report-section reveal" style="--i:3" aria-labelledby="r-tax">
            <h2 id="r-tax"><span class="sec-no">{{ Money::digits('2') }}</span> {{ __('How your tax is worked out') }}</h2>
            <div class="split print-stack">
                <table class="table computation">
                    <tbody>
                        <tr><td>{{ __('Total salary received') }}</td><td class="r">{{ Money::bdt($s['gross']) }}</td></tr>
                        <tr><td>{{ __('Less: tax-free part (⅓, up to :cap)', ['cap' => Money::bdt($rep['rules']['exemption_cap'])]) }}</td><td class="r">−{{ Money::bdt($s['exemption']) }}</td></tr>
                        <tr class="sum"><td>{{ __('Taxable income') }}</td><td class="r">{{ Money::bdt($s['taxable']) }}</td></tr>
                        <tr><td>{{ __('Tax by slab, before rebate') }}</td><td class="r">{{ Money::bdt($s['gross_tax']) }}</td></tr>
                        <tr><td>{{ __('Less: investment rebate') }}</td><td class="r tone-good">−{{ Money::bdt($s['rebate']) }}</td></tr>
                        @if ($s['min_tax_applied'])
                            <tr><td>{{ __('Raised to the minimum tax') }}</td><td class="r">{{ Money::bdt($s['min_tax']) }}</td></tr>
                        @endif
                        @if ($s['filing_adjustment'] != 0)
                            <tr><td>{{ $s['filing_adjustment'] < 0 ? __('Early filing rebate') : __('Late filing charge') }}</td><td class="r">{{ $s['filing_adjustment'] < 0 ? '−' : '+' }}{{ Money::bdt(abs($s['filing_adjustment'])) }}</td></tr>
                        @endif
                        <tr class="sum"><td>{{ __('Tax for the year') }}</td><td class="r">{{ Money::bdt($s['liability']) }}</td></tr>
                        <tr><td>{{ __('Less: TDS deducted by the employer') }}</td><td class="r">−{{ Money::bdt($s['tds_paid']) }}</td></tr>
                        <tr @class(['sum', 'total', 'cost' => $s['payable'] > 0])><td>{{ $s['payable'] >= 0 ? __('Left to pay') : __('Refund due') }}</td><td class="r">{{ Money::bdt(abs($s['payable'])) }}</td></tr>
                    </tbody>
                </table>
                <div>
                    <table class="table">
                        <thead><tr><th>{{ __('Slab') }}</th><th>{{ __('Rate') }}</th><th class="r">{{ __('Your income') }}</th><th class="r">{{ __('Tax') }}</th></tr></thead>
                        <tbody>
                            @foreach ($rep['slabs'] as $slab)
                                <tr @class(['is-top' => $slab['is_top'] && $s['taxable'] > 0])><td>{{ $slab['label'] }}</td><td>{{ Money::pct($slab['rate'], 0) }}</td><td class="r">{{ Money::bdt($slab['amount']) }}</td><td class="r">{{ Money::bdt($slab['tax']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="chart-box short" style="margin-top:14px"><canvas id="rc-slabs" role="img" aria-label="{{ __('Bar chart of tax paid in each slab') }}"></canvas></div>
                </div>
            </div>
        </section>

        {{-- ============ 3. Investments ============ --}}
        <section class="report-section reveal" style="--i:4" aria-labelledby="r-inv">
            <h2 id="r-inv"><span class="sec-no">{{ Money::digits('3') }}</span> {{ __('Investments and rebate') }}</h2>
            <div class="split">
                <div>
                    <dl class="opt-figures">
                        <div><dt>{{ __('Rebate now') }}</dt><dd class="tone-good">{{ Money::bdt($inv['rebate']) }}</dd></div>
                        <div><dt>{{ __('Maximum rebate') }}</dt><dd>{{ Money::bdt($inv['rebate_max']) }}</dd></div>
                        <div><dt>{{ __('Invest in total') }}</dt><dd>{{ Money::bdt($inv['needed']) }}</dd></div>
                    </dl>
                    <div class="meter"><i style="width:{{ round($inv['progress'] * 100) }}%"></i></div>
                    <p class="meter-caption"><span>{{ __(':amount counted', ['amount' => Money::bdt($inv['eligible'])]) }}</span><span>{{ Money::pct($inv['progress'], 0) }}</span></p>
                    @if ($inv['gap'] > 0)
                        <p class="callout" style="margin-top:14px">{{ __('Investing :gap more would have lowered the tax by :saving.', ['gap' => Money::bdt($inv['gap']), 'saving' => Money::bdt($inv['extra_saving'])]) }}</p>
                    @endif
                </div>
                <table class="table">
                    <thead><tr><th>{{ __('Investment') }}</th><th class="r">{{ __('Amount') }}</th><th class="r">{{ __('Counted') }}</th></tr></thead>
                    <tbody>
                        @foreach ($inv['items'] as $item)
                            @if ($item['amount'] > 0)
                                <tr><td>{{ $item['label'] }}</td><td class="r">{{ Money::bdt($item['amount']) }}</td><td class="r">{{ Money::bdt($item['eligible']) }}</td></tr>
                            @endif
                        @endforeach
                        @if ($inv['invested'] <= 0)
                            <tr><td colspan="3" class="muted">{{ __('No investments entered.') }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ============ 4. Insights ============ --}}
        <section class="report-section print-break reveal" style="--i:5" aria-labelledby="r-insights">
            <h2 id="r-insights"><span class="sec-no">{{ Money::digits('4') }}</span> {{ __('What the numbers say') }}</h2>
            <ul class="insights">
                @foreach ($rep['predictions'] as $p)
                    <li class="insight"><span class="insight-icon {{ $p['tone'] }}"><x-icon :name="$p['icon']" size="18" /></span><div><h3>{{ $p['title'] }}</h3><p>{{ $p['text'] }}</p></div></li>
                @endforeach
            </ul>
        </section>

        {{-- ============ 5. Tips ============ --}}
        @if ($r['tips'])
            <section class="report-section reveal" style="--i:6" aria-labelledby="r-tips">
                <h2 id="r-tips"><span class="sec-no">{{ Money::digits('5') }}</span> {{ __('Pay less tax, legally') }}</h2>
                <div class="tips">
                    @foreach ($r['tips'] as $tip)
                        <article class="tip kind-{{ $tip['kind'] }}">
                            <header>
                                <span class="tip-icon"><x-icon :name="$tip['icon']" size="18" /></span>
                                @if ($tip['saving'])<span class="tip-saving">{{ __(':amount less', ['amount' => Money::bdt($tip['saving'])]) }}</span>@endif
                            </header>
                            <h3>{{ $tip['title'] }}</h3>
                            <p>{{ $tip['about'] }}</p>
                            <small>{{ $tip['law'] }}</small>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ============ 6. Charts ============ --}}
        <section class="report-section print-break reveal" style="--i:7" aria-labelledby="r-charts">
            <h2 id="r-charts"><span class="sec-no">{{ Money::digits('6') }}</span> {{ __('Your tax in pictures') }}</h2>
            <div class="charts">
                <div class="chart-card">
                    <h3>{{ __('From gross tax to what you pay') }}</h3>
                    <div class="chart-box"><canvas id="rc-waterfall" role="img" aria-label="{{ __('Waterfall from gross tax to tax payable') }}"></canvas></div>
                </div>
                <div class="chart-card">
                    <h3>{{ __('Average vs marginal rate') }}</h3>
                    <div class="chart-box"><canvas id="rc-rate" role="img" aria-label="{{ __('Average and marginal tax rate by income') }}"></canvas></div>
                </div>
                <div class="chart-card">
                    <h3>{{ __('Rebate grows, then stops') }}</h3>
                    <div class="chart-box"><canvas id="rc-rebate" role="img" aria-label="{{ __('Line chart of rebate and tax against investment') }}"></canvas></div>
                </div>
                <div class="chart-card">
                    <h3>{{ __('Same income, other years') }}</h3>
                    <div class="chart-box"><canvas id="rc-future" role="img" aria-label="{{ __('Tax for the same income in each tax year') }}"></canvas></div>
                </div>
            </div>
        </section>

        {{-- ============ 7. Return form ============ --}}
        <section class="report-section print-break reveal" style="--i:8" aria-labelledby="r-guide">
            <h2 id="r-guide"><span class="sec-no">{{ Money::digits('7') }}</span> {{ __('Where each figure goes on your return') }}</h2>
            <p class="hint" style="margin-bottom:14px">{{ __('Line numbers of NBR’s return form :form.', ['form' => Money::digits($r['guide']['form'])]) }}</p>
            @foreach ($r['guide']['sections'] as $section)
                <h3 class="guide-h">{{ __($section['title']) }}</h3>
                <table class="table guide-table">
                    <tbody>
                        @foreach ($section['rows'] as $row)
                            <tr><td class="guide-serial">{{ Money::digits($row['serial']) }}</td><td>{{ __($row['label']) }}</td><td class="r">{{ Money::bdt($row['value']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </section>

        @if ($d['notes'])
            <section class="report-section"><h2>{{ __('Notes') }}</h2><p style="white-space:pre-line">{{ $d['notes'] }}</p></section>
        @endif

        <footer class="report-foot">
            <p>{{ __('Prepared with Kor Hishab from the salary certificate :file on :date, using the Income Tax Act 2023 as amended by the Finance Act 2026. An estimate, not tax advice: confirm with NBR or a tax practitioner before filing.', ['file' => $certificate->original_name, 'date' => $generated]) }}</p>
        </footer>
    </article>
</x-layout>
