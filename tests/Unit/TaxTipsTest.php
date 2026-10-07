<?php

namespace Tests\Unit;

use App\Services\Tax\TaxEngine;
use App\Services\Tax\TaxReport;
use App\Services\Tax\TaxTips;
use App\Support\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TaxTipsTest extends TestCase
{
    private TaxEngine $engine;

    private TaxTips $tips;

    protected function setUp(): void
    {
        Money::useDigits('latin');
        Money::useGrouping('intl');
        $this->engine = new TaxEngine(require __DIR__.'/../../config/tax.php');
        $this->tips = new TaxTips($this->engine, require __DIR__.'/../../config/tips.php');
    }

    /** Tips keyed by id, for a report on $input as of $today. */
    private function tips(array $input, string $today = '2026-10-08'): array
    {
        $date = new DateTimeImmutable($today);
        $report = (new TaxReport($this->engine))->build($input + ['year' => '2026-27', 'category' => 'general'], $date);

        return array_column($this->tips->for($report, $date), null, 'id');
    }

    private function tax(array $input): float
    {
        $report = (new TaxReport($this->engine))->build($input + ['year' => '2026-27', 'category' => 'general'], new DateTimeImmutable('2026-10-08'));

        return $report['summary']['tax_after_rebate'];
    }

    public function test_rebate_gap_comes_first_with_its_saving(): void
    {
        $tips = $this->tips(['gross_income' => 1800000]);
        $report = (new TaxReport($this->engine))->build(['gross_income' => 1800000, 'year' => '2026-27', 'category' => 'general']);

        $this->assertSame('rebate_gap', array_key_first($tips));
        $this->assertSame($report['investments']['extra_saving'], $tips['rebate_gap']['saving']);
        // With rebate room left, the less obvious eligible investments are suggested too.
        $this->assertArrayHasKey('provident_fund', $tips);
        $this->assertArrayHasKey('pension', $tips);
        $this->assertNull($tips['pension']['saving']);
    }

    public function test_savings_come_first_largest_first(): void
    {
        $savings = array_column($this->tips(['gross_income' => 1800000]), 'saving');
        $withSaving = array_values(array_filter($savings, fn ($s) => $s !== null));

        $this->assertSame($withSaving, array_slice($savings, 0, count($withSaving)));
        $sorted = $withSaving;
        rsort($sorted);
        $this->assertSame($sorted, $withSaving);
    }

    public function test_what_if_savings_match_the_engine(): void
    {
        $base = ['gross_income' => 1500000];
        $tips = $this->tips($base);

        $this->assertSame($this->tax($base) - $this->tax($base + ['category' => 'women_senior']), $tips['category']['saving']);
        $this->assertSame($this->tax($base) - $this->tax($base + ['disabled_children' => 1]), $tips['disabled_child']['saving']);
        $this->assertArrayNotHasKey('category', $this->tips($base + ['category' => 'women_senior']));
    }

    public function test_filing_date_tips(): void
    {
        // AY 2026-27 income year ended 30 June 2026: in July the early window is open.
        $july = $this->tips(['gross_income' => 1500000], '2026-07-10');
        $this->assertArrayHasKey('early_filing', $july);
        $this->assertSame(abs($this->engine->filingAdjustment($this->tax(['gross_income' => 1500000]), 'early')), $july['early_filing']['saving']);

        // In October it has closed; a late filer is told what filing on time saves.
        $late = $this->tips(['gross_income' => 1500000, 'filing' => 'late']);
        $this->assertArrayNotHasKey('early_filing', $late);
        $this->assertArrayHasKey('late_filing', $late);
    }

    public function test_minimum_tax_first_return_and_refunds(): void
    {
        $tips = $this->tips(['gross_income' => 630000, 'tds_paid' => 20000]);

        $this->assertArrayHasKey('minimum_tax', $tips);
        // Minimum tax ৳5,000 falls to ৳1,000 for a first return, but the slab tax (৳2,000) still applies.
        $this->assertSame($this->tax(['gross_income' => 630000]) - $this->tax(['gross_income' => 630000, 'new_taxpayer' => true]), $tips['first_return']['saving']);
        $this->assertSame(3000.0, $tips['first_return']['saving']);
        $this->assertArrayHasKey('refund', $tips);
        $this->assertArrayNotHasKey('first_return', $this->tips(['gross_income' => 630000, 'new_taxpayer' => true]));
    }

    public function test_nothing_to_say_without_income(): void
    {
        $this->assertSame([], $this->tips(['gross_income' => 0]));
    }

    public function test_catalogue_has_every_tip_grouped_by_kind(): void
    {
        $catalogue = $this->tips->catalogue();
        $ids = array_merge(...array_map(fn ($g) => array_column($g, 'id'), array_values($catalogue)));

        $this->assertEqualsCanonicalizing(array_keys(require __DIR__.'/../../config/tips.php'), $ids);
        foreach ($catalogue as $kind => $tips) {
            $this->assertContains($kind, TaxTips::KINDS);
            foreach ($tips as $tip) {
                $this->assertSame($kind, $tip['kind']);
                $this->assertNull($tip['saving']);
            }
        }
    }
}
