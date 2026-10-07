<?php

namespace Tests\Unit;

use App\Services\Tax\TargetTaxSolver;
use App\Services\Tax\TaxEngine;
use App\Services\Tax\TaxReport;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TaxEngineTest extends TestCase
{
    private TaxEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new TaxEngine(require __DIR__.'/../../config/tax.php');
    }

    private function report(array $input): array
    {
        return (new TaxReport($this->engine))->build($input + ['year' => '2026-27', 'category' => 'general'], new DateTimeImmutable('2026-10-05'));
    }

    public function test_it_matches_the_spreadsheet_for_the_reference_salary(): void
    {
        $r = $this->report(['gross_income' => 1335524, 'investments' => ['dps' => 120000]]);

        $this->assertSame(445175.0, $r['summary']['exemption']);   // 1/3 of salary, under the 5 lakh cap
        $this->assertSame(890349.0, $r['summary']['taxable']);
        $this->assertSame(58552.0, $r['summary']['gross_tax']);    // 0 + 30,000 + 28,552
        $this->assertSame(12000.0, $r['summary']['rebate']);       // 10% of 1,20,000
        $this->assertSame(46552.0, $r['summary']['liability']);
        $this->assertSame(267105.0, $r['investments']['needed']);  // 3% of taxable / 10%
        $this->assertSame(264476.0, $r['next_slab']['raise_needed']); // gross 16,00,000 is where the 20% slab begins
        $this->assertSame(0.2, $r['next_slab']['rate_next']);
    }

    public function test_salary_exemption_is_capped_at_five_lakh(): void
    {
        $r = $this->report(['gross_income' => 3000000]);

        $this->assertSame(500000.0, $r['summary']['exemption']);
        $this->assertSame(2500000.0, $r['summary']['taxable']);
    }

    public function test_income_within_the_threshold_pays_nothing(): void
    {
        $r = $this->report(['gross_income' => 600000]);   // taxable 4,00,000 = threshold

        $this->assertSame(0.0, $r['summary']['liability']);
        $this->assertSame(0.0, $r['summary']['min_tax']);
    }

    public function test_minimum_tax_applies_just_above_the_threshold(): void
    {
        $r = $this->report(['gross_income' => 630000]);   // taxable 4,20,000 → slab tax 2,000

        $this->assertSame(2000.0, $r['summary']['gross_tax']);
        $this->assertSame(5000.0, $r['summary']['liability']);
        $this->assertTrue($r['summary']['min_tax_applied']);
    }

    public function test_investment_caps_are_applied_per_instrument(): void
    {
        $eligible = $this->engine->eligibleInvestments(['dps' => 200000, 'savings_certificate' => 600000, 'shares' => 900000]);

        $this->assertSame(120000.0, $eligible['items']['dps']['eligible']);
        $this->assertSame(500000.0, $eligible['items']['savings_certificate']['eligible']);
        $this->assertSame(900000.0, $eligible['items']['shares']['eligible']);
        $this->assertSame(1520000.0, $eligible['total']);
    }

    public function test_women_and_seniors_get_a_higher_threshold(): void
    {
        $general = $this->report(['gross_income' => 1335524]);
        $women = $this->report(['gross_income' => 1335524, 'category' => 'women_senior']);

        $this->assertSame(450000.0, $women['summary']['threshold']);
        // Every slab shifts up by 50,000, so the saving comes off the top (15%) slab.
        $this->assertSame(7500.0, $general['summary']['gross_tax'] - $women['summary']['gross_tax']);
    }

    public function test_early_filing_rebate_and_late_filing_charge(): void
    {
        $this->assertSame(-2327.6, $this->engine->filingAdjustment(46552, 'early'));
        $this->assertSame(-25000.0, $this->engine->filingAdjustment(900000, 'early'));
        $this->assertSame(3000.0, $this->engine->filingAdjustment(46552, 'late'));       // 2% is 931, minimum 3,000
        $this->assertSame(0.0, $this->engine->filingAdjustment(46552, 'standard'));
    }

    public function test_roadmap_years_use_the_35_percent_top_rate(): void
    {
        $r = (new TaxReport($this->engine))->build(['year' => '2030-31', 'category' => 'general', 'gross_income' => 40000000]);

        $this->assertSame(0.35, $r['summary']['marginal_rate']);
    }

    public function test_target_solver_round_trips(): void
    {
        $solver = new TargetTaxSolver($this->engine);
        $result = $solver->solve(['target_tax' => 50000, 'rebate_mode' => 'custom', 'investment' => 120000, 'year' => '2026-27', 'category' => 'general']);

        $this->assertSame(1370000.0, $result['gross']);
        $this->assertSame(50000.0, $result['tax']);
        $this->assertSame($result['gross'], (float) array_sum(array_column($result['components'], 'amount')));
    }

    public function test_target_below_minimum_tax_warns(): void
    {
        $solver = new TargetTaxSolver($this->engine);
        $result = $solver->solve(['target_tax' => 3000, 'rebate_mode' => 'none', 'year' => '2026-27', 'category' => 'general']);

        $this->assertNotNull($result['warning']);
        $this->assertSame(5000.0, $result['tax']);
    }
}
