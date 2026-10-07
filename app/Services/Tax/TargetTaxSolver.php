<?php

namespace App\Services\Tax;

use App\Support\Lang;
use App\Support\Money;

/**
 * Reverse calculator: "I want to pay ৳X in tax — what gross salary is that,
 * and how does it split into salary components?"
 *
 * Net tax is a non-decreasing function of gross income, so a binary search
 * on gross income finds the smallest salary that produces the target.
 */
final class TargetTaxSolver
{
    public const MODES = ['none', 'custom', 'max'];

    public function __construct(private readonly TaxEngine $engine) {}

    public function solve(array $raw): array
    {
        $e = $this->engine;
        $in = $e->normalize($raw);
        $ctx = $e->context($in);
        $target = max(0.0, round((float) ($raw['target_tax'] ?? 0)));
        $mode = in_array($raw['rebate_mode'] ?? '', self::MODES, true) ? $raw['rebate_mode'] : 'none';
        $investment = max(0.0, (float) ($raw['investment'] ?? 0));
        $eligible = match ($mode) {
            'max' => TaxEngine::INF,
            'custom' => $investment,
            default => 0.0,
        };

        $taxFreeGross = floor($e->grossForTaxable($ctx['threshold'], $ctx));
        $warning = null;

        if ($target <= 0) {
            $gross = $taxFreeGross;
        } else {
            $lo = 0.0;
            $hi = 1.0e10;
            for ($i = 0; $i < 90; $i++) {
                $mid = ($lo + $hi) / 2;
                if ($e->netTax($mid, $eligible, $ctx) >= $target) {
                    $hi = $mid;
                } else {
                    $lo = $mid;
                }
            }
            $gross = ceil($hi);
            // Ceil can overshoot by a taka; step back while the target still holds.
            while ($gross > 0 && $e->netTax($gross - 1, $eligible, $ctx) >= $target) {
                $gross--;
            }
        }

        $core = $e->core($gross, $eligible, $ctx);
        if ($target > 0 && $target < $ctx['min_tax']) {
            $warning = Lang::t('Any income above the tax-free limit pays at least :min (the minimum tax), so a lower tax is not possible. This is the smallest salary that pays tax.',
                ['min' => Money::bdt($ctx['min_tax'])]);
        }

        $components = $this->split($gross, $raw['ratios'] ?? null);

        $curve = [];
        $max = max($gross * 1.8, 1000000);
        for ($i = 0; $i <= 36; $i++) {
            $g = $max * $i / 36;
            $curve[] = ['income' => round($g), 'tax' => round($e->netTax($g, $eligible, $ctx))];
        }

        $rebateNeeded = $mode === 'max' && $ctx['rebate_rate'] > 0 ? ceil($core['rebate_used'] / $ctx['rebate_rate']) : null;

        return [
            'input' => $in + ['target_tax' => $target, 'rebate_mode' => $mode, 'investment' => $investment],
            'gross' => $gross,
            'monthly' => round($gross / 12),
            'taxable' => round($core['taxable']),
            'exemption' => round($core['exemption']),
            'gross_tax' => round($core['gross_tax']),
            'rebate' => round($core['rebate_used']),
            'tax' => round($core['tax']),
            'investment_needed' => $rebateNeeded,
            'tax_free_gross' => $taxFreeGross,
            'warning' => $warning,
            'components' => $components,
            'copy_text' => $this->copyText($components, $gross),
            'curve' => $curve,
        ];
    }

    /**
     * Split a gross salary by component ratios. Basic absorbs rounding so the total is exact.
     */
    public function split(float $gross, ?array $ratios): array
    {
        $defaults = $this->engine->rules()['salary_components'];
        $parts = [];
        $sum = 0.0;
        foreach ($defaults as $key => $def) {
            $ratio = is_array($ratios) && array_key_exists($key, $ratios) ? max(0.0, (float) $ratios[$key]) : (float) $def['ratio'];
            $parts[$key] = ['key' => $key, 'label' => Lang::t($def['label']), 'ratio' => $ratio];
            $sum += $ratio;
        }
        if ($sum <= 0) {
            return $this->split($gross, null);
        }

        $allocated = 0.0;
        foreach ($parts as $key => &$part) {
            $part['ratio'] = $part['ratio'] / $sum;
            if ($key !== 'basic') {
                $part['amount'] = round($gross * $part['ratio']);
                $allocated += $part['amount'];
            }
        }
        unset($part);
        $parts['basic']['amount'] = $gross - $allocated;
        foreach ($parts as &$part) {
            $part['monthly'] = round($part['amount'] / 12);
            $part['ratio'] = round($part['ratio'], 6);
        }
        unset($part);

        return array_values($parts);
    }

    private function copyText(array $components, float $gross): string
    {
        $lines = [];
        foreach ($components as $i => $c) {
            $lines[] = Money::digits((string) ($i + 1)).' '.$c['label'].' '.Lang::t('TK. :amount/-', ['amount' => Money::group($c['amount'])]);
        }
        $lines[] = Lang::t('Total = TK. :amount/-', ['amount' => Money::group($gross)]);

        return implode("\n", $lines);
    }
}
