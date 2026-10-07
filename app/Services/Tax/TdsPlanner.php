<?php

namespace App\Services\Tax;

use App\Support\Lang;
use App\Support\Money;
use DateTimeImmutable;

/**
 * Monthly TDS plan for one income year (July to June).
 *
 * The year's tax comes from TaxEngine on the projected annual salary. TDS
 * already deducted in finished months is subtracted, and what is left is
 * spread evenly across the remaining months that pay a salary, so the year
 * ends with nothing to pay and no refund to chase.
 */
final class TdsPlanner
{
    public const MONTHS = ['jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar', 'apr', 'may', 'jun'];

    private const NAMES = ['jul' => 'July', 'aug' => 'August', 'sep' => 'September', 'oct' => 'October', 'nov' => 'November', 'dec' => 'December',
        'jan' => 'January', 'feb' => 'February', 'mar' => 'March', 'apr' => 'April', 'may' => 'May', 'jun' => 'June'];

    /** Being behind or ahead by less than this is "on track". */
    private const SLACK = 1000;

    public function __construct(private readonly TaxEngine $engine) {}

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

    public function plan(array $raw): array
    {
        $in = $this->engine->normalize($raw);
        $ctx = $this->engine->context($in);
        $eligible = max(0.0, round((float) ($raw['investment'] ?? 0)));
        $months = $this->months($raw['months'] ?? []);

        $gross = array_sum(array_map(fn ($m) => $m['salary'] + $m['bonus'], $months));
        $core = $this->engine->core($gross, $eligible, $ctx);
        $liability = round($core['tax']);

        $deducted = array_sum(array_map(fn ($m) => $m['done'] ? $m['tds'] : 0, $months));
        $remaining = $liability - $deducted;

        // Spread what is left over the open months that pay a salary (all open months if none do).
        $open = array_keys(array_filter($months, fn ($m) => ! $m['done'] && $m['salary'] + $m['bonus'] > 0));
        if (! $open) {
            $open = array_keys(array_filter($months, fn ($m) => ! $m['done']));
        }
        $suggested = array_fill_keys(self::MONTHS, 0.0);
        if ($open && $remaining > 0) {
            $each = floor($remaining / count($open));
            foreach ($open as $key) {
                $suggested[$key] = $each;
            }
            $suggested[end($open)] += $remaining - $each * count($open);   // last month absorbs rounding
        }

        // The pace line: tax due by each month in proportion to income earned so far.
        $rows = [];
        $cumIncome = $cumPlanned = 0.0;
        $neededByNow = 0.0;
        $calendar = $this->calendar($in['year']);
        foreach ($months as $key => $m) {
            $cumIncome += $m['salary'] + $m['bonus'];
            $planned = $m['done'] ? $m['tds'] : $suggested[$key];
            $cumPlanned += $planned;
            $needed = $gross > 0 ? round($liability * $cumIncome / $gross) : 0.0;
            if ($m['done']) {
                $neededByNow = $needed;
            }
            [$y] = $calendar[$key];
            $rows[] = $m + [
                'key' => $key,
                'label' => Lang::label(':month :year', ['month' => Lang::t(self::NAMES[$key]), 'year' => $y]),
                'suggested' => $suggested[$key],
                'planned' => $planned,
                'cumulative_planned' => $cumPlanned,
                'cumulative_needed' => $needed,
            ];
        }

        $status = match (true) {
            $remaining < -0.5 => 'refund',
            $deducted < $neededByNow - self::SLACK => 'behind',
            $deducted > $neededByNow + self::SLACK => 'ahead',
            default => 'on_track',
        };
        $next = $open ? $suggested[$open[0]] : 0.0;

        return [
            'input' => $in + ['investment' => $eligible],
            'rules' => ['label' => Lang::label($ctx['year']['label']), 'income_year' => Lang::label($ctx['year']['income_year'])],
            'gross' => $gross,
            'taxable' => round($core['taxable']),
            'rebate' => round($core['rebate_used']),
            'liability' => $liability,
            'deducted' => $deducted,
            'remaining' => $remaining,
            'needed_by_now' => $neededByNow,
            'open_months' => count($open),
            'next_monthly' => $next,
            'status' => $status,
            'months' => $rows,
            'hr_text' => $this->hrText($ctx, $gross, $liability, $deducted, $remaining, $open, $suggested, $calendar),
        ];
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

    private function hrText(array $ctx, float $gross, float $liability, float $deducted, float $remaining, array $open, array $suggested, array $calendar): string
    {
        $vars = [
            'year' => Lang::label($ctx['year']['label']),
            'gross' => Money::bdt($gross),
            'tax' => Money::bdt($liability),
            'deducted' => Money::bdt($deducted),
        ];

        if ($remaining <= 0 || ! $open) {
            return Lang::t('Dear HR, my projected salary for :year is :gross, with income tax of :tax. :deducted has already been deducted, which covers it. Please do not deduct further TDS this year.', $vars);
        }

        $first = $open[0];
        $last = end($open);
        $label = fn ($k) => Lang::label(':month :year', ['month' => Lang::t(self::NAMES[$k]), 'year' => $calendar[$k][0]]);

        return Lang::t('Dear HR, my projected salary for :year is :gross, with income tax of :tax. :deducted has been deducted so far. Please deduct :monthly TDS each month from :from to :to (:count months) so the full tax is covered.', $vars + [
            'monthly' => Money::bdt($suggested[$first]),
            'from' => $label($first),
            'to' => $label($last),
            'count' => Money::digits((string) count($open)),
        ]);
    }
}
