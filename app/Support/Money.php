<?php

namespace App\Support;

/**
 * Number formatting. Two grouping styles are supported:
 *  - "intl": 1,335,524 (default)
 *  - "lakh": 13,35,524 (South Asian lakh/crore grouping)
 * and two digit sets: Latin (0-9) or Bangla (০-৯).
 */
final class Money
{
    private const BANGLA = ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯'];

    private static string $style = 'intl';

    private static string $digits = 'latin';

    public static function useGrouping(?string $style): void
    {
        self::$style = $style === 'lakh' ? 'lakh' : 'intl';
    }

    public static function useDigits(?string $digits): void
    {
        self::$digits = $digits === 'bn' ? 'bn' : 'latin';
    }

    public static function digitStyle(): string
    {
        return self::$digits;
    }

    /** Swap 0-9 for ০-৯ when Bangla digits are on. */
    public static function digits(string $text): string
    {
        return self::$digits === 'bn' ? strtr($text, self::BANGLA) : $text;
    }

    public static function group(float|int $value): string
    {
        $negative = $value < 0;
        $digits = (string) (int) round(abs($value));

        if (self::$style === 'intl') {
            return self::digits(($negative ? '-' : '').number_format((float) $digits));
        }

        if (strlen($digits) > 3) {
            $last3 = substr($digits, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($digits, 0, -3));
            $digits = $rest.','.$last3;
        }

        return self::digits(($negative ? '-' : '').$digits);
    }

    public static function bdt(float|int $value): string
    {
        return ($value < 0 ? '−' : '').'৳'.self::group(abs($value));
    }

    public static function pct(float $ratio, int $decimals = 1): string
    {
        return self::digits(number_format($ratio * 100, $decimals)).'%';
    }
}
