<?php

namespace App\Support;

/**
 * Number formatting. Two grouping styles are supported:
 *  - "intl": 1,335,524 (default)
 *  - "lakh": 13,35,524 (South Asian lakh/crore grouping)
 */
final class Money
{
    private static string $style = 'intl';

    public static function useGrouping(?string $style): void
    {
        self::$style = $style === 'lakh' ? 'lakh' : 'intl';
    }

    public static function group(float|int $value): string
    {
        $negative = $value < 0;
        $digits = (string) (int) round(abs($value));

        if (self::$style === 'intl') {
            return ($negative ? '-' : '').number_format((float) $digits);
        }

        if (strlen($digits) > 3) {
            $last3 = substr($digits, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($digits, 0, -3));
            $digits = $rest.','.$last3;
        }

        return ($negative ? '-' : '').$digits;
    }

    public static function bdt(float|int $value): string
    {
        return ($value < 0 ? '−' : '').'৳'.self::group(abs($value));
    }

    public static function pct(float $ratio, int $decimals = 1): string
    {
        return number_format($ratio * 100, $decimals).'%';
    }
}
