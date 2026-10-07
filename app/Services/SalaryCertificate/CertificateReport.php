<?php

namespace App\Services\SalaryCertificate;

use App\Services\Tax\ReturnGuide;
use App\Services\Tax\TaxReport;
use App\Services\Tax\TaxTips;
use App\Support\Money;

/**
 * Everything in a salary certificate report, from confirmed figures:
 * the calculator's full tax report, the legal ways to lower it, where each
 * figure goes on the return, and checks against the certificate itself.
 */
final class CertificateReport
{
    public function __construct(private readonly TaxReport $reports, private readonly TaxTips $tips) {}

    public function build(array $data, ?array $extracted = null): array
    {
        $data = CertificateForm::complete($data);
        $gross = CertificateForm::gross($data);

        $report = $this->reports->build([
            'year' => $data['year'],
            'category' => $data['category'],
            'disabled_children' => $data['disabled_children'],
            'new_taxpayer' => $data['new_taxpayer'],
            'gross_income' => $gross,
            'investments' => $data['investments'],
            'tds_paid' => $data['tds'],
            'filing' => $data['filing'],
        ]);

        $salary = [];
        foreach (config('salary_certificate.components') as $key => $def) {
            $salary[] = ['key' => $key, 'label' => __($def['label']), 'amount' => $data['components'][$key], 'perk' => false];
        }
        foreach (config('salary.perks') as $key => $def) {
            if ($data['perks'][$key] > 0) {
                $salary[] = ['key' => $key, 'label' => __($def['label']), 'amount' => $data['perks'][$key], 'perk' => true];
            }
        }
        foreach ($salary as &$row) {
            $row['monthly'] = round($row['amount'] / 12);
            $row['share'] = $gross > 0 ? round($row['amount'] / $gross, 6) : 0.0;
        }
        unset($row);

        // Does what was entered agree with the certificate's own total?
        $certificateTotal = $extracted['gross_total'] ?? null;
        $checks = [];
        if ($certificateTotal && abs($certificateTotal - $gross) > max(10, $certificateTotal * 0.005)) {
            $checks[] = ['tone' => 'watch', 'text' => __('The figures add up to :sum, but the certificate’s total says :total. Check for a missing or misread line.', [
                'sum' => Money::bdt($gross), 'total' => Money::bdt($certificateTotal),
            ])];
        }

        return [
            'data' => $data,
            'report' => $report,
            'tips' => $this->tips->for($report),
            'guide' => (new ReturnGuide(config('return_form')))->build($report),
            'salary' => $salary,
            'gross' => $gross,
            'checks' => $checks,
        ];
    }
}
