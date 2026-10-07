<?php

namespace Tests\Unit;

use App\Services\Tax\OfferComparer;
use App\Services\Tax\TaxEngine;
use PHPUnit\Framework\TestCase;

class OfferComparerTest extends TestCase
{
    private TaxEngine $engine;

    private OfferComparer $comparer;

    protected function setUp(): void
    {
        $this->engine = new TaxEngine(require __DIR__.'/../../config/tax.php');
        $this->comparer = new OfferComparer($this->engine);
    }

    private function tax(float $gross, float $eligible = 0): float
    {
        $ctx = $this->engine->context($this->engine->normalize(['year' => '2026-27', 'category' => 'general']));

        return round($this->engine->netTax($gross, $eligible, $ctx));
    }

    private function compare(array $offers, string $mode = 'none', float $investment = 0): array
    {
        return $this->comparer->compare(['year' => '2026-27', 'category' => 'general', 'investment_mode' => $mode, 'investment' => $investment, 'offers' => $offers]);
    }

    public function test_tax_matches_the_engine_and_pf_is_taxed_but_not_cash(): void
    {
        $r = $this->compare([
            ['name' => 'Now', 'monthly' => 100000, 'basic_pct' => 60, 'bonus_count' => 2, 'bonus_base' => 'basic', 'employer_pf' => 6000],
            ['name' => 'Offer', 'monthly' => 120000, 'basic_pct' => 50, 'bonus_count' => 2, 'bonus_base' => 'gross'],
        ]);
        [$a, $b] = $r['offers'];

        // A: 12 × 1,00,000 + 2 × 60,000 bonus + 12 × 6,000 PF.
        $this->assertSame(120000.0, $a['bonuses']);
        $this->assertSame(72000.0, $a['employer_pf']);
        $this->assertSame(1392000.0, $a['gross']);
        $this->assertSame($this->tax(1392000), $a['tax']);
        $this->assertSame(1320000.0 - $a['tax'], $a['take_home_annual']);    // PF is not cash in hand
        $this->assertSame($a['take_home_annual'] + 72000, $a['total_value']);

        // B: bonuses are months of gross.
        $this->assertSame(240000.0, $b['bonuses']);
        $this->assertSame(1680000.0, $b['gross']);
        $this->assertSame($this->tax(1680000), $b['tax']);
    }

    public function test_best_offer_and_differences(): void
    {
        $r = $this->compare([
            ['name' => 'A', 'monthly' => 100000],
            ['name' => 'B', 'monthly' => 130000],
            ['name' => 'C', 'monthly' => 115000],
        ]);

        $this->assertSame(1, $r['best']);
        $this->assertTrue($r['offers'][1]['best']);
        $this->assertNull($r['offers'][0]['vs_first']);
        $this->assertEqualsWithDelta(0.3, $r['offers'][1]['vs_first']['gross_raise'], 1e-9);
        // Tax takes a bite: the take-home raise is smaller than the gross raise.
        $this->assertLessThan(0.3, $r['offers'][1]['vs_first']['take_home_raise']);
        $this->assertSame($r['offers'][1]['tax'] - $r['offers'][0]['tax'], $r['offers'][1]['vs_first']['tax']);
    }

    public function test_investment_modes(): void
    {
        $offers = [['name' => 'A', 'monthly' => 150000], ['name' => 'B', 'monthly' => 150000]];

        $none = $this->compare($offers)['offers'][0];
        $same = $this->compare($offers, 'same', 100000)['offers'][0];
        $max = $this->compare($offers, 'max')['offers'][0];

        $this->assertSame(0.0, $none['rebate']);
        $this->assertSame($this->tax(1800000, 100000), $same['tax']);
        $this->assertSame($max['investment_needed'], $max['investment']);
        $this->assertLessThan($same['tax'], $max['tax']);
    }

    public function test_offers_without_a_name_get_a_letter(): void
    {
        $r = $this->compare([['monthly' => 50000], ['name' => '  ', 'monthly' => 60000]]);

        $this->assertSame(['A', 'B'], array_column($r['offers'], 'name'));
    }
}
