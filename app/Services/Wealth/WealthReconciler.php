<?php

namespace App\Services\Wealth;

/**
 * The IT-10B (2023) arithmetic, as a check.
 *
 * The form derives this year's net wealth from last year's plus sources of
 * fund minus expenses (line 5), and it must also equal assets minus
 * liabilities. When the two disagree, the return does not explain where
 * the wealth came from (or where it went).
 */
final class WealthReconciler
{
    public const BALANCED = 'balanced';

    public const UNEXPLAINED = 'unexplained';      // more wealth than income explains

    public const UNDERSTATED = 'understated';      // less wealth than expected: expenses or losses missing

    public const NO_BASELINE = 'no_baseline';      // no previous net wealth to start from

    public function __construct(private readonly array $config) {}

    /**
     * @param  array  $data  ['receipts' => [...], 'expenses' => [...], 'liabilities' => [...], 'assets' => [...]]
     * @param  float|null  $previous  Net wealth at the end of the previous income year, if known.
     */
    public function reconcile(array $data, ?float $previous): array
    {
        $totals = [];
        foreach (array_keys($this->config['groups']) as $group) {
            $totals[$group] = $this->sum($group, $data[$group] ?? []);
        }

        $netWealth = $totals['assets'] - $totals['liabilities'];
        $expected = $previous === null ? null : $previous + $totals['receipts'] - $totals['expenses'];
        $gap = $expected === null ? null : $netWealth - $expected;
        $tolerance = max((float) $this->config['tolerance']['min'], $totals['receipts'] * (float) $this->config['tolerance']['share_of_sources']);

        $status = match (true) {
            $gap === null => self::NO_BASELINE,
            abs($gap) <= $tolerance => self::BALANCED,
            $gap > 0 => self::UNEXPLAINED,
            default => self::UNDERSTATED,
        };

        return [
            'totals' => $totals,
            'previous_net_wealth' => $previous,
            'net_wealth' => $netWealth,
            'expected_net_wealth' => $expected,
            'gross_wealth' => $netWealth + $totals['liabilities'],
            'change' => $previous === null ? null : $netWealth - $previous,
            'gap' => $gap,
            'tolerance' => round($tolerance),
            'status' => $status,
        ];
    }

    /** Only the lines the config knows count; unknown keys are ignored. */
    public function sum(string $group, array $values): float
    {
        $total = 0.0;
        foreach (array_keys($this->config['groups'][$group]['lines']) as $line) {
            $total += max(0.0, (float) ($values[$line] ?? 0));
        }

        return $total;
    }

    /** Keep only known lines, as whole non-negative taka. */
    public function clean(array $data): array
    {
        $clean = [];
        foreach ($this->config['groups'] as $group => $def) {
            foreach (array_keys($def['lines']) as $line) {
                $clean[$group][$line] = (int) max(0, round((float) ($data[$group][$line] ?? 0)));
            }
        }

        return $clean;
    }

    /** "2026-27" → "2025-26". */
    public static function previousYear(string $year): string
    {
        $start = (int) substr($year, 0, 4) - 1;

        return $start.'-'.substr((string) ($start + 1), -2);
    }

    /** The income year of assessment year "2026-27" ends on 30 June 2026. */
    public static function yearEnd(string $year): string
    {
        return substr($year, 0, 4).'-06-30';
    }

    /** Assessment years a user may start a statement for, newest first. */
    public function availableYears(string $defaultYear): array
    {
        $years = [];
        $last = (int) substr($defaultYear, 0, 4) + (int) $this->config['years_ahead'];
        for ($start = (int) substr($this->config['first_year'], 0, 4); $start <= $last; $start++) {
            $years[] = $start.'-'.substr((string) ($start + 1), -2);
        }

        return array_reverse($years);
    }
}
