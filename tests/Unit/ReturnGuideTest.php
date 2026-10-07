<?php

namespace Tests\Unit;

use App\Services\Tax\ReturnGuide;
use App\Services\Tax\TaxEngine;
use App\Services\Tax\TaxReport;
use App\Support\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ReturnGuideTest extends TestCase
{
    private TaxReport $reports;

    private ReturnGuide $guide;

    protected function setUp(): void
    {
        Money::useDigits('latin');
        $this->reports = new TaxReport(new TaxEngine(require __DIR__.'/../../config/tax.php'));
        $this->guide = new ReturnGuide(require __DIR__.'/../../config/return_form.php');
    }

    private function rows(array $input): array
    {
        $report = $this->reports->build($input + ['year' => '2026-27', 'category' => 'general'], new DateTimeImmutable('2026-10-05'));
        $rows = [];
        foreach ($this->guide->build($report)['sections'] as $key => $section) {
            foreach ($section['rows'] as $row) {
                $rows["{$key}.{$row['serial']}"] = $row['value'];
            }
        }

        return $rows;
    }

    public function test_every_line_resolves_for_a_range_of_returns(): void
    {
        foreach ([
            ['gross_income' => 0],
            ['gross_income' => 630000],
            ['gross_income' => 1335524, 'investments' => ['dps' => 120000, 'savings_certificate' => 50000, 'shares' => 10000], 'tds_paid' => 30000],
            ['gross_income' => 900000, 'tds_paid' => 90000, 'filing' => 'very_late'],
        ] as $input) {
            foreach ($this->rows($input) as $line => $value) {
                $this->assertIsFloat($value, "Line {$line} did not resolve");
            }
        }
    }

    public function test_figures_land_on_the_right_lines(): void
    {
        $r = $this->rows(['gross_income' => 1335524, 'investments' => ['dps' => 120000, 'savings_certificate' => 50000, 'mutual_fund' => 25000], 'tds_paid' => 30000]);

        $this->assertSame(1335524.0, $r['schedule1.13']);   // total salary
        $this->assertSame(445175.0, $r['schedule1.14']);    // one-third exempt
        $this->assertSame(890349.0, $r['schedule1.15']);
        $this->assertSame($r['schedule1.15'], $r['statement.1']);
        $this->assertSame(75000.0, $r['schedule5.3']);      // savings certificate + mutual fund
        $this->assertSame($r['schedule5.12'], $r['statement.13']);
        $this->assertSame($r['statement.12'] - $r['statement.13'], $r['statement.14']);
        $this->assertSame(30000.0, $r['statement.20']);
        $this->assertSame($r['statement.19'] - $r['statement.20'], $r['statement.23']);
        $this->assertSame(0.0, $r['statement.25']);
    }

    public function test_late_filing_and_refunds(): void
    {
        $late = $this->rows(['gross_income' => 1335524, 'filing' => 'late']);
        $this->assertGreaterThan(0, $late['statement.18']);
        $this->assertSame($late['statement.16'] + $late['statement.18'], $late['statement.19']);

        $refund = $this->rows(['gross_income' => 1335524, 'tds_paid' => 100000]);
        $this->assertSame(0.0, $refund['statement.23']);
        $this->assertSame(100000.0 - $refund['statement.19'], $refund['statement.25']);
    }

    public function test_blank_guide_has_no_figures(): void
    {
        foreach ($this->guide->build(null)['sections'] as $section) {
            foreach ($section['rows'] as $row) {
                $this->assertNull($row['value']);
            }
        }
    }
}
