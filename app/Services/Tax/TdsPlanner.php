<?php

namespace App\Services\Tax;

use App\Support\Lang;
use App\Support\Money;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Monthly TDS plan for one income year (July to June).
 *
 * The year's salary is twelve months of pay and bonuses plus taxable perks.
 * Three outcomes are worked out with the same engine as the calculator:
 *
 *   none     no investment at all
 *   current  the investments the user plans
 *   best     the lowest legal tax: investing enough for the full rebate
 *
 * TDS already deducted is subtracted and the rest is spread over the months
 * still to be paid, according to the chosen strategy:
 *
 *   current      cover the tax as it stands
 *   full_rebate  cover the lowest tax, assuming the investments are made
 *   cap          deduct at most a fixed amount a month; the rest is paid with the return
 */
final class TdsPlanner
{
    public const MONTHS = ['jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar', 'apr', 'may', 'jun'];

    public const STRATEGIES = ['current', 'full_rebate', 'cap'];

    private const NAMES = ['jul' => 'July', 'aug' => 'August', 'sep' => 'September', 'oct' => 'October', 'nov' => 'November', 'dec' => 'December',
        'jan' => 'January', 'feb' => 'February', 'mar' => 'March', 'apr' => 'April', 'may' => 'May', 'jun' => 'June'];

    /** Being behind or ahead by less than this is "on track". */
    private const SLACK = 1000;

    /** Report predictions that still make sense inside the planner. */
    private const REPORT_INSIGHTS = ['slab', 'shield', 'rates', 'mintax', 'wasted', 'roadmap'];

    /**
     * @param  string[]  $perkKeys  Taxable perks the planner accepts (config/salary.php).
     */
    public function __construct(
        private readonly TaxEngine $engine,
        private readonly TaxReport $reports,
        private readonly array $perkKeys = [],
    ) {}

    /** The assessment year whose income year contains $today. */
    public function currentYear(DateTimeImmutable $today): string
    {
        foreach ($this->engine->rules()['years'] as $key => $year) {
            if ($today->format('Y-m-d') <= $year['income_year_end']) {
                return $key;
            }
        }

        return $this->engine->rules()['default_year'];
    }

    /** Months of the income year that have already been paid, as of $today. */
    public function defaultDone(string $yearKey, DateTimeImmutable $today): array
    {
        $done = [];
        foreach ($this->calendar($yearKey) as $key => [$y, $m]) {
            $done[$key] = sprintf('%04d-%02d', $y, $m) < $today->format('Y-m');
        }

        return $done;
    }

