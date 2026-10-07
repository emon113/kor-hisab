<?php

namespace Tests\Unit;

use App\Services\Tax\TaxEngine;
use App\Services\Tax\TaxReport;
use App\Services\Tax\TdsPlanner;
use App\Support\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TdsPlannerTest extends TestCase
{
    private TaxEngine $engine;

    private TdsPlanner $planner;

    protected function setUp(): void
    {
        Money::useDigits('latin');
        Money::useGrouping('intl');
        $this->engine = new TaxEngine(require __DIR__.'/../../config/tax.php');
        $perks = array_keys((require __DIR__.'/../../config/salary.php')['perks']);
        $this->planner = new TdsPlanner($this->engine, new TaxReport($this->engine), $perks);
    }

    /** 12 months of $salary; $overrides by month key. */
    private function months(float $salary, array $overrides = []): array
    {
        $months = [];
        foreach (TdsPlanner::MONTHS as $key) {
            $months[$key] = array_merge(['salary' => $salary, 'bonus' => 0, 'tds' => 0, 'done' => false], $overrides[$key] ?? []);
        }

        return $months;
    }

    private function plan(array $months, array $extra = [], string $today = '2026-10-08'): array
    {
        return $this->planner->plan(['year' => '2027-28', 'category' => 'general', 'months' => $months] + $extra, new DateTimeImmutable($today));
    }

    private function tax(float $gross, float $eligible = 0): float
    {
        $ctx = $this->engine->context($this->engine->normalize(['year' => '2027-28', 'category' => 'general']));

        return round($this->engine->netTax($gross, $eligible, $ctx));
    }

    public function test_three_outcomes_match_the_engine(): void
    {
        $plan = $this->plan($this->months(150000), ['investments' => ['dps' => 60000]]);

        $this->assertSame(1800000.0, $plan['gross']);
        $this->assertSame($this->tax(1800000), $plan['outcomes']['none']);
        $this->assertSame($this->tax(1800000, 60000), $plan['outcomes']['current']);
        $this->assertSame($this->tax(1800000, $plan['investments']['needed']), $plan['outcomes']['best']);
        $this->assertLessThan($plan['outcomes']['current'], $plan['outcomes']['best']);
        $this->assertSame($plan['outcomes']['current'] - $plan['outcomes']['best'], $plan['outcomes']['saving']);
    }

    public function test_perks_and_bonuses_count_as_salary(): void
    {
        $plan = $this->plan($this->months(100000, ['mar' => ['bonus' => 60000]]), ['perks' => ['employer_pf' => 72000, 'transport' => 60000, 'unknown' => 999999]]);

        $this->assertSame(132000.0, $plan['perk_total']);                 // unknown perk ignored
        $this->assertSame(1200000.0 + 60000 + 132000, $plan['gross']);
        $this->assertGreaterThan(0, $plan['impact']['bonuses']);
        $this->assertGreaterThan(0, $plan['impact']['perks']);
        $this->assertContains('pf', array_column($plan['insights'], 'key')); // employer PF can also count as investment
    }

    public function test_current_strategy_covers_the_tax(): void
    {
        $paid = array_fill_keys(['jul', 'aug', 'sep'], ['done' => true, 'tds' => 3000]);
        $plan = $this->plan($this->months(100000, $paid + ['mar' => ['bonus' => 55000], 'jun' => ['bonus' => 55000]]), ['investments' => ['dps' => 60000]]);

        $this->assertSame(9000.0, $plan['deducted']);
        $this->assertSame(9, $plan['open_months']);
        $this->assertEquals($plan['liability'], array_sum(array_column($plan['months'], 'planned')));
        $this->assertSame(0.0, $plan['year_end']);
        $this->assertSame(0.0, $plan['months'][0]['suggested']);   // paid months get no suggestion
    }

    public function test_full_rebate_strategy_targets_the_lowest_tax(): void
    {
        $plan = $this->plan($this->months(150000), ['strategy' => 'full_rebate']);

        $this->assertSame($plan['outcomes']['best'], $plan['target']);
        $this->assertEquals($plan['outcomes']['best'], array_sum(array_column($plan['months'], 'planned')));
        // If the investments are not made, the gap is due with the return.
        $this->assertSame($plan['outcomes']['current'] - $plan['outcomes']['best'], $plan['year_end']);
        $this->assertStringContainsString('I will invest', $plan['hr_text']);
    }

    public function test_cap_strategy_leaves_the_rest_for_the_return(): void
    {
        $plan = $this->plan($this->months(150000), ['strategy' => 'cap', 'monthly_cap' => 3000]);

        foreach ($plan['months'] as $row) {
            $this->assertLessThanOrEqual(3000, $row['suggested']);
        }
        $this->assertSame(36000.0, array_sum(array_column($plan['months'], 'planned')));
        $this->assertSame($plan['liability'] - 36000, $plan['year_end']);
        $this->assertContains('year_end', array_column($plan['insights'], 'key'));

        // A cap above what is needed changes nothing.
        $loose = $this->plan($this->months(150000), ['strategy' => 'cap', 'monthly_cap' => 1000000]);
        $this->assertSame(0.0, $loose['year_end']);
    }

    public function test_the_monthly_range_spans_full_rebate_to_no_investment(): void
    {
        $plan = $this->plan($this->months(150000));

        $this->assertSame(ceil($plan['outcomes']['best'] / 12), $plan['range']['low']);
        $this->assertSame(ceil($plan['outcomes']['none'] / 12), $plan['range']['high']);
    }

    public function test_months_without_salary_get_no_deduction(): void
    {
        $empty = array_fill_keys(['jul', 'aug', 'sep', 'oct', 'nov', 'dec'], ['salary' => 0]);
        $plan = $this->plan($this->months(150000, $empty));

        $this->assertSame(6, $plan['open_months']);
        foreach (array_slice($plan['months'], 0, 6) as $row) {
            $this->assertSame(0.0, $row['suggested']);
        }
        $this->assertEquals($plan['liability'], array_sum(array_column($plan['months'], 'suggested')));
    }

    public function test_over_deduction_is_a_refund(): void
    {
        $paid = array_fill_keys(['jul', 'aug', 'sep', 'oct'], ['done' => true, 'tds' => 20000]);
        $plan = $this->plan($this->months(100000, $paid));

        $this->assertSame('refund', $plan['status']);
        $this->assertSame(0.0, $plan['next_monthly']);
        $this->assertStringContainsString('do not deduct further TDS', $plan['hr_text']);
    }

    public function test_advice_follows_the_numbers(): void
    {
        $plan = $this->plan($this->months(150000));
        $keys = array_column($plan['insights'], 'key');
        $lowest = $plan['insights'][array_search('lowest', $keys, true)];

        $this->assertSame('apply_plan', $lowest['action']['type']);
        $this->assertNotEmpty($lowest['action']['plan']);
        $this->assertNotContains('tds', $keys);                         // calculator-only predictions are left out

        // Early-filing advice shows while 30 September 2027 is still ahead, and not after it.
        $this->assertContains('early', $keys);
        $this->assertNotContains('early', array_column($this->plan($this->months(150000), [], '2027-10-15')['insights'], 'key'));
    }

    public function test_current_year_and_paid_months_follow_the_calendar(): void
    {
        $today = new DateTimeImmutable('2026-10-08');

        $this->assertSame('2027-28', $this->planner->currentYear($today));
        $done = $this->planner->defaultDone('2027-28', $today);
        $this->assertSame([true, true, true, false], [$done['jul'], $done['aug'], $done['sep'], $done['oct']]);
    }
}
