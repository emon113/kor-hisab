<?php

namespace App\Support;

/**
 * Choice lists for the pages, translated, built from config/tax.php and
 * config/salary.php. One place so every page shows the same labels.
 */
final class TaxOptions
{
    public static function years(): array
    {
        return collect(config('tax.years'))->map(fn ($y, $k) => [
            'key' => $k,
            'label' => Lang::label($y['label']),
            'income_year' => Lang::label($y['income_year']),
            'projected' => (bool) $y['projected'],
        ])->values()->all();
    }

    public static function categories(): array
    {
        return collect(config('tax.categories'))->map(fn ($label, $k) => ['key' => $k, 'label' => __($label)])->values()->all();
    }

    public static function instruments(): array
    {
        return collect(config('tax.instruments'))->map(fn ($i, $k) => [
            'key' => $k,
            'label' => __($i['label']),
            'hint' => __($i['hint']),
            'cap' => $i['cap'],
            'common' => $i['common'] ?? true,
        ])->values()->all();
    }

    public static function filing(): array
    {
        return collect(config('tax.filing_periods'))->map(fn ($f, $k) => ['key' => $k, 'label' => Lang::label($f['label']), 'hint' => Lang::label($f['hint'])])->values()->all();
    }

    public static function perks(): array
    {
        return collect(config('salary.perks'))->map(fn ($p, $k) => [
            'key' => $k,
            'label' => __($p['label']),
            'hint' => $p['hint'] ? __($p['hint']) : null,
            'line' => $p['line'],
        ])->values()->all();
    }
}
