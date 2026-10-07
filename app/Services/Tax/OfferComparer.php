<?php

namespace App\Services\Tax;

/**
 * Compares two or three salary offers after tax.
 *
 * Offers in Bangladesh are quoted as a monthly gross with festival bonuses
 * (usually counted in months of basic) and sometimes an employer provident
 * fund contribution. Employer PF counts as salary for tax (Schedule 1) but
 * is not cash in hand, so offers are compared on monthly take-home and on
 * total yearly value (take-home plus PF savings).
 */
final class OfferComparer
{
    public const MODES = ['none', 'same', 'max'];

    public const BONUS_BASES = ['basic', 'gross'];

    public function __construct(private readonly TaxEngine $engine) {}

    public function compare(array $raw): array
    {
        $in = $this->engine->normalize($raw);
        $ctx = $this->engine->context($in);
        $mode = in_array($raw['investment_mode'] ?? '', self::MODES, true) ? $raw['investment_mode'] : 'none';
        $investment = max(0.0, round((float) ($raw['investment'] ?? 0)));

        $offers = [];
        foreach (array_values($raw['offers'] ?? []) as $i => $offer) {
            $offers[] = $this->offer($offer, $i, $ctx, $mode, $investment);
        }

        $best = null;
        foreach ($offers as $i => $o) {
            if ($best === null || $o['take_home_monthly'] > $offers[$best]['take_home_monthly']) {
                $best = $i;
            }
        }

        // Differences against the first offer, the one you have (or are measuring from).
        $base = $offers[0] ?? null;
        foreach ($offers as $i => &$o) {
            $o['best'] = $i === $best;
            $o['vs_first'] = $base === null || $i === 0 ? null : [
                'take_home_monthly' => $o['take_home_monthly'] - $base['take_home_monthly'],
                'tax' => $o['tax'] - $base['tax'],
                'total_value' => $o['total_value'] - $base['total_value'],
                'gross_raise' => $base['monthly'] > 0 ? $o['monthly'] / $base['monthly'] - 1 : null,
                'take_home_raise' => $base['take_home_monthly'] > 0 ? $o['take_home_monthly'] / $base['take_home_monthly'] - 1 : null,
            ];
        }
        unset($o);

        return [
            'input' => $in + ['investment_mode' => $mode, 'investment' => $investment],
            'offers' => $offers,
            'best' => $best,
        ];
    }

    private function offer(array $offer, int $index, array $ctx, string $mode, float $investment): array
    {
        $monthly = max(0.0, round((float) ($offer['monthly'] ?? 0)));
        $basicPct = min(100.0, max(1.0, (float) ($offer['basic_pct'] ?? 60)));
        $basic = round($monthly * $basicPct / 100);
        $bonusCount = min(12.0, max(0.0, (float) ($offer['bonus_count'] ?? 0)));
        $bonusBase = in_array($offer['bonus_base'] ?? '', self::BONUS_BASES, true) ? $offer['bonus_base'] : 'basic';
        $bonuses = round($bonusCount * ($bonusBase === 'basic' ? $basic : $monthly));
        $other = max(0.0, round((float) ($offer['other_annual'] ?? 0)));
        $pf = max(0.0, round((float) ($offer['employer_pf'] ?? 0))) * 12;

        $cash = $monthly * 12 + $bonuses + $other;
        $gross = $cash + $pf;   // what Schedule 1 counts as salary

        $needed = ceil($this->engine->core($gross, 0, $ctx)['investment_needed']);
        $eligible = match ($mode) {
            'same' => $investment,
            'max' => $needed,
            default => 0.0,
        };
        $core = $this->engine->core($gross, $eligible, $ctx);
        $tax = round($core['tax']);

        return [
            'index' => $index,
            'name' => trim((string) ($offer['name'] ?? '')) ?: chr(65 + $index),
            'monthly' => $monthly,
            'basic_monthly' => $basic,
            'allowances_monthly' => $monthly - $basic,
            'bonuses' => $bonuses,
            'other' => $other,
            'employer_pf' => $pf,
            'gross' => $gross,
            'taxable' => round($core['taxable']),
            'investment' => $eligible,
            'investment_needed' => $needed,
            'rebate' => round($core['rebate_used']),
            'tax' => $tax,
            'take_home_annual' => $cash - $tax,
            'take_home_monthly' => round(($cash - $tax) / 12),
            'total_value' => $cash - $tax + $pf,
            'effective_rate' => $gross > 0 ? round($tax / $gross, 6) : 0.0,
            'marginal_rate' => $this->engine->marginalRate($core['taxable'], $ctx),
        ];
    }
}
