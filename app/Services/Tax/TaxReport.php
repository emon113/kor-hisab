<?php

namespace App\Services\Tax;

use App\Support\Money;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Turns one set of inputs into everything the UI shows: summary figures,
 * slab breakdown, rebate optimiser, plain-language predictions, raise
 * scenarios and the data behind every chart.
 */
final class TaxReport
{
    private const RAISES = [0, 0.05, 0.10, 0.15, 0.20, 0.25, 0.30, 0.40, 0.50];

    public function __construct(private readonly TaxEngine $engine) {}

    public function build(array $raw, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today', new DateTimeZone('Asia/Dhaka'));
        $e = $this->engine;
        $in = $e->normalize($raw);
        $ctx = $e->context($in);
        $inv = $e->eligibleInvestments($in['investments']);
        $gross = $in['gross_income'];
        $core = $e->core($gross, $inv['total'], $ctx);

        $filingAdj = $e->filingAdjustment($core['tax'], $in['filing']);
        $liability = $core['tax'] + $filingAdj;
        $payable = $liability - $in['tds_paid'];
        $top = $e->topBandIndex($core['taxable'], $ctx);

        $summary = [
            'gross' => $gross,
            'exemption' => $core['exemption'],
            'taxable' => $core['taxable'],
            'threshold' => $ctx['threshold'],
            'gross_tax' => $core['gross_tax'],
            'rebate' => $core['rebate_used'],
            'rebate_claimed' => $core['rebate'],
            'min_tax' => $core['min_tax'],
            'min_tax_applied' => $core['min_tax_applied'],
            'tax_after_rebate' => $core['tax'],
            'filing_adjustment' => $filingAdj,
            'liability' => $liability,
            'tds_paid' => $in['tds_paid'],
            'payable' => $payable,
            'effective_rate' => $gross > 0 ? $liability / $gross : 0.0,
            'marginal_rate' => $ctx['bands'][$top]['rate'],
            'monthly_tax' => $liability / 12,
            'take_home' => $gross - $liability,
            'take_home_monthly' => ($gross - $liability) / 12,
        ];

        $nextSlab = $this->nextSlab($gross, $core, $top, $ctx);
        $investments = $this->investments($inv, $core, $ctx, $gross);
        $scenarios = $this->scenarios($gross, $inv['total'], $top, $ctx);

        $report = [
            'input' => $in,
            'rules' => [
                'year' => $in['year'],
                'label' => $ctx['year']['label'],
                'income_year' => $ctx['year']['income_year'],
                'projected' => (bool) $ctx['year']['projected'],
                'category' => $this->engine->rules()['categories'][$in['category']],
                'threshold' => $ctx['threshold'],
                'exemption_cap' => $ctx['ex_cap'],
                'rebate_rate' => $ctx['rebate_rate'],
                'rebate_pct' => $ctx['rebate_pct'],
                'rebate_cap' => $ctx['rebate_cap'],
                'minimum_tax' => $ctx['min_tax'],
            ],
            'summary' => $summary,
            'slabs' => $this->slabRows($core['taxable'], $top, $ctx),
            'next_slab' => $nextSlab,
            'investments' => $investments,
            'scenarios' => $scenarios,
            'charts' => [
                'income_split' => $this->incomeSplit($core, $ctx),
                'waterfall' => $this->waterfall($summary, $core),
                'rate_curve' => $this->rateCurve($gross, $inv['total'], $ctx),
                'heatmap' => $this->heatmap($gross, $inv['total'], $core, $ctx),
                'future' => $this->futureYears($in, $inv['total']),
                'categories' => $this->categoryComparison($in, $inv['total']),
                'paycheck' => [
                    ['label' => 'Take-home after saving', 'value' => max(0.0, ($gross - $liability - $inv['invested']) / 12)],
                    ['label' => 'Investments', 'value' => $inv['invested'] / 12],
                    ['label' => 'Tax', 'value' => $liability / 12],
                ],
            ],
        ];
        $report['predictions'] = $this->predictions($in, $ctx, $core, $summary, $nextSlab, $investments, $report['charts']['future'], $today);

        return $this->roundDeep($report);
    }

