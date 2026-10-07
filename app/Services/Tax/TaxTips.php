<?php

namespace App\Services\Tax;

use App\Support\Lang;
use App\Support\Money;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Legal ways to lower the tax in a TaxReport, with the saving each brings.
 *
 * Which tips apply, and how much they save, is decided here from the report
 * and the engine ("what would the tax be if…"). The words live in
 * config/tips.php. Tips with a saving come first, largest first.
 */
final class TaxTips
{
    public const KINDS = ['invest', 'claim', 'file', 'check', 'know'];

    public function __construct(private readonly TaxEngine $engine, private readonly array $tips) {}

    /** Every tip in general terms, grouped by kind, for the catalogue page. */
    public function catalogue(): array
    {
        $groups = array_fill_keys(self::KINDS, []);
        foreach ($this->tips as $id => $tip) {
            $groups[$tip['kind']][] = $this->format($id, [], null, general: true);
        }

        return array_filter($groups);
    }

    /** Tips that apply to this report, personalised. */
    public function for(array $report, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today', new DateTimeZone('Asia/Dhaka'));
        $in = $report['input'];
        $s = $report['summary'];
        $inv = $report['investments'];
        $ctx = $this->engine->context($in);
        $gross = (float) $s['gross'];
        $tax = (float) $s['tax_after_rebate'];
        $eligible = (float) $inv['eligible'];
        $amounts = array_column($inv['items'], 'amount', 'key');
        $bdt = fn (float $v) => Money::bdt($v);

        // Tax on the same income if one thing about the taxpayer were different.
        $taxIf = fn (array $change) => round($this->engine->netTax($gross, $eligible, $this->engine->context($change + $in)));

        $out = [];
        $add = function (string $id, array $vars = [], ?float $saving = null) use (&$out) {
            if (isset($this->tips[$id])) {
                $out[] = $this->format($id, $vars, $saving);
            }
        };

        if ($gross <= 0) {
            return [];
        }

        $gap = (float) $inv['gap'];
        if ($gap > 0 && $inv['extra_saving'] > 0) {
            $add('rebate_gap', ['gap' => $bdt($gap), 'saving' => $bdt($inv['extra_saving'])], (float) $inv['extra_saving']);
            foreach (['provident_fund', 'life_insurance', 'pension', 'zakat_donation'] as $key) {
                if ((float) ($amounts[$key] ?? 0) <= 0) {
                    $add($key === 'zakat_donation' ? 'zakat' : $key, ['gap' => $bdt($gap)]);
                }
            }
        }

        // Filing date: the early rebate while the window is open, otherwise avoid the late charge.
        $endYear = (int) substr($ctx['year']['income_year_end'], 0, 4);
        $earlyOpen = $today <= new DateTimeImmutable("{$endYear}-09-30", $today->getTimezone());
        $early = abs($this->engine->filingAdjustment($tax, 'early'));
        if ($tax > 0 && $earlyOpen && $in['filing'] !== 'early' && $early > 0) {
            $add('early_filing', ['year' => Money::digits((string) $endYear), 'saving' => $bdt($early + max(0.0, $s['filing_adjustment']))], $early + max(0.0, $s['filing_adjustment']));
        } elseif ($s['filing_adjustment'] > 0) {
            $add('late_filing', ['saving' => $bdt($s['filing_adjustment'])], (float) $s['filing_adjustment']);
        }

        if ($s['min_tax_applied'] && ! $in['new_taxpayer']) {
            $saving = $tax - $taxIf(['new_taxpayer' => true]);
            if ($saving > 0) {
                $add('first_return', ['saving' => $bdt($saving)], $saving);
            }
        }
        if ($in['category'] === 'general' && $tax > 0) {
            $saving = $tax - $taxIf(['category' => 'women_senior']);
            if ($saving > 0) {
                $add('category', ['saving' => $bdt($saving)], $saving);
            }
        }
        if ((int) $in['disabled_children'] === 0 && $tax > 0) {
            $saving = $tax - $taxIf(['disabled_children' => 1]);
            if ($saving > 0) {
                $add('disabled_child', ['saving' => $bdt($saving)], $saving);
            }
        }

        if ($s['payable'] < -0.5) {
            $add('refund', ['amount' => $bdt(-$s['payable'])]);
        }
        if ($inv['invested'] > 0 && $tax > 0) {
            $add('declare_to_hr');
        }
        if ($inv['wasted'] > 0 && $s['taxable'] > $s['threshold']) {
            $add('over_invest', ['amount' => $bdt($inv['wasted'])]);
        }
        if ($s['min_tax_applied']) {
            $add('minimum_tax', ['amount' => $bdt($s['min_tax'])]);
        }
        if ($tax > 0) {
            $add('household');
        }
        $add('salary_structure');
        if ($inv['invested'] > 0) {
            $add('keep_proof');
        }

        // Savings first (largest first), then by kind in the order of KINDS.
        $order = array_flip(self::KINDS);
        usort($out, fn ($a, $b) => [$b['saving'] !== null, $b['saving'] ?? 0, -$order[$b['kind']]] <=> [$a['saving'] !== null, $a['saving'] ?? 0, -$order[$a['kind']]]);

        return $out;
    }

    private function format(string $id, array $vars, ?float $saving, bool $general = false): array
    {
        $tip = $this->tips[$id];

        return [
            'id' => $id,
            'kind' => $tip['kind'],
            'icon' => $tip['icon'],
            'title' => Lang::t($general ? $tip['title'] : $tip['headline'], $vars),
            'about' => Lang::t($tip['about']),
            'law' => Lang::t($tip['law']),
            'saving' => $saving === null ? null : round($saving),
        ];
    }
}
