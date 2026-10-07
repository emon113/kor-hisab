<?php

namespace App\Services\Tax;

use InvalidArgumentException;

/**
 * Maps a TaxReport onto the lines of the NBR return form (config/return_form.php).
 *
 * No tax is calculated here: every figure comes from the report, plus a few
 * sums the form asks for (line 14 = 12 – 13 and so on).
 */
final class ReturnGuide
{
    public function __construct(private readonly array $form) {}

    /**
     * @param  array|null  $report  A TaxReport::build() result, or null for the blank guide.
     */
    public function build(?array $report): array
    {
        $values = $report ? $this->values($report) : null;

        $sections = [];
        foreach ($this->form['sections'] as $key => $section) {
            $rows = [];
            foreach ($section['rows'] as $row) {
                $rows[] = $row + ['value' => $values === null ? null : $this->resolve($values, $row['source'])];
            }
            $sections[$key] = ['title' => $section['title'], 'intro' => $section['intro'], 'rows' => $rows];
        }

        return [
            'form' => $this->form['form'],
            'checked_at' => $this->form['checked_at'],
            'sources' => $this->form['sources'],
            'sections' => $sections,
            'steps' => $this->form['steps'],
            'documents' => $this->form['documents'],
        ];
    }

    /** Everything a `source` in the config may point at. */
    public function values(array $report): array
    {
        $s = $report['summary'];
        $lateCharge = max(0.0, (float) $s['filing_adjustment']);
        $totalPayable = $s['tax_after_rebate'] + $lateCharge;
        $paidWithReturn = max(0.0, (float) $s['payable']);

        $inv = [];
        foreach ($report['investments']['items'] as $item) {
            $inv[$item['key']] = $item['amount'];
        }

        return [
            'summary' => $s,
            'inv' => $inv,
            'investments' => ['invested' => $report['investments']['invested'], 'eligible' => $report['investments']['eligible']],
            'computed' => [
                'net_after_rebate' => max(0.0, $s['gross_tax'] - $s['rebate']),
                'late_charge' => $lateCharge,
                'total_payable' => $totalPayable,
                'paid_with_return' => $paidWithReturn,
                'total_paid' => $s['tds_paid'] + $paidWithReturn,
                'excess' => max(0.0, -$s['payable']),
            ],
        ];
    }

    /** A dotted path, or a list of paths that are added together. */
    public function resolve(array $values, string|array $source): float
    {
        $total = 0.0;
        foreach ((array) $source as $path) {
            $value = data_get($values, $path);
            if (! is_numeric($value)) {
                throw new InvalidArgumentException("Return guide source [{$path}] does not resolve to a number.");
            }
            $total += (float) $value;
        }

        return round($total);
    }
}