    // ------------------------------------------------------------------ sections

    private function slabRows(float $taxable, int $top, array $ctx): array
    {
        $rows = [];
        foreach ($this->engine->slabs($taxable, $ctx) as $i => $row) {
            $open = $row['to'] >= TaxEngine::INF;
            $width = $open ? null : $row['to'] - $row['from'];
            $label = match (true) {
                $i === 0 => 'First '.Money::bdt($width),
                $open => 'Remaining income',
                default => 'Next '.Money::bdt($width),
            };
            $rows[] = [
                'label' => $label,
                'rate' => $row['rate'],
                'from' => $row['from'],
                'to' => $open ? null : $row['to'],
                'width' => $width,
                'amount' => $row['amount'],
                'tax' => $row['tax'],
                'fill' => $width ? min(1, $row['amount'] / $width) : ($row['amount'] > 0 ? 1 : 0),
                'is_top' => $i === $top,
            ];
        }

        return $rows;
    }

    private function nextSlab(float $gross, array $core, int $top, array $ctx): array
    {
        $bands = $ctx['bands'];
        $band = $bands[$top];
        $atTop = $top === array_key_last($bands);
        $raise = null;
        if (! $atTop) {
            $grossNeeded = $this->engine->grossForTaxable($band['to'], $ctx);
            $raise = max(0, ceil($grossNeeded - $gross));
        }

        return [
            'at_top' => $atTop,
            'rate_now' => $band['rate'],
            'rate_next' => $atTop ? null : $bands[$top + 1]['rate'],
            'raise_needed' => $raise,
            'raise_monthly' => $raise === null ? null : $raise / 12,
            'room_in_slab' => $atTop ? null : max(0.0, $band['to'] - $core['taxable']),
            'slab_progress' => $atTop ? 1 : (($band['to'] - $band['from']) > 0
                ? max(0.0, min(1.0, ($core['taxable'] - $band['from']) / ($band['to'] - $band['from']))) : 0),
        ];
    }

    private function investments(array $inv, array $core, array $ctx, float $gross): array
    {
        $needed = ceil($core['investment_needed']);
        $eligible = $inv['total'];
        $gap = max(0.0, $needed - $eligible);
        $overCaps = array_sum(array_column($inv['items'], 'over'));

        // Example plan: fill capped instruments first, the uncapped one last.
        $plan = [];
        $left = $gap;
        foreach ($inv['items'] as $item) {
            if ($left <= 0) {
                break;
            }
            $room = $item['room'] ?? TaxEngine::INF;
            $add = min($left, $room);
            if ($add > 0) {
                $plan[] = ['key' => $item['key'], 'label' => $item['label'], 'add' => $add];
                $left -= $add;
            }
        }

        $max = max($needed * 1.5, $eligible * 1.15, 50000);
        $curve = [];
        for ($i = 0; $i <= 20; $i++) {
            $amount = round($max * $i / 20, -3);
            $c = $this->engine->core($gross, $amount, $ctx);
            $curve[] = ['investment' => $amount, 'rebate' => $c['rebate_used'], 'tax' => $c['tax']];
        }

        return [
            'items' => array_values($inv['items']),
            'invested' => $inv['invested'],
            'eligible' => $eligible,
            'needed' => $needed,
            'gap' => $gap,
            'progress' => $needed > 0 ? min(1, $eligible / $needed) : 1,
            'rebate' => $core['rebate_used'],
            'rebate_max' => $core['rebate_useful_max'],
            'extra_saving' => max(0.0, $core['rebate_useful_max'] - $core['rebate_used']),
            'wasted' => ($needed > 0 ? max(0.0, $eligible - $needed) : ($core['taxable'] > $ctx['threshold'] ? $eligible : 0.0)) + $overCaps,
            'plan' => $plan,
            'curve' => $curve,
        ];
    }

