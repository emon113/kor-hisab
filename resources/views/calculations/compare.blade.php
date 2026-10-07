@use('App\Support\Money')
@php
    $sa = $ra['summary']; $sb = $rb['summary'];
    $rows = [
        [__('Gross income'), $sa['gross'], $sb['gross'], 'neutral'],
        [__('Tax-free salary'), $sa['exemption'], $sb['exemption'], 'neutral'],
        [__('Taxable income'), $sa['taxable'], $sb['taxable'], 'neutral'],
        [__('Tax before rebate'), $sa['gross_tax'], $sb['gross_tax'], 'cost'],
        [__('Investment rebate'), $sa['rebate'], $sb['rebate'], 'gain'],
        [__('Tax for the year'), $sa['liability'], $sb['liability'], 'cost'],
        [__('Monthly take-home'), $sa['take_home_monthly'], $sb['take_home_monthly'], 'gain'],
        [__('Eligible investment'), $ra['investments']['eligible'], $rb['investments']['eligible'], 'neutral'],
        [__('Still to invest for full rebate'), $ra['investments']['gap'], $rb['investments']['gap'], 'cost'],
    ];
@endphp
<x-layout :title="__('Compare calculations')">
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const a = @js(['title' => $a->title, 's' => $sa, 'slabs' => $ra['slabs']]);
                const b = @js(['title' => $b->title, 's' => $sb, 'slabs' => $rb['slabs']]);
                const draw = () => {
                    KH.chart(document.getElementById('cmpChart'), {
                        type: 'bar',
                        data: {
                            labels: [KH.t('Tax before rebate'), KH.t('Rebate'), KH.t('Tax for the year'), KH.t('Monthly take-home')],
                            datasets: [
                                { label: a.title, data: [a.s.gross_tax, a.s.rebate, a.s.liability, a.s.take_home_monthly], backgroundColor: KH.css('--slab-2'), borderRadius: 6, maxBarThickness: 44 },
                                { label: b.title, data: [b.s.gross_tax, b.s.rebate, b.s.liability, b.s.take_home_monthly], backgroundColor: KH.css('--green'), borderRadius: 6, maxBarThickness: 44 },
                            ],
                        },
                        options: { plugins: { tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + KH.bdt(c.parsed.y) } } }, scales: { y: KH.axisMoney({ beginAtZero: true }), x: { grid: { display: false } } } },
                    });
                    KH.chart(document.getElementById('slabCmpChart'), {
                        type: 'bar',
                        data: {
                            labels: a.slabs.map((s) => KH.pct(s.rate, 0)),
                            datasets: [
                                { label: a.title, data: a.slabs.map((s) => s.amount), backgroundColor: KH.css('--slab-2'), borderRadius: 6, maxBarThickness: 36 },
                                { label: b.title, data: b.slabs.map((s) => s.amount), backgroundColor: KH.css('--green'), borderRadius: 6, maxBarThickness: 36 },
                            ],
                        },
                        options: { plugins: { tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + KH.bdt(c.parsed.y) } } }, scales: { y: KH.axisMoney({ beginAtZero: true }), x: { grid: { display: false } } } },
                    });
                };
                draw();
                window.addEventListener('kh:theme', draw);
            });
        </script>
    @endpush

    <div class="page-head">
        <div>
            <h1>{{ __('Compare calculations') }}</h1>
            <p>{{ __('Both are recalculated with the current rules for their tax year. The last column shows the change from the first to the second.') }}</p>
        </div>
        <a href="{{ route('calculations.index') }}" class="btn btn-ghost">{{ __('Back to saved') }}</a>
    </div>

    <div class="table-wrap panel" style="padding:8px 18px;margin-bottom:28px">
        <table class="table">
            <thead>
                <tr>
                    <th></th>
                    <th class="r"><a href="{{ route('calculations.show', $a) }}">{{ $a->title }}</a><br><span class="muted">{{ $a->yearLabel() }}</span></th>
                    <th class="r"><a href="{{ route('calculations.show', $b) }}">{{ $b->title }}</a><br><span class="muted">{{ $b->yearLabel() }}</span></th>
                    <th class="r">{{ __('Change') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as [$label, $va, $vb, $kind])
                    @php
                        $d = $vb - $va;
                        $class = $kind === 'neutral' || abs($d) < 1 ? '' : (($kind === 'cost') === ($d > 0) ? 'diff-up' : 'diff-down');
                    @endphp
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="r">{{ Money::bdt($va) }}</td>
                        <td class="r">{{ Money::bdt($vb) }}</td>
                        <td class="r {{ $class }}">{{ abs($d) < 1 ? '–' : ($d > 0 ? '+' : '').Money::bdt($d) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td>{{ __('Effective rate') }}</td>
                    <td class="r">{{ Money::pct($sa['effective_rate']) }}</td>
                    <td class="r">{{ Money::pct($sb['effective_rate']) }}</td>
                    <td class="r">{{ __(':points pts', ['points' => Money::digits(number_format(($sb['effective_rate'] - $sa['effective_rate']) * 100, 2))]) }}</td>
                </tr>
                <tr>
                    <td>{{ __('Top slab') }}</td>
                    <td class="r">{{ Money::pct($sa['marginal_rate'], 0) }}</td>
                    <td class="r">{{ Money::pct($sb['marginal_rate'], 0) }}</td>
                    <td class="r"></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="charts">
        <div class="panel chart-card">
            <h3>{{ __('Key figures side by side') }}</h3>
            <p>{{ __('Tax, rebate and take-home pay.') }}</p>
            <div class="chart-box"><canvas id="cmpChart" role="img" aria-label="{{ __('Grouped bar chart comparing two calculations') }}"></canvas></div>
        </div>
        <div class="panel chart-card">
            <h3>{{ __('Income in each slab') }}</h3>
            <p>{{ __('How each calculation fills the slabs.') }}</p>
            <div class="chart-box"><canvas id="slabCmpChart" role="img" aria-label="{{ __('Income per slab for both calculations') }}"></canvas></div>
        </div>
    </div>
</x-layout>
