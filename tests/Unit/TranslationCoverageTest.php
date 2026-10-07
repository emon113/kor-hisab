<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every English string the app can show must have a Bangla translation,
 * and every translation must keep the same :placeholders.
 */
class TranslationCoverageTest extends TestCase
{
    private const SCAN = ['app', 'resources/views', 'public/js', 'routes'];

    /** Config files whose user-facing labels are translated where they are shown. */
    private const LABEL_KEYS = ['label', 'hint', 'income_year', 'question', 'reason', 'service', 'text', 'title', 'note'];

    private string $root;

    private array $bn;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->bn = json_decode(file_get_contents($this->root.'/lang/bn.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_every_source_string_has_a_bangla_translation(): void
    {
        $missing = array_values(array_diff(array_keys($this->sourceStrings()), array_keys($this->bn)));

        $this->assertSame([], $missing, "Add these to lang/bn.json:\n".implode("\n", $missing));
    }

    public function test_every_config_label_has_a_bangla_translation(): void
    {
        $missing = [];
        foreach ($this->translatedConfigs() as $file) {
            array_walk_recursive($file, function ($value, $key) use (&$missing) {
                if (is_string($key) && in_array($key, self::LABEL_KEYS, true) && is_string($value) && ! isset($this->bn[$value])) {
                    $missing[] = $value;
                }
            });
        }
        foreach ((require $this->root.'/config/tax.php')['categories'] as $label) {
            isset($this->bn[$label]) || $missing[] = $label;
        }

        $this->assertSame([], array_values(array_unique($missing)), "Add these config labels to lang/bn.json:\n".implode("\n", $missing));
    }

    public function test_translation_calls_use_literal_strings(): void
    {
        // A ternary inside the call hides both strings from the coverage scan: write two calls instead.
        $offenders = [];
        foreach ($this->sourceFiles() as $path) {
            if (preg_match_all('/(?:__|Lang::t|Lang::label|KH\.t)\([^)\'"]*\?\s*[\'"]/', file_get_contents($path), $m)) {
                $offenders[] = basename($path).': '.implode(' | ', $m[0]);
            }
        }

        $this->assertSame([], $offenders);
    }

    public function test_translations_keep_their_placeholders(): void
    {
        $broken = [];
        foreach ($this->bn as $en => $bn) {
            preg_match_all('/:([a-z_]+)/', $en, $a);
            preg_match_all('/:([a-z_]+)/', $bn, $b);
            $want = array_unique($a[1]);
            $have = array_unique($b[1]);
            sort($want);
            sort($have);
            if ($want !== $have) {
                $broken[] = $en;
            }
        }

        $this->assertSame([], $broken, "Placeholders differ in:\n".implode("\n", $broken));
    }

    /** Literal arguments of __(), Lang::t(), Lang::label() and KH.t() across the project. */
    private function sourceStrings(): array
    {
        $found = [];
        foreach ($this->sourceFiles() as $path) {
            preg_match_all('/(?:__|Lang::t|Lang::label|KH\.t)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s', file_get_contents($path), $m);
            foreach ($m[2] as $string) {
                $found[stripcslashes($string)] = true;
            }
        }

        return $found;
    }

    private function sourceFiles(): array
    {
        $paths = [];
        foreach (self::SCAN as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root.'/'.$dir)) as $file) {
                if (preg_match('/\.(php|js)$/', $file->getFilename())) {
                    $paths[] = $file->getPathname();
                }
            }
        }

        return $paths;
    }

    private function translatedConfigs(): array
    {
        $tax = require $this->root.'/config/tax.php';
        $configs = [['years' => $tax['years'], 'instruments' => $tax['instruments'], 'filing' => $tax['filing_periods'], 'salary' => $tax['salary_components']]];
        foreach (['return_form', 'wealth', 'filing_check'] as $name) {
            $path = $this->root."/config/{$name}.php";
            if (is_file($path)) {
                $configs[] = require $path;
            }
        }

        return $configs;
    }
}