    private function scenarios(float $gross, float $eligible, int $baseTop, array $ctx): array
    {
        $base = $this->engine->core($gross, $eligible, $ctx);
        $rows = [];
        foreach (self::RAISES as $raise) {
            $g = $gross * (1 + $raise);
            $c = $this->engine->core($g, $eligible, $ctx);
            $topIndex = $this->engine->topBandIndex($c['taxable'], $ctx);
            $gain = ($g - $c['tax']) - ($gross - $base['tax']);
            $rows[] = [
                'raise' => $raise,
                'gross' => $g,
                'taxable' => $c['taxable'],
                'tax' => $c['tax'],
                'extra_tax' => $c['tax'] - $base['tax'],
                'top_rate' => $ctx['bands'][$topIndex]['rate'],
                'slab_changed' => $topIndex !== $baseTop,
                'take_home_gain' => $gain,
                'keep_pct' => $g > $gross ? $gain / ($g - $gross) : null,
                'monthly_take_home' => ($g - $c['tax']) / 12,
                'investment_needed' => ceil($c['investment_needed']),
            ];
        }

        return $rows;
    }

    private function incomeSplit(array $core, array $ctx): array
    {
        $parts = [['label' => 'Tax-free salary (⅓ rule)', 'value' => $core['exemption'], 'rate' => null]];
        foreach ($this->engine->slabs($core['taxable'], $ctx) as $i => $row) {
            $parts[] = [
                'label' => $i === 0 ? 'Tax-free slab' : Money::pct($row['rate'], 0).' slab',
                'value' => $row['amount'],
                'rate' => $row['rate'],
            ];
        }

        return $parts;
    }

    private function waterfall(array $s, array $core): array
    {
        $steps = [['label' => 'Gross tax', 'value' => $s['gross_tax'], 'kind' => 'up']];
        if ($s['rebate'] > 0) {
            $steps[] = ['label' => 'Rebate', 'value' => -$s['rebate'], 'kind' => 'down'];
        }
        $topUp = $s['tax_after_rebate'] - ($s['gross_tax'] - $s['rebate']);
        if ($topUp > 0.5) {
            $steps[] = ['label' => 'Minimum tax top-up', 'value' => $topUp, 'kind' => 'up'];
        }
        if (abs($s['filing_adjustment']) > 0) {
            $steps[] = ['label' => $s['filing_adjustment'] < 0 ? 'Early filing' : 'Late filing', 'value' => $s['filing_adjustment'], 'kind' => $s['filing_adjustment'] < 0 ? 'down' : 'up'];
        }
        $steps[] = ['label' => 'Liability', 'value' => $s['liability'], 'kind' => 'total'];
        if ($s['tds_paid'] > 0) {
            $steps[] = ['label' => 'TDS paid', 'value' => -$s['tds_paid'], 'kind' => 'down'];
            $steps[] = ['label' => $s['payable'] >= 0 ? 'To pay' : 'Refund', 'value' => $s['payable'], 'kind' => 'total'];
        }

        return $steps;
    }

    private function rateCurve(float $gross, float $eligible, array $ctx): array
    {
        $max = ceil(max(3600000, $gross * 2) / 100000) * 100000;
        $points = [];
        for ($i = 1; $i <= 40; $i++) {
            $g = $max * $i / 40;
            $c = $this->engine->core($g, $eligible, $ctx);
            $points[] = [
                'income' => $g,
                'effective' => $c['tax'] / $g,
                'marginal' => $this->engine->marginalRate($c['taxable'], $ctx),
                'tax' => $c['tax'],
            ];
        }
        $you = $this->engine->core($gross, $eligible, $ctx);

        return ['points' => $points, 'you' => ['income' => $gross, 'effective' => $gross > 0 ? $you['tax'] / $gross : 0, 'tax' => $you['tax']]];
    }

