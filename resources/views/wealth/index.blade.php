@use('App\Support\Money')
@use('App\Models\WealthStatement')
<x-layout :title="__('Assets and liabilities')">
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const data = @js($chart);
                const canvas = document.getElementById('wealthChart');
                if (!canvas || data.labels.length < 2) return;
                const draw = () => KH.chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [
                            { type: 'line', label: KH.t('Net wealth'), data: data.net, borderColor: KH.css('--green'), backgroundColor: KH.css('--green'), tension: 0.25, pointRadius: 4, borderWidth: 2.5 },
                            { label: KH.t('Total assets'), data: data.assets, backgroundColor: KH.css('--slab-1'), borderRadius: 6, maxBarThickness: 40 },
                            { label: KH.t('Liabilities'), data: data.liabilities, backgroundColor: KH.css('--red-soft'), borderRadius: 6, maxBarThickness: 40 },
                        ],
                    },
                    options: {
                        interaction: { mode: 'index', intersect: false },
                        plugins: { tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + KH.bdt(c.parsed.y) } } },
                        scales: { y: KH.axisMoney({ beginAtZero: true }), x: { grid: { display: false } } },
                    },
                });
                draw();
                window.addEventListener('kh:theme', draw);
            });
            document.addEventListener('click', (e) => {
                const del = e.target.closest('[data-confirm]');
                if (del && !confirm(del.dataset.confirm)) e.preventDefault();
            });
        </script>
    @endpush

    <div class="page-head">
        <div>
            <h1>{{ __('Assets and liabilities') }}</h1>
            <p>{{ __('Keep the statement NBR asks for (IT-10B) year by year. Each year starts from last year’s figures, and the check shows whether your income explains the change in your wealth.') }}</p>
        </div>
        @if ($available)
            <form class="head-actions" onsubmit="location.href = this.action + '/' + this.year.value; return false;" action="{{ route('wealth.index') }}">
                <select name="year" class="select" aria-label="{{ __('Tax year') }}" style="width:auto">
                    @foreach ($available as $y)
                        <option value="{{ $y }}">{{ WealthStatement::yearLabel($y) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary"><x-icon name="plus" size="18" /> {{ __('Start a year') }}</button>
            </form>
        @endif
    </div>

    <div class="split">
        <div>
            @if ($rows->isEmpty())
                <div class="panel empty">
                    <h2>{{ __('No statements yet') }}</h2>
                    <p>{{ __('Start with the year you are filing for. Next year, your assets and liabilities carry over automatically.') }}</p>
                </div>
            @else
                @if (count($chart['labels']) >= 2)
                    <div class="panel chart-card" style="margin-bottom:22px">
                        <h3>{{ __('Your wealth over the years') }}</h3>
                        <p>{{ __('Assets and liabilities at the end of each income year; the line is net wealth.') }}</p>
                        <div class="chart-box"><canvas id="wealthChart" role="img" aria-label="{{ __('Net wealth, assets and liabilities by year') }}"></canvas></div>
                    </div>
                @endif

                <ul class="calc-list">
                    @foreach ($rows as $row)
                        @php
                            $st = $row['statement']; $r = $row['result'];
                            [$tone, $label] = match ($r['status']) {
                                'balanced' => ['good', __('Adds up')],
                                'unexplained' => ['cost', __('Unexplained :amount', ['amount' => Money::bdt(abs($r['gap']))])],
                                'understated' => ['watch', __('Short by :amount', ['amount' => Money::bdt(abs($r['gap']))])],
                                default => ['info', __('No starting point')],
                            };
                        @endphp
                        <li class="calc-item wealth-item">
                            <div class="calc-title">
                                <a href="{{ route('wealth.edit', $st->tax_year) }}">{{ WealthStatement::yearLabel($st->tax_year) }}</a>
                                <span><span class="pill tone-{{ $tone }}">{{ $label }}</span></span>
                            </div>
                            <dl class="calc-fig"><dt>{{ __('Total assets') }}</dt><dd>{{ Money::bdt($r['totals']['assets']) }}</dd></dl>
                            <dl class="calc-fig"><dt>{{ __('Liabilities') }}</dt><dd>{{ Money::bdt($r['totals']['liabilities']) }}</dd></dl>
                            <dl class="calc-fig"><dt>{{ __('Net wealth') }}</dt><dd class="tone-good">{{ Money::bdt($r['net_wealth']) }}</dd></dl>
                            <div class="calc-actions">
                                <a href="{{ route('wealth.print', $st->tax_year) }}" class="icon-btn" title="{{ __('Print view') }}" aria-label="{{ __('Print view') }}"><x-icon name="print" size="16" /></a>
                                <a href="{{ route('wealth.edit', $st->tax_year) }}" class="icon-btn" title="{{ __('Open') }}" aria-label="{{ __('Open :title', ['title' => WealthStatement::yearLabel($st->tax_year)]) }}"><x-icon name="open" size="16" /></a>
                                <form method="POST" action="{{ route('wealth.destroy', $st->tax_year) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn" title="{{ __('Delete') }}" aria-label="{{ __('Delete :title', ['title' => WealthStatement::yearLabel($st->tax_year)]) }}" data-confirm="{{ __('Delete “:title”? This can’t be undone.', ['title' => WealthStatement::yearLabel($st->tax_year)]) }}"><x-icon name="trash" size="16" /></button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <aside class="panel" style="padding:20px;align-self:start">
            <h3 style="margin-bottom:10px">{{ __('Who must submit IT-10B') }}</h3>
            <ul class="checklist">
                @foreach ($whoMustSubmit as $item)
                    <li style="font-size:var(--t-sm)">{{ __($item['text']) }}</li>
                @endforeach
            </ul>
            <p class="hint" style="margin-top:12px">{{ __('From the IT-10B (2023) form. Even when it is optional, keeping it helps you answer questions about where your savings came from.') }}</p>
        </aside>
    </div>
</x-layout>