    public function plan(array $raw, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today', new DateTimeZone('Asia/Dhaka'));
        $in = $this->engine->normalize($raw);
        $ctx = $this->engine->context($in);
        $months = $this->months($raw['months'] ?? []);
        $perks = $this->perks($raw['perks'] ?? []);
        $strategy = in_array($raw['strategy'] ?? '', self::STRATEGIES, true) ? $raw['strategy'] : 'current';
        $cap = max(0.0, round((float) ($raw['monthly_cap'] ?? 0)));

        $salaryTotal = array_sum(array_column($months, 'salary'));
        $bonusTotal = array_sum(array_column($months, 'bonus'));
        $perkTotal = array_sum($perks);
        $gross = $salaryTotal + $bonusTotal + $perkTotal;
        $deducted = array_sum(array_map(fn ($m) => $m['done'] ? $m['tds'] : 0, $months));

        // The calculator's own report for this salary: investments, rebate plan and predictions.
        // (normalize() already set gross_income to 0, so the planner's figures go first.)
        $report = $this->reports->build([
            'gross_income' => $gross,
            'tds_paid' => $deducted,
            'filing' => 'standard',
        ] + $in, $today);
        $s = $report['summary'];
        $inv = $report['investments'];

        $taxNow = $s['tax_after_rebate'];
        $taxBest = round($this->engine->core($gross, $inv['needed'], $ctx)['tax']);
        $taxNone = round($this->engine->netTax($gross, 0, $ctx));
        $target = $strategy === 'full_rebate' ? min($taxBest, $taxNow) : $taxNow;

        // Spread what is left over the open months that pay a salary (all open months if none do).
        $open = array_keys(array_filter($months, fn ($m) => ! $m['done'] && $m['salary'] + $m['bonus'] > 0));
        if (! $open) {
            $open = array_keys(array_filter($months, fn ($m) => ! $m['done']));
        }
        $suggested = $this->spread(max(0.0, $target - $deducted), $open, $strategy === 'cap' ? $cap : null);
        $plannedFuture = array_sum($suggested);
        $yearEnd = $taxNow - $deducted - $plannedFuture;   // > 0: pay with the return, < 0: refund

        $count = max(1, count($open));
        $range = [
            'low' => $open ? ceil(max(0.0, $taxBest - $deducted) / $count) : 0.0,
            'high' => $open ? ceil(max(0.0, $taxNone - $deducted) / $count) : 0.0,
        ];

        // The pace line: tax due by each month in proportion to pay received so far.
        $rows = [];
        $cumIncome = $cumPlanned = $neededByNow = 0.0;
        $pay = $salaryTotal + $bonusTotal;
        $calendar = $this->calendar($in['year']);
        foreach ($months as $key => $m) {
            $cumIncome += $m['salary'] + $m['bonus'];
            $planned = $m['done'] ? $m['tds'] : $suggested[$key];
            $cumPlanned += $planned;
            $needed = $pay > 0 ? round($taxNow * $cumIncome / $pay) : 0.0;
            if ($m['done']) {
                $neededByNow = $needed;
            }
            $rows[] = $m + [
                'key' => $key,
                'label' => $this->monthLabel($key, $calendar),
                'suggested' => $suggested[$key],
                'planned' => $planned,
                'cumulative_planned' => $cumPlanned,
                'cumulative_needed' => $needed,
            ];
        }

        $status = match (true) {
            $taxNow - $deducted < -0.5 => 'refund',
            $deducted < $neededByNow - self::SLACK => 'behind',
            $deducted > $neededByNow + self::SLACK => 'ahead',
            default => 'on_track',
        };

        $eligible = $inv['eligible'];
        $impact = fn (float $part) => $part > 0 ? round($taxNow - $this->engine->netTax($gross - $part, $eligible, $ctx)) : 0.0;

        $plan = [
            'input' => $in + ['strategy' => $strategy, 'monthly_cap' => $cap],
            'rules' => ['label' => Lang::label($ctx['year']['label']), 'income_year' => Lang::label($ctx['year']['income_year'])],
            'gross' => $gross,
            'salary_total' => $salaryTotal,
            'bonus_total' => $bonusTotal,
            'perk_total' => $perkTotal,
            'perks' => $perks,
            'taxable' => $s['taxable'],
            'rebate' => $s['rebate'],
            'outcomes' => [
                'none' => $taxNone,
                'current' => $taxNow,
                'best' => $taxBest,
                'saving' => max(0.0, $taxNow - $taxBest),
                'early_filing' => round(abs($this->engine->filingAdjustment($taxBest, 'early'))),
            ],
            'investments' => [
                'items' => $inv['items'],
                'invested' => $inv['invested'],
                'eligible' => $eligible,
                'needed' => $inv['needed'],
                'gap' => $inv['gap'],
                'plan' => $inv['plan'],
                'progress' => $inv['progress'],
            ],
            'liability' => $taxNow,
            'target' => $target,
            'deducted' => $deducted,
            'remaining' => $taxNow - $deducted,
            'needed_by_now' => $neededByNow,
            'open_months' => count($open),
            'next_monthly' => $open ? $suggested[$open[0]] : 0.0,
            'range' => $range,
            'year_end' => round($yearEnd),
            'status' => $status,
            'impact' => ['bonuses' => $impact($bonusTotal), 'perks' => $impact($perkTotal)],
            'months' => $rows,
        ];
        $plan['insights'] = array_merge(
            $this->insights($plan, $ctx, $raw['investments'] ?? [], $today),
            array_values(array_filter($report['predictions'], fn ($p) => in_array($p['key'], self::REPORT_INSIGHTS, true))),
        );
        $plan['hr_text'] = $this->hrText($plan, $ctx, $open, $suggested, $calendar);

        return $plan;
    }