    private function heatmap(float $gross, float $eligible, array $core, array $ctx): array
    {
        $base = $gross > 0 ? $gross : 1000000;
        $incomes = [];
        for ($i = 0; $i < 13; $i++) {
            $incomes[] = round($base * (0.4 + 0.1 * $i));
        }
        $topNeed = $this->engine->core(end($incomes), 0, $ctx)['investment_needed'];
        $colMax = max(50000, ceil(max($core['investment_needed'] * 1.6, $eligible * 1.2, min($topNeed, $core['investment_needed'] * 2.5)) / 10000) * 10000);
        $investments = [];
        for ($j = 0; $j <= 10; $j++) {
            $investments[] = round($colMax * $j / 10, -3);
        }
        $values = [];
        $min = TaxEngine::INF;
        $max = 0.0;
        foreach ($incomes as $g) {
            $row = [];
            foreach ($investments as $amount) {
                $t = round($this->engine->netTax($g, $amount, $ctx));
                $row[] = $t;
                $min = min($min, $t);
                $max = max($max, $t);
            }
            $values[] = $row;
        }
        $userCol = 0;
        foreach ($investments as $j => $amount) {
            if (abs($amount - $eligible) < abs($investments[$userCol] - $eligible)) {
                $userCol = $j;
            }
        }

        return ['incomes' => $incomes, 'investments' => $investments, 'values' => $values,
            'min' => $min, 'max' => $max, 'user_row' => $gross > 0 ? 6 : null, 'user_col' => $userCol];
    }

    private function futureYears(array $in, float $eligible): array
    {
        $out = [];
        foreach ($this->engine->rules()['years'] as $key => $year) {
            $ctx = $this->engine->context(['year' => $key] + $in);
            $c = $this->engine->core($in['gross_income'], $eligible, $ctx);
            $out[] = ['year' => $key, 'label' => $year['label'], 'projected' => (bool) $year['projected'],
                'threshold' => $ctx['threshold'], 'tax' => $c['tax'], 'current' => $key === $in['year']];
        }

        return $out;
    }

    private function categoryComparison(array $in, float $eligible): array
    {
        $out = [];
        foreach ($this->engine->rules()['categories'] as $key => $label) {
            $ctx = $this->engine->context(['category' => $key] + $in);
            $out[] = ['key' => $key, 'label' => $label, 'threshold' => $ctx['threshold'],
                'tax' => $this->engine->netTax($in['gross_income'], $eligible, $ctx), 'current' => $key === $in['category']];
        }

        return $out;
    }

    // ------------------------------------------------------------------ predictions

