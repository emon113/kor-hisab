<?php

namespace Tests\Unit;

use App\Services\Tax\TaxEngine;
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
        $this->engine = new TaxEngine(require __DIR__.'/../../config/tax.php');
        $this->planner = new TdsPlanner($this->engine);
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

    private function plan(array $months, float $investment = 0): array
    {
        return $this->planner->plan(['year' => '2027-28', 'category' => 'general', 'investment' => $investment, 'months' => $months]);
    }

    private function annualTax(float $gross, float $investment = 0): float
    {
        $ctx = $this->engine->context($this->engine->normalize(['year' => '2027-28', 'category' => 'general', 'gross_income' => $gross]));

        return round($this->engine->netTax($gross, $investment, $ctx));
    }

    public function test_the_plan_always_adds_up_to_the_years_tax(): void
    {
        $months = $this->months(100000, [
            'jul' => ['done' => true, 'tds' => 3000], 'aug' => ['done' => true, 'tds' => 3000], 'sep' => ['done' => true, 'tds' => 3000],
            'mar' => ['bonus' => 55000], 'jun' => ['bonus' => 55000],
        ]);
        $plan = $this->plan($months, 60000);

        $this->assertSame(1310000.0, $plan['gross']);
        $this->assertSame($this->annualTax(1310000, 60000), $plan['liability']);
        $this->assertSame(9000.0, $plan['deducted']);
        $this->assertSame(9, $plan['open_months']);
        $this->assertEquals($plan['liability'], array_sum(array_column($plan['months'], 'planned')));
        $this->assertSame(0.0, $plan['months'][0]['suggested']);   // paid months get no suggestion
    }

    public function test_months_without_salary_get_no_deduction(): void
    {
        // Joined in January: July to December are empty.
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
        $this->assertSame(80000.0 - $plan['liability'], -$plan['remaining']);
        $this->assertSame(0.0, $plan['next_monthly']);
        $this->assertStringContainsString('do not deduct further TDS', $plan['hr_text']);
    }

    public function test_behind_ahead_and_on_track(): void
    {
        $tax = $this->annualTax(1200000);
        $due = $tax / 4;   // 3 of 12 equal months earned

        $onTrack = $this->plan($this->months(100000, array_fill_keys(['jul', 'aug', 'sep'], ['done' => true, 'tds' => round($due / 3)])));
        $this->assertSame('on_track', $onTrack['status']);

        $behind = $this->plan($this->months(100000, array_fill_keys(['jul', 'aug', 'sep'], ['done' => true, 'tds' => 0])));
        $this->assertSame('behind', $behind['status']);
        $this->assertStringContainsString('each month from October 2026 to June 2027 (9 months)', $behind['hr_text']);

        $ahead = $this->plan($this->months(100000, array_fill_keys(['jul', 'aug', 'sep'], ['done' => true, 'tds' => round($due)])));
        $this->assertSame('ahead', $ahead['status']);
    }

    public function test_current_year_and_paid_months_follow_the_calendar(): void
    {
        $today = new DateTimeImmutable('2026-10-08');

        $this->assertSame('2027-28', $this->planner->currentYear($today));   // income year Jul 2026 – Jun 2027
        $done = $this->planner->defaultDone('2027-28', $today);
        $this->assertSame([true, true, true, false], [$done['jul'], $done['aug'], $done['sep'], $done['oct']]);
        $this->assertFalse($done['jun']);
    }
}
