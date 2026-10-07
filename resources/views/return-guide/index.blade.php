@use('App\Support\Money')
<x-layout :title="__('Return form guide')">
    @php $s = $report['summary'] ?? null; @endphp

    <div class="page-head">
        <div>
            <h1>{{ __('Where each number goes on your return') }}</h1>
            <p>{{ __('Line by line for NBR’s return form :form. The Bangla form uses the same serial numbers.', ['form' => Money::digits($guide['form'])]) }}</p>
        </div>
        <div class="head-actions no-print">
            <button type="button" class="btn btn-ghost" onclick="window.print()"><x-icon name="print" size="18" /> {{ __('Print') }}</button>
            @if ($calculation)
                <a href="{{ route('calculations.show', $calculation) }}" class="btn btn-primary">{{ __('Back to the calculation') }}</a>
            @else
                <a href="{{ route('home') }}" class="btn btn-primary">{{ __('Back to the calculator') }}</a>
            @endif
        </div>
    </div>

    @if ($s)
        <dl class="stats">
            <div><dt>{{ __('Tax year') }}</dt><dd>{{ $report['rules']['label'] }}</dd></div>
            <div><dt>{{ __('Gross income') }}</dt><dd>{{ Money::bdt($s['gross']) }}</dd></div>
            <div><dt>{{ __('Tax for the year') }}</dt><dd class="tone-cost">{{ Money::bdt($s['liability']) }}</dd></div>
            <div>
                <dt>{{ $s['payable'] >= 0 ? __('Left to pay') : __('Refund due') }}</dt>
                <dd class="{{ $s['payable'] > 0 ? 'tone-cost' : 'tone-good' }}">{{ Money::bdt(abs($s['payable'])) }}</dd>
            </div>
        </dl>
        @if ($calculation)
            <p class="print-only"><strong>{{ $calculation->title }}</strong></p>
        @endif
    @else
        <div class="callout no-print" style="margin-bottom:24px">
            {{ __('These are the lines that matter for a salaried taxpayer. Open this guide from the calculator to see your own figures next to each line.') }}
            <a href="{{ route('home') }}">{{ __('Open the calculator') }}</a>
        </div>
    @endif

    <div class="callout warn" style="margin-bottom:28px">
        {{ __('A guide, not the official form. Copy the figures into the NBR form or the e-return system; the system’s own calculation is final.') }}
    </div>

    @foreach ($guide['sections'] as $key => $section)
        <section class="sec" aria-labelledby="h-{{ $key }}">
            <div class="sec-head">
                <div>
                    <h2 id="h-{{ $key }}">{{ __($section['title']) }}</h2>
                    <p>{{ __($section['intro']) }}</p>
                </div>
            </div>
            <div class="table-wrap panel guide-table" style="padding:6px 16px">
                <table class="table">
                    <thead><tr><th>{{ __('Line') }}</th><th>{{ __('On the form') }}</th><th class="r">{{ __('Your figure') }}</th></tr></thead>
                    <tbody>
                        @foreach ($section['rows'] as $row)
                            <tr>
                                <td class="guide-serial">{{ Money::digits($row['serial']) }}</td>
                                <td>
                                    {{ __($row['label']) }}
                                    @if ($row['note']) <small>{{ __($row['note']) }}</small> @endif
                                </td>
                                <td class="r">{{ $row['value'] === null ? '—' : Money::bdt($row['value']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <div class="split split-even">
        <section class="panel" style="padding:22px" aria-labelledby="h-steps">
            <h2 id="h-steps" style="margin-bottom:16px">{{ __('Filing online, step by step') }}</h2>
            <ol class="steps">
                @foreach ($guide['steps'] as $step)
                    <li><div><b>{{ __($step['title']) }}</b><p>{{ __($step['text']) }}</p></div></li>
                @endforeach
            </ol>
        </section>
        <section class="panel" style="padding:22px" aria-labelledby="h-docs">
            <h2 id="h-docs" style="margin-bottom:16px">{{ __('Keep these ready') }}</h2>
            <ul class="checklist">
                @foreach ($guide['documents'] as $doc)
                    <li><label><input type="checkbox"> <span>{{ __($doc['label']) }}</span></label></li>
                @endforeach
            </ul>
        </section>
    </div>

    <section class="sources" style="margin-top:28px">
        <p>{{ __('Checked against these sources on :date. Forms and rules change: confirm with NBR before filing.', ['date' => $checkedAt]) }}</p>
        <ul>
            @foreach ($guide['sources'] as $source)
                <li><a href="{{ $source['url'] }}" rel="noopener" target="_blank">{{ __($source['label']) }}</a></li>
            @endforeach
        </ul>
    </section>
</x-layout>