    private function predictions(array $in, array $ctx, array $core, array $s, array $next, array $inv, array $future, DateTimeImmutable $today): array
    {
        $e = $this->engine;
        $gross = $in['gross_income'];
        $eligible = $inv['eligible'];
        $tax = $core['tax'];
        $out = [];
        $add = function (string $key, string $icon, string $tone, string $title, string $text) use (&$out) {
            $out[] = compact('key', 'icon', 'tone', 'title', 'text');
        };

        // Next slab
        if ($next['at_top']) {
            $add('slab', 'mountain', 'info', 'You are in the top slab',
                'Every extra taka of taxable income is taxed at '.Money::pct($next['rate_now'], 0).'. The investment rebate is now your main lever.');
        } elseif ($next['rate_now'] == 0) {
            $add('slab', 'sprout', 'good', Money::bdt($next['raise_needed']).' of tax-free headroom',
                'Your income can grow by '.Money::bdt($next['raise_needed']).' before any tax applies. The first slab after that is '.Money::pct($next['rate_next'], 0).'.');
        } else {
            $add('slab', 'trending-up', 'watch', 'Next slab in '.Money::bdt($next['raise_needed']),
                'Your next '.Money::bdt($next['raise_needed']).' of raise (about '.Money::bdt($next['raise_monthly']).' a month) stays in the '
                .Money::pct($next['rate_now'], 0).' slab. Anything beyond that is taxed in the '.Money::pct($next['rate_next'], 0).' slab.');
        }

        // Rebate
        if ($inv['needed'] > 0 && $inv['gap'] > 0) {
            $add('rebate', 'target', 'watch', 'Invest '.Money::bdt($inv['gap']).' more to save '.Money::bdt($inv['extra_saving']),
                'That is a guaranteed '.Money::pct($ctx['rebate_rate'], 0).' return from the rebate alone. You need '.Money::bdt($inv['needed'])
                .' of eligible investment in total and have '.Money::bdt($eligible).'.');
        } elseif ($inv['needed'] > 0) {
            $add('rebate', 'check', 'good', 'Full rebate unlocked',
                'Your '.Money::bdt($eligible).' of eligible investment earns the maximum rebate of '.Money::bdt($inv['rebate_max']).'.');
        } elseif ($core['taxable'] > $ctx['threshold']) {
            $add('rebate', 'alert', 'info', 'Investing will not lower your tax',
                'Your tax is already at the minimum of '.Money::bdt($ctx['min_tax']).', so a rebate has nothing left to reduce.');
        }
        if ($inv['wasted'] > 0 && $core['taxable'] > $ctx['threshold']) {
            $add('wasted', 'alert', 'watch', Money::bdt($inv['wasted']).' of investment earns no rebate',
                'Money above the rebate limit, or above an instrument’s cap, is still saving, but it does not reduce this year’s tax.');
        }

        // Filing date
        $endYear = (int) substr($ctx['year']['income_year_end'], 0, 4);
        $zone = $today->getTimezone();
        $early = new DateTimeImmutable("{$endYear}-09-30", $zone);
        $standard = new DateTimeImmutable("{$endYear}-12-31", $zone);
        if ($tax > 0) {
            if ($today <= $early && $in['filing'] !== 'early') {
                $saving = abs($e->filingAdjustment($tax, 'early'));
                $add('filing', 'calendar', 'good', 'File by 30 September '.$endYear.' to save '.Money::bdt($saving),
                    'Returns filed between 1 July and 30 September get a 5% rebate on the tax, up to ৳25,000.');
            } elseif (in_array($in['filing'], ['late', 'very_late'], true)) {
                $add('filing', 'alert', 'cost', 'Late filing adds '.Money::bdt($s['filing_adjustment']),
                    'File by 31 December '.$endYear.' to avoid the additional tax.');
            } elseif ($today > $early && $today <= $standard) {
                $add('filing', 'calendar', 'watch', 'File by 31 December '.$endYear,
                    'Filing after December adds 2% (at least ৳3,000), rising to 5% (at least ৳5,000) after March.');
            }
        }

        // TDS status
        if ($s['liability'] > 0 || $s['tds_paid'] > 0) {
            if ($s['payable'] > 0.5) {
                $add('tds', 'receipt', 'cost', Money::bdt($s['payable']).' still to pay',
                    ($s['tds_paid'] > 0 ? 'After the '.Money::bdt($s['tds_paid']).' already deducted, set' : 'Set')
                    .' aside about '.Money::bdt($s['monthly_tax']).' a month, or ask your employer to deduct that much as monthly TDS.');
            } elseif ($s['payable'] < -0.5) {
                $add('tds', 'receipt', 'good', Money::bdt(-$s['payable']).' refund due',
                    'More tax was deducted than you owe. Claim it in your return; salaried taxpayers now get automated refunds.');
            } else {
                $add('tds', 'check', 'good', 'Your TDS covers the full tax', 'Nothing more to pay when you file.');
            }
        }

        if ($gross > 0) {
            // Raise sensitivity
            $extra = $e->netTax($gross + 10000, $eligible, $ctx) - $tax;
            $add('raise', 'coins', 'info', 'You keep '.Money::bdt(10000 - $extra).' of every ৳10,000 raise',
                'An extra ৳10,000 of salary adds about '.Money::bdt($extra).' in tax.'
                .($gross < $ctx['ex_cap'] / max($ctx['ex_fraction'], 1e-9) ? ' One-third of any raise is tax-free, which softens your slab rate.' : ''));

            // Exemption shield
            $capGross = $ctx['ex_fraction'] > 0 ? $ctx['ex_cap'] / $ctx['ex_fraction'] : 0;
            if ($gross < $capGross) {
                $add('shield', 'shield', 'good', 'Your tax-free salary is still growing',
                    'One-third of your salary ('.Money::bdt($core['exemption']).' now) is tax-free until gross income reaches '
                    .Money::bdt($capGross).', which is '.Money::bdt($capGross - $gross).' away.');
            } else {
                $add('shield', 'shield', 'info', 'Your tax-free salary is maxed out',
                    'The exemption stops at '.Money::bdt($ctx['ex_cap']).', so every extra taka of salary is now fully taxable at your slab rate.');
            }

            // Average vs marginal
            $add('rates', 'percent', 'info', 'Average '.Money::pct($s['effective_rate']).', marginal '.Money::pct($s['marginal_rate'], 0),
                'You pay '.Money::pct($s['effective_rate']).' of gross income in tax overall, but raises and bonuses are taxed at your top slab rate.');

            // Next year forecast
            $g2 = $gross * 1.1;
            $c2 = $e->core($g2, $eligible, $ctx);
            $slabMove = $e->topBandIndex($c2['taxable'], $ctx) !== $e->topBandIndex($core['taxable'], $ctx);
            $add('forecast', 'calendar', 'info', 'A 10% raise means '.Money::bdt($c2['tax'] - $tax).' more tax',
                'At '.Money::bdt($g2).' a year, tax rises to about '.Money::bdt($c2['tax']).' and the investment needed for the full rebate becomes '
                .Money::bdt(ceil($c2['investment_needed'])).($slabMove ? '. That raise also moves you into the '.Money::pct($e->marginalRate($c2['taxable'], $ctx), 0).' slab.' : '.'));

            // Bonus
            $bonus = $gross / 12;
            $bonusTax = $e->netTax($gross + $bonus, $eligible, $ctx) - $tax;
            $add('bonus', 'gift', 'info', 'An extra month’s bonus costs '.Money::bdt($bonusTax).' in tax',
                'A one-off bonus of '.Money::bdt($bonus).' adds about '.Money::bdt($bonusTax).' to your tax, so you keep '.Money::bdt($bonus - $bonusTax).'.');

            // Raise needed for 10% more take-home
            $takeHome = $gross - $tax;
            $target = $takeHome * 1.1;
            $lo = $gross;
            $hi = $gross * 3 + 1000000;
            for ($i = 0; $i < 60; $i++) {
                $mid = ($lo + $hi) / 2;
                if ($mid - $e->netTax($mid, $eligible, $ctx) >= $target) {
                    $hi = $mid;
                } else {
                    $lo = $mid;
                }
            }
            $add('takehome', 'wallet', 'info', 'A '.Money::pct($hi / $gross - 1).' raise lifts take-home by 10%',
                'To bring home '.Money::bdt($takeHome * 0.1 / 12).' more a month, your gross salary needs to reach about '.Money::bdt(ceil($hi)).'.');

            // Per ৳100 / days
            if ($s['liability'] > 0) {
                $add('days', 'clock', 'info', '৳'.number_format($s['effective_rate'] * 100, 2).' of every ৳100 goes to tax',
                    'Spread across the year, that equals about '.max(1, (int) round($s['effective_rate'] * 365)).' days of your income.');
            }
        }

        if ($core['min_tax_applied']) {
            $add('mintax', 'alert', 'watch', 'Minimum tax applies',
                'Your tax after rebate works out below '.Money::bdt($ctx['min_tax']).', so you pay the minimum. More investment will not lower it.');
        }

        foreach ($future as $row) {
            if ($row['projected'] && $row['tax'] < $tax - 1) {
                $add('roadmap', 'sprout', 'good', 'Tax-free limit rises to '.Money::bdt($row['threshold']).' in '.$row['label'],
                    'At today’s income your tax under that year’s rules would be about '.Money::bdt($row['tax']).', '.Money::bdt($tax - $row['tax']).' less.');
                break;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ helpers

    private function roundDeep(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->roundDeep($v, is_string($k) ? $k : $key);
            }

            return $out;
        }
        if (is_float($value)) {
            if ($value >= TaxEngine::INF) {
                return null;
            }
            $precise = in_array($key, ['rate', 'effective_rate', 'marginal_rate', 'effective', 'marginal', 'fill', 'progress',
                'keep_pct', 'raise', 'rate_now', 'rate_next', 'top_rate', 'slab_progress', 'rebate_rate', 'rebate_pct'], true);

            return $precise ? round($value, 6) : (float) round($value);
        }

        return $value;
    }
}
