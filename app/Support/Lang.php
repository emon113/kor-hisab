<?php

namespace App\Support;

/**
 * Translation for code that must also run outside a booted Laravel app
 * (the tax services are unit-tested with plain PHPUnit).
 *
 * Inside the app this is __(); without a translator it only fills in the
 * :placeholders, so the English text still reads correctly.
 */
final class Lang
{
    public static function t(string $text, array $replace = []): string
    {
        if (function_exists('app') && app()->bound('translator')) {
            return __($text, $replace);
        }

        uksort($replace, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($replace as $key => $value) {
            $text = str_replace(':'.$key, (string) $value, $text);
        }

        return $text;
    }

    /** Translate a label that contains digits (years, dates) and localise the digits. */
    public static function label(string $text, array $replace = []): string
    {
        return Money::digits(self::t($text, $replace));
    }
}
