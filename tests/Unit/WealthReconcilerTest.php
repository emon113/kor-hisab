<?php

namespace Tests\Unit;

use App\Services\Wealth\WealthReconciler;
use PHPUnit\Framework\TestCase;

class WealthReconcilerTest extends TestCase
{
    private WealthReconciler $reconciler;

    protected function setUp(): void
    {
        $this->reconciler = new WealthReconciler(require __DIR__.'/../../config/wealth.php');
    }

    private function data(array $receipts = [], array $expenses = [], array $liabilities = [], array $assets = []): array
    {
        return compact('receipts', 'expenses', 'liabilities', 'assets');
    }

    public function test_it_balances_when_income_explains_the_change(): void
    {
        // Last year 10 lakh; earned 12 lakh, spent 8 lakh → expect 14 lakh. Assets 16 lakh − loan 2 lakh = 14 lakh.
        $r = $this->reconciler->reconcile($this->data(
            ['income_shown' => 800000, 'exempt_income' => 400000],
            ['food_clothing' => 500000, 'housing' => 300000],
            ['institutional' => 200000],
            ['bank' => 600000, 'sanchaypatra_dps' => 1000000],
        ), 1000000);

        $this->assertSame(1200000.0, $r['totals']['receipts']);
        $this->assertSame(1400000.0, $r['expected_net_wealth']);
        $this->assertSame(1400000.0, $r['net_wealth']);
        $this->assertSame(1600000.0, $r['gross_wealth']);
        $this->assertSame(0.0, $r['gap']);
        $this->assertSame(WealthReconciler::BALANCED, $r['status']);
    }

    public function test_unexplained_and_understated_wealth(): void
    {
        $base = ['income_shown' => 1000000];

        $more = $this->reconciler->reconcile($this->data($base, [], [], ['bank' => 2500000]), 1000000);
        $this->assertSame(500000.0, $more['gap']);
        $this->assertSame(WealthReconciler::UNEXPLAINED, $more['status']);

        $less = $this->reconciler->reconcile($this->data($base, [], [], ['bank' => 1500000]), 1000000);
        $this->assertSame(-500000.0, $less['gap']);
        $this->assertSame(WealthReconciler::UNDERSTATED, $less['status']);
    }

    public function test_small_gaps_count_as_rounding(): void
    {
        // Tolerance is the larger of ৳10,000 and 1% of sources (here ৳20,000).
        $r = $this->reconciler->reconcile($this->data(['income_shown' => 2000000], [], [], ['bank' => 3015000]), 1000000);

        $this->assertSame(20000.0, $r['tolerance']);
        $this->assertSame(WealthReconciler::BALANCED, $r['status']);
    }

    public function test_without_last_year_there_is_nothing_to_check(): void
    {
        $r = $this->reconciler->reconcile($this->data(['income_shown' => 500000], [], [], ['bank' => 200000]), null);

        $this->assertNull($r['gap']);
        $this->assertNull($r['expected_net_wealth']);
        $this->assertSame(WealthReconciler::NO_BASELINE, $r['status']);
        $this->assertSame(200000.0, $r['net_wealth']);
    }

    public function test_unknown_lines_and_negative_amounts_are_ignored(): void
    {
        $clean = $this->reconciler->clean(['assets' => ['bank' => 1000.4, 'yacht' => 5000000, 'cash' => -50]]);

        $this->assertSame(1000, $clean['assets']['bank']);
        $this->assertSame(0, $clean['assets']['cash']);
        $this->assertArrayNotHasKey('yacht', $clean['assets']);
        $this->assertSame(0, $clean['receipts']['income_shown']);
    }

    public function test_year_helpers(): void
    {
        $this->assertSame('2025-26', WealthReconciler::previousYear('2026-27'));
        $this->assertSame('1999-00', WealthReconciler::previousYear('2000-01'));
        $this->assertSame('2026-06-30', WealthReconciler::yearEnd('2026-27'));
        $this->assertSame(['2027-28', '2026-27', '2025-26', '2024-25', '2023-24'], $this->reconciler->availableYears('2026-27'));
    }
}