    /** Even split, floored; the last month absorbs rounding. With a cap, no month goes above it. */
    private function spread(float $amount, array $open, ?float $cap): array
    {
        $out = array_fill_keys(self::MONTHS, 0.0);
        if (! $open || $amount <= 0) {
            return $out;
        }
        $each = floor($amount / count($open));
        if ($cap !== null && $each >= $cap) {
            foreach ($open as $key) {
                $out[$key] = $cap;
            }

            return $out;
        }
        foreach ($open as $key) {
            $out[$key] = $each;
        }
        $out[end($open)] += $amount - $each * count($open);

        return $out;
    }

    /** Planner-specific advice, worked out from the plan. Each item: key, icon, tone, title, text, optional action. */
    private function insights(array $p, array $ctx, array $investments, DateTimeImmutable $today): array
    {
        $out = [];
        $add = function (string $key, string $icon, string $tone, string $title, string $text, array $vars = [], ?array $action = null) use (&$out) {
            $out[] = ['key' => $key, 'icon' => $icon, 'tone' => $tone, 'title' => Lang::t($title, $vars), 'text' => Lang::t($text, $vars), 'action' => $action];
        };
        $bdt = fn (float $v) => Money::bdt($v);
        $o = $p['outcomes'];
        $inv = $p['investments'];

        if ($o['saving'] > 0.5 && $inv['gap'] > 0) {
            $ideas = implode(', ', array_map(fn ($x) => $x['label'].' '.$bdt($x['add']), $inv['plan']));
            $add('lowest', 'target', 'good', 'Lowest legal tax: :best',
                'Invest :gap more in eligible schemes and the year’s tax falls from :now to :best, so monthly TDS can drop to about :low. One way: :ideas.',
                ['best' => $bdt($o['best']), 'gap' => $bdt($inv['gap']), 'now' => $bdt($o['current']), 'low' => $bdt($p['range']['low']), 'ideas' => $ideas],
                ['type' => 'apply_plan', 'label' => Lang::t('Add these to my plan'), 'plan' => $inv['plan']]);
        } elseif ($inv['needed'] > 0 && $inv['gap'] <= 0) {
            $add('lowest', 'check', 'good', 'You already pay the lowest legal tax',
                'Your planned investments earn the full rebate, so :best is as low as this salary’s tax goes.', ['best' => $bdt($o['best'])]);
        }

        $employerPf = $p['perks']['employer_pf'] ?? 0;
        $pfCounted = (float) ($investments['provident_fund'] ?? 0);
        if ($employerPf > 0 && $pfCounted < $employerPf) {
            $add('pf', 'coins', 'info', 'Your provident fund counts twice',
                'The employer’s :pf PF contribution is taxed as salary, but it also counts as an investment for the rebate. Add it, with your own contribution, under Provident fund.',
                ['pf' => $bdt($employerPf)],
                ['type' => 'add_investment', 'label' => Lang::t('Add :amount to Provident fund', ['amount' => $bdt($employerPf)]), 'key' => 'provident_fund', 'amount' => $employerPf]);
        }

        if ($p['input']['strategy'] !== 'current' || $inv['invested'] > 0) {
            $add('declare', 'receipt', 'info', 'Tell HR about your investments',
                'Employers must deduct TDS each month based on your estimated tax for the year (section 86). Give HR your planned investments with proof, and they can deduct for the lower tax.');
        }

        if ($p['input']['strategy'] === 'cap' && $p['year_end'] > 0.5) {
            $add('year_end', 'calendar', 'watch', ':amount to pay with your return',
                'With TDS capped at :cap a month, :amount is left for when you file. Keep it aside: it is due with the return. Your employer may still have to deduct more if your declared estimate needs it.',
                ['amount' => $bdt($p['year_end']), 'cap' => $bdt($p['input']['monthly_cap'])]);
        }

        if ($p['impact']['bonuses'] > 0) {
            $add('bonus_tax', 'gift', 'info', 'Bonuses add :tax in tax',
                'Your :bonus in bonuses raise the year’s tax by :tax. The plan already spreads it over the remaining months.',
                ['tax' => $bdt($p['impact']['bonuses']), 'bonus' => $bdt($p['bonus_total'])]);
        }
        if ($p['impact']['perks'] > 0) {
            $add('perk_tax', 'wallet', 'info', 'Perks and benefits add :tax in tax',
                ':perks of taxable perks count as salary, adding :tax to the year’s tax.',
                ['tax' => $bdt($p['impact']['perks']), 'perks' => $bdt($p['perk_total'])]);
        }

        $endYear = (int) substr($ctx['year']['income_year_end'], 0, 4);
        if ($o['early_filing'] > 0 && $today <= new DateTimeImmutable("{$endYear}-09-30", $today->getTimezone())) {
            $add('early', 'calendar', 'good', 'File by 30 September :year for :amount off',
                'Returns filed between 1 July and 30 September get a 5% rebate on the tax, up to ৳25,000. It lowers what you settle when you file, not the monthly TDS.',
                ['year' => Money::digits((string) $endYear), 'amount' => $bdt($o['early_filing'])]);
        }

        return $out;
    }

