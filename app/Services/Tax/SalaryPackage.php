<?php

namespace App\Services\Tax;

/**
 * A salary as offer letters and HR quote it: a monthly gross, the share of it
 * that is basic, festival bonuses counted in months of basic or gross, other
 * yearly cash and the employer's provident fund contribution.
 *
 * Employer PF is salary for tax (Schedule 1 of the return) but not cash in
 * hand, so both totals are returned.
 */
final class SalaryPackage
{
    public const BONUS_BASES = ['basic', 'gross'];

    public static function annual(array $p): array
    {
        $monthly = max(0.0, round((float) ($p['monthly'] ?? 0)));
        $basicPct = min(100.0, max(1.0, (float) ($p['basic_pct'] ?? 60)));
        $basic = round($monthly * $basicPct / 100);
        $bonusCount = min(12.0, max(0.0, (float) ($p['bonus_count'] ?? 0)));
        $bonusBase = in_array($p['bonus_base'] ?? '', self::BONUS_BASES, true) ? $p['bonus_base'] : 'basic';
        $bonusEach = $bonusBase === 'basic' ? $basic : $monthly;
        $other = max(0.0, round((float) ($p['other_annual'] ?? 0)));
        $pf = max(0.0, round((float) ($p['employer_pf'] ?? 0))) * 12;
        $cash = $monthly * 12 + round($bonusCount * $bonusEach) + $other;

        return [
            'monthly' => $monthly,
            'basic_monthly' => $basic,
            'allowances_monthly' => $monthly - $basic,
            'bonus_each' => $bonusEach,
            'bonuses' => round($bonusCount * $bonusEach),
            'other' => $other,
            'employer_pf' => $pf,
            'cash' => $cash,          // what reaches the bank account, before tax
            'gross' => $cash + $pf,   // what the return counts as salary
        ];
    }
}
