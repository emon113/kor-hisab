@use('App\Support\Money')
<x-layout :title="__('Saved calculations')">
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const data = @js($chart);
                const canvas = document.getElementById('historyChart');
                if (!canvas || data.labels.length < 2) return;
                const draw = () => KH.chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [
                            { type: 'bar', label: KH.t('Tax'), data: data.liability, backgroundColor: KH.css('--red'), borderRadius: 6, maxBarThickness: 36, yAxisID: 'y' },
                            { type: 'line', label: KH.t('Effective rate'), data: data.rate, borderColor: KH.css('--green'), backgroundColor: KH.css('--green'), yAxisID: 'y2', tension: 0.25, pointRadius: 4 },
                        ],
                    },
                    options: {
                        interaction: { mode: 'index', intersect: false },
                        plugins: { tooltip: { callbacks: { label: (c) => ' ' + (c.dataset.yAxisID === 'y2' ? KH.t('Effective rate: :rate%', { rate: KH.num(c.parsed.y) }) : KH.t('Tax: :amount', { amount: KH.bdt(c.parsed.y) })) } } },
                        scales: {
                            y: KH.axisMoney({ beginAtZero: true }),
                            y2: { position: 'right', beginAtZero: true, grid: { display: false }, ticks: { callback: (v) => KH.num(v) + '%' } },
                            x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, callback: function (v) { const l = this.getLabelForValue(v); return l.length > 18 ? l.slice(0, 17) + '…' : l; } } },
                        },
                    },
                });
                draw();
                window.addEventListener('kh:theme', draw);
            });

            // Compare: allow at most two selections.
            document.addEventListener('change', (e) => {
                if (!e.target.matches('[data-compare]')) return;
                const picked = [...document.querySelectorAll('[data-compare]:checked')];
                if (picked.length > 2) { e.target.checked = false; KH.toast(KH.t('Pick two calculations to compare.')); return; }
                const bar = document.getElementById('compareBar');
                bar.hidden = picked.length === 0;
                document.getElementById('compareCount').textContent = picked.length === 2 ? KH.t('Ready to compare') : KH.t('Pick one more');
                const go = document.getElementById('compareGo');
                go.disabled = picked.length !== 2;
                if (picked.length === 2) go.dataset.href = go.dataset.base + '?a=' + picked[0].value + '&b=' + picked[1].value;
            });
            document.addEventListener('click', (e) => {
                if (e.target.closest('#compareGo') && e.target.closest('#compareGo').dataset.href) location.href = e.target.closest('#compareGo').dataset.href;
                const del = e.target.closest('[data-confirm]');
                if (del && !confirm(del.dataset.confirm)) e.preventDefault();
            });
        </script>
    @endpush

    <div class="page-head">
        <div>
            <h1>{{ __('Saved calculations') }}</h1>
            <p>{{ __('Reopen one to keep editing, or tick two to compare them side by side.') }}</p>
        </div>
        <a href="{{ route('home', ['new' => 1]) }}" class="btn btn-primary"><x-icon name="plus" size="18" /> {{ __('New calculation') }}</a>
    </div>

    @if ($calculations->isEmpty())
        <div class="panel empty">
            <h2>{{ __('Nothing saved yet') }}</h2>
            <p>{{ __('Work out your tax, then press Save calculation. It will appear here.') }}</p>
            <a href="{{ route('home') }}" class="btn btn-primary">{{ __('Open the calculator') }}</a>
        </div>
    @else
        <dl class="stats">
            <div><dt>{{ __('Saved') }}</dt><dd>{{ Money::digits((string) $stats['count']) }}</dd></div>
            <div><dt>{{ __('Latest tax') }}</dt><dd class="tone-cost">{{ Money::bdt($stats['latest']->liability) }}</dd></div>
            <div><dt>{{ __('Lowest tax') }}</dt><dd class="tone-good">{{ Money::bdt($stats['lowest']->liability) }}</dd></div>
            <div><dt>{{ __('Average effective rate') }}</dt><dd>{{ Money::pct($stats['average_rate']) }}</dd></div>
        </dl>

        @if (count($chart['labels']) >= 2)
            <div class="panel chart-card" style="margin-bottom:28px">
                <h3>{{ __('Tax across your saved calculations') }}</h3>
                <p>{{ __('Oldest to newest. Bars are tax for the year; the line is the share of gross income.') }}</p>
                <div class="chart-box"><canvas id="historyChart" role="img" aria-label="{{ __('Tax and effective rate across saved calculations') }}"></canvas></div>
            </div>
        @endif

        <ul class="calc-list">
            @foreach ($calculations as $calc)
                <li class="calc-item">
                    <input type="checkbox" class="check" data-compare value="{{ $calc->id }}" aria-label="{{ __('Select :title to compare', ['title' => $calc->title]) }}" style="width:18px;height:18px;accent-color:var(--green)">
                    <div class="calc-title">
                        <a href="{{ route('calculations.show', $calc) }}">{{ $calc->title }}</a>
                        <span>{{ __(':year. Updated :when', ['year' => $calc->yearLabel(), 'when' => Money::digits($calc->updated_at->diffForHumans())]) }}</span>
                    </div>
                    <dl class="calc-fig"><dt>{{ __('Gross income') }}</dt><dd>{{ Money::bdt($calc->gross_income) }}</dd></dl>
                    <dl class="calc-fig"><dt>{{ __('Tax') }}</dt><dd class="tone-cost">{{ Money::bdt($calc->liability) }}</dd></dl>
                    <dl class="calc-fig"><dt>{{ __('Effective rate') }}</dt><dd>{{ Money::pct($calc->effective_rate) }}</dd></dl>
                    <div class="calc-actions">
                        <a href="{{ route('calculations.guide', $calc) }}" class="icon-btn" title="{{ __('Return form guide') }}" aria-label="{{ __('Return form guide for :title', ['title' => $calc->title]) }}"><x-icon name="guide" size="16" /></a>
                        <a href="{{ route('calculations.show', $calc) }}" class="icon-btn" title="{{ __('Open') }}" aria-label="{{ __('Open :title', ['title' => $calc->title]) }}"><x-icon name="open" size="16" /></a>
                        <form method="POST" action="{{ route('calculations.destroy', $calc) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="icon-btn" title="{{ __('Delete') }}" aria-label="{{ __('Delete :title', ['title' => $calc->title]) }}" data-confirm="{{ __('Delete “:title”? This can’t be undone.', ['title' => $calc->title]) }}"><x-icon name="trash" size="16" /></button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="compare-bar" id="compareBar" hidden>
            <span id="compareCount">{{ __('Pick one more') }}</span>
            <button type="button" class="btn btn-primary btn-sm" id="compareGo" data-base="{{ route('calculations.compare') }}" disabled><x-icon name="compare" size="16" /> {{ __('Compare') }}</button>
        </div>
    @endif
</x-layout>
