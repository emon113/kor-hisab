@use('App\Support\Money')
@use('App\Models\WealthStatement')
@php $year = WealthStatement::yearLabel($statement->tax_year); @endphp
<x-layout :title="__('Assets and liabilities').' · '.$year">
    <div class="page-head">
        <div>
            <h1>{{ __('Statement of assets, liabilities and expenses') }}</h1>
            <p>{{ $year }} · {{ __('Position as on :date', ['date' => WealthStatement::asOnLabel($statement->tax_year)]) }} · {{ auth()->user()->name }}</p>
        </div>
        <div class="head-actions no-print">
            <button type="button" class="btn btn-ghost" onclick="window.print()"><x-icon name="print" size="18" /> {{ __('Print') }}</button>
            <a href="{{ route('wealth.edit', $statement->tax_year) }}" class="btn btn-primary">{{ __('Edit') }}</a>
        </div>
    </div>

    <p class="callout warn" style="margin-bottom:24px">{{ __('Your working copy in the order of IT-10B (2023). Copy the figures into the official form or the e-return system.') }}</p>

    @php
        $line = fn ($serial, $label, $value, $strong = false) => ['serial' => $serial, 'label' => $label, 'value' => $value, 'strong' => $strong];
        $rows = [
            $line('1', __('Sources of fund'), $result['totals']['receipts'], true),
            $line('2', __('Net wealth at the end of last year'), $result['previous_net_wealth']),
            $line('4', __('Expenses and losses'), $result['totals']['expenses'], true),
            $line('5', __('Net wealth at the end of this year'), $result['net_wealth'], true),
            $line('6', __('Personal liabilities'), $result['totals']['liabilities']),
            $line('7', __('Gross wealth (5 + 6)'), $result['gross_wealth'], true),
            $line('10', __('Total assets in and outside Bangladesh'), $result['totals']['assets'], true),
        ];
    @endphp

    <section class="sec">
        <div class="table-wrap panel guide-table" style="padding:6px 16px">
            <table class="table">
                <thead><tr><th>{{ __('Line') }}</th><th>{{ __('Summary') }}</th><th class="r">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td class="guide-serial">{{ Money::digits($r['serial']) }}</td>
                            <td>@if ($r['strong'])<strong>{{ $r['label'] }}</strong>@else{{ $r['label'] }}@endif</td>
                            <td class="r">{{ $r['value'] === null ? '—' : Money::bdt($r['value']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($result['gap'] !== null && $result['status'] !== 'balanced')
            <p class="callout cost" style="margin-top:12px">{{ __('Line 5 worked out from income (:expected) differs from assets less liabilities (:actual) by :gap. Resolve this before filing.', ['expected' => Money::bdt($result['expected_net_wealth']), 'actual' => Money::bdt($result['net_wealth']), 'gap' => Money::bdt(abs($result['gap']))]) }}</p>
        @endif
    </section>

    @foreach (['receipts', 'expenses', 'liabilities', 'assets'] as $group)
        <section class="sec">
            <div class="sec-head"><h2>{{ __($groups[$group]['title']) }}</h2></div>
            <div class="table-wrap panel guide-table" style="padding:6px 16px">
                <table class="table">
                    <tbody>
                        @foreach ($groups[$group]['lines'] as $key => $def)
                            <tr>
                                <td class="guide-serial">{{ Money::digits($def['serial']) }}</td>
                                <td>{{ __($def['label']) }}</td>
                                <td class="r">{{ Money::bdt($statement->{$group}[$key] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><td></td><td>{{ __('Total') }}</td><td class="r">{{ Money::bdt($result['totals'][$group]) }}</td></tr></tfoot>
                </table>
            </div>
        </section>
    @endforeach

    @if ($statement->notes)
        <section class="sec"><h2>{{ __('Notes') }}</h2><p style="white-space:pre-line">{{ $statement->notes }}</p></section>
    @endif
</x-layout>