    private function hrText(array $p, array $ctx, array $open, array $suggested, array $calendar): string
    {
        $vars = [
            'year' => Lang::label($ctx['year']['label']),
            'gross' => Money::bdt($p['gross']),
            'tax' => Money::bdt($p['target']),
            'deducted' => Money::bdt($p['deducted']),
        ];

        if ($p['target'] - $p['deducted'] <= 0 || ! $open) {
            return Lang::t('Dear HR, my projected salary for :year is :gross, with income tax of :tax. :deducted has already been deducted, which covers it. Please do not deduct further TDS this year.', $vars);
        }

        $vars += [
            'monthly' => Money::bdt($suggested[$open[0]]),
            'from' => $this->monthLabel($open[0], $calendar),
            'to' => $this->monthLabel(end($open), $calendar),
            'count' => Money::digits((string) count($open)),
        ];

        if ($p['input']['strategy'] === 'full_rebate' && $p['investments']['needed'] > 0) {
            return Lang::t('Dear HR, my projected salary for :year is :gross. I will invest :invest in eligible schemes this year and will share the proof, which brings my income tax to :tax. :deducted has been deducted so far. Please deduct :monthly TDS each month from :from to :to (:count months).',
                $vars + ['invest' => Money::bdt(max($p['investments']['needed'], $p['investments']['eligible']))]);
        }

        return Lang::t('Dear HR, my projected salary for :year is :gross, with income tax of :tax. :deducted has been deducted so far. Please deduct :monthly TDS each month from :from to :to (:count months) so the full tax is covered.', $vars);
    }

    /** 12 clean months, July first. */
    private function months(array $raw): array
    {
        $out = [];
        foreach (self::MONTHS as $i => $key) {
            $m = $raw[$key] ?? $raw[$i] ?? [];
            $out[$key] = [
                'salary' => max(0.0, round((float) ($m['salary'] ?? 0))),
                'bonus' => max(0.0, round((float) ($m['bonus'] ?? 0))),
                'tds' => max(0.0, round((float) ($m['tds'] ?? 0))),
                'done' => filter_var($m['done'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $out;
    }

    /** Known perks only, as whole non-negative taka for the year. */
    private function perks(array $raw): array
    {
        $out = [];
        foreach ($this->perkKeys as $key) {
            $out[$key] = max(0.0, round((float) ($raw[$key] ?? 0)));
        }

        return $out;
    }

    private function monthLabel(string $key, array $calendar): string
    {
        return Lang::label(':month :year', ['month' => Lang::t(self::NAMES[$key]), 'year' => $calendar[$key][0]]);
    }

    /** Calendar year and month of each income-year month, from the year's income_year_end. */
    private function calendar(string $yearKey): array
    {
        $endYear = (int) substr($this->engine->year($yearKey)['income_year_end'], 0, 4);
        $out = [];
        foreach (self::MONTHS as $i => $key) {
            $month = ($i + 6) % 12 + 1;   // jul → 7 … jun → 6
            $out[$key] = [$month >= 7 ? $endYear - 1 : $endYear, $month];
        }

        return $out;
    }
}
