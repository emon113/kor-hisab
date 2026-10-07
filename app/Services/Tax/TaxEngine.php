<?php

namespace App\Services\Tax;

use InvalidArgumentException;

/**
 * Pure tax arithmetic for a salaried individual in Bangladesh.
 *
 * Framework-free on purpose: it receives the rules array (config/tax.php) and
 * plain numbers, so it is trivially unit-testable and reusable from
 * controllers, seeders and console commands alike.
 */
final class TaxEngine
{
    public const INF = PHP_FLOAT_MAX;

    public function __construct(private readonly array $rules) {}

    public function rules(): array
    {
        return $this->rules;
    }

    public function year(string $key): array
    {
        return $this->rules['years'][$key]
            ?? throw new InvalidArgumentException("Unknown tax year [{$key}].");
    }

    /**
     * Clean raw user input into the canonical shape every other method expects.
     */
    public function normalize(array $input): array
    {
        $year = array_key_exists($input['year'] ?? '', $this->rules['years'])
            ? $input['year'] : $this->rules['default_year'];
        $category = array_key_exists($input['category'] ?? '', $this->rules['categories'])
            ? $input['category'] : 'general';
        $filing = array_key_exists($input['filing'] ?? '', $this->rules['filing_periods'])
            ? $input['filing'] : 'standard';

        $investments = [];
        foreach (array_keys($this->rules['instruments']) as $key) {
            $investments[$key] = max(0.0, round((float) ($input['investments'][$key] ?? 0)));
        }

        return [
            'year' => $year,
            'category' => $category,
            'gross_income' => max(0.0, round((float) ($input['gross_income'] ?? 0))),
            'investments' => $investments,
            'tds_paid' => max(0.0, round((float) ($input['tds_paid'] ?? 0))),
            'filing' => $filing,
            'new_taxpayer' => filter_var($input['new_taxpayer'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'disabled_children' => max(0, min(10, (int) ($input['disabled_children'] ?? 0))),
        ];
    }

    /**
     * Resolve the rules of one tax year for one taxpayer into a flat context.
     */
    public function context(array $input): array
    {
        $year = $this->year($input['year']);
        $threshold = $year['thresholds'][$input['category']]
            + ($input['disabled_children'] ?? 0) * $this->rules['extra_per_disabled_child'];

        $bands = [['from' => 0.0, 'to' => (float) $threshold, 'rate' => 0.0]];
        $from = (float) $threshold;
        foreach ($year['slabs'] as [$width, $rate]) {
            $to = $width === null ? self::INF : $from + $width;
            $bands[] = ['from' => $from, 'to' => $to, 'rate' => (float) $rate];
            if ($width === null) {
                break;
            }
            $from = $to;
        }

        return [
            'year_key' => $input['year'],
            'year' => $year,
            'threshold' => (float) $threshold,
            'bands' => $bands,
            'ex_fraction' => (float) $year['salary_exemption']['fraction'],
            'ex_cap' => (float) $year['salary_exemption']['cap'],
            'rebate_rate' => (float) $year['rebate']['rate'],
            'rebate_pct' => (float) $year['rebate']['income_pct'],
            'rebate_cap' => (float) $year['rebate']['cap'],
            'min_tax' => (float) (($input['new_taxpayer'] ?? false) ? $year['minimum_tax_new'] : $year['minimum_tax']),
        ];
    }

    /**
     * Per-instrument eligible amounts (each capped by law) and their total.
     */
    public function eligibleInvestments(array $investments): array
    {
        $items = [];
        $total = 0.0;
        foreach ($this->rules['instruments'] as $key => $def) {
            $amount = (float) ($investments[$key] ?? 0);
            $cap = $def['cap'];
            $eligible = $cap === null ? $amount : min($amount, (float) $cap);
            $items[$key] = [
                'key' => $key,
                'label' => $def['label'],
                'hint' => $def['hint'] ?? '',
                'amount' => $amount,
                'cap' => $cap,
                'eligible' => $eligible,
                'room' => $cap === null ? null : max(0.0, $cap - $amount),
                'over' => $cap === null ? 0.0 : max(0.0, $amount - $cap),
            ];
            $total += $eligible;
        }

        return ['items' => $items, 'total' => $total, 'invested' => array_sum(array_column($items, 'amount'))];
    }

    public function exemption(float $gross, array $ctx): float
    {
        return min($gross * $ctx['ex_fraction'], $ctx['ex_cap']);
    }

    public function taxable(float $gross, array $ctx): float
    {
        return max(0.0, $gross - $this->exemption($gross, $ctx));
    }

    /**
     * Inverse of taxable(): the gross salary that yields a given taxable income.
     */
    public function grossForTaxable(float $taxable, array $ctx): float
    {
        $f = $ctx['ex_fraction'];
        if ($f <= 0) {
            return $taxable;
        }
        $taxableAtCap = $ctx['ex_cap'] / $f - $ctx['ex_cap'];

        return $taxable <= $taxableAtCap ? $taxable / (1 - $f) : $taxable + $ctx['ex_cap'];
    }

    /**
     * Slab-by-slab split of a taxable income.
     */
    public function slabs(float $taxable, array $ctx): array
    {
        $rows = [];
        foreach ($ctx['bands'] as $i => $band) {
            $amount = max(0.0, min($taxable, $band['to']) - $band['from']);
            $rows[] = $band + ['index' => $i, 'amount' => $amount, 'tax' => $amount * $band['rate']];
        }

        return $rows;
    }

    public function grossTax(float $taxable, array $ctx): float
    {
        $tax = 0.0;
        foreach ($ctx['bands'] as $band) {
            $tax += max(0.0, min($taxable, $band['to']) - $band['from']) * $band['rate'];
        }

        return $tax;
    }

    /**
     * Index of the highest band the taxable income reaches (0 = still tax-free).
     */
    public function topBandIndex(float $taxable, array $ctx): int
    {
        $index = 0;
        foreach ($ctx['bands'] as $i => $band) {
            if ($taxable > $band['from'] && $band['to'] > $band['from']) {
                $index = $i;
            }
        }

        return $index;
    }

    public function marginalRate(float $taxable, array $ctx): float
    {
        return $ctx['bands'][$this->topBandIndex($taxable, $ctx)]['rate'];
    }

    /**
     * The full liability chain for one gross income and one eligible investment amount.
     * Filing-period adjustment and TDS are applied separately (see liability()).
     */
    public function core(float $gross, float $eligible, array $ctx): array
    {
        $exemption = $this->exemption($gross, $ctx);
        $taxable = max(0.0, $gross - $exemption);
        $grossTax = $this->grossTax($taxable, $ctx);

        $rebateLimit = min($taxable * $ctx['rebate_pct'], $ctx['rebate_cap']);
        $rebateOnInvestment = $eligible * $ctx['rebate_rate'];
        $rebate = min($rebateLimit, $rebateOnInvestment);

        $minTax = $taxable > $ctx['threshold'] ? $ctx['min_tax'] : 0.0;
        $afterRebate = max($grossTax - $rebate, $minTax);

        // The rebate can never push tax below the minimum, so part of it may be "wasted".
        $usefulMax = min($rebateLimit, max(0.0, $grossTax - $minTax));
        $needed = $ctx['rebate_rate'] > 0 ? $usefulMax / $ctx['rebate_rate'] : 0.0;

        return [
            'gross' => $gross,
            'exemption' => $exemption,
            'taxable' => $taxable,
            'gross_tax' => $grossTax,
            'rebate_limit' => $rebateLimit,
            'rebate_on_investment' => $rebateOnInvestment,
            'rebate' => $rebate,
            'rebate_used' => max(0.0, min($rebate, $grossTax - $afterRebate)),
            'rebate_useful_max' => $usefulMax,
            'investment_needed' => $needed,
            'min_tax' => $minTax,
            'min_tax_applied' => $minTax > 0 && $grossTax - $rebate < $minTax,
            'tax' => $afterRebate,
        ];
    }

    public function netTax(float $gross, float $eligible, array $ctx): float
    {
        return $this->core($gross, $eligible, $ctx)['tax'];
    }

    public function filingAdjustment(float $tax, string $period): float
    {
        $rule = $this->rules['filing_periods'][$period] ?? null;
        if (! $rule || $tax <= 0 || $rule['rate'] == 0) {
            return 0.0;
        }
        if ($rule['rate'] < 0) {
            return -min($tax * abs($rule['rate']), $rule['max'] ?? self::INF);
        }

        return max($tax * $rule['rate'], $rule['min'] ?? 0);
    }
}
