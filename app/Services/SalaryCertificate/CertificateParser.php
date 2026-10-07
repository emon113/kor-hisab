<?php

namespace App\Services\SalaryCertificate;

/**
 * Turns the OCR text of a salary certificate into figures and details.
 *
 * Certificates are tables: a label, then a monthly and/or yearly amount.
 * OCR keeps the columns (tab-separated) but often misreads thousands
 * separators ("720,000" → "720.000", "60-000", "6_000"), so amounts are
 * read leniently. Labels are matched with the patterns in
 * config/salary_certificate.php. Nothing here is final: the user reviews
 * every figure next to the document before anything is calculated.
 */
final class CertificateParser
{
    /** Fields that may appear on several lines and add up (two Eid bonuses, several allowances…). */
    private const ADDITIVE = ['festival_bonus', 'other_allowances', 'other_facility', 'performance_bonus', 'overtime', 'arrear', 'leave_encashment'];

    private const BANGLA_DIGITS = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    /** A money amount with optional separators (OCR may turn "," into ".", "-" or "_"). */
    private const MONEY = '\d{1,3}(?:[.,\-_\x{2009} ]\d{2,3})+(?:[.,]\d{1,2})?|\d{3,}(?:[.,]\d{1,2})?';

    /**
     * @param  array  $config  config/salary_certificate.php
     * @param  array  $years  config('tax.years'), to recognise the assessment year
     */
    public function __construct(private readonly array $config, private readonly array $years) {}

    public function parse(string $text): array
    {
        $text = strtr(str_replace("\r", '', $text), self::BANGLA_DIGITS);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== ''));

        $figures = [];
        $evidence = [];
        $grossTotal = null;
        $yearlyColumn = null;   // index of the yearly column among a line's amounts, from a header row

        foreach ($lines as $line) {
            [$label, $amounts] = $this->split($line);

            if (! $amounts) {
                $yearlyColumn = $this->headerColumn($line) ?? $yearlyColumn;

                continue;
            }

            $field = $this->match($label);
            if ($field === null) {
                continue;
            }
            $amount = $this->pick($amounts, $yearlyColumn);

            if ($field === 'gross_total') {
                $grossTotal ??= $amount;

                continue;
            }
            if (isset($figures[$field]) && ! in_array($field, self::ADDITIVE, true)) {
                continue;   // first match wins for single-line figures
            }
            // A "total festival bonus" line replaces the parts already added up.
            $isTotal = (bool) preg_match('/\btotal\b|মোট/u', mb_strtolower($label));
            $figures[$field] = $isTotal ? $amount : ($figures[$field] ?? 0) + $amount;
            $evidence[$field][] = $line;
        }

        return [
            'figures' => $figures,
            'gross_total' => $grossTotal,
            'found' => array_keys($figures),
            'evidence' => array_map(fn ($l) => implode("\n", $l), $evidence),
            'meta' => [
                'employer' => $this->employer($lines),
                'employee' => $this->employee($text),
                'tin' => $this->tin($text),
                'year' => $this->year($text),
            ],
        ];
    }

    /** Read one amount; null if the text is not an amount. Dashes and "nil" read as zero. */
    public function amount(string $text): ?float
    {
        $t = trim(strtr($text, self::BANGLA_DIGITS));
        $t = trim(preg_replace('/^(tk\.?|taka|bdt|৳)\s*|\s*(\/-|=|tk\.?|taka)$/iu', '', $t));
        if (preg_match('/^(-|–|—|nil|n\/a|none)$/iu', $t)) {
            return 0.0;
        }
        if (! preg_match('/^(?:'.self::MONEY.')$/u', $t) || preg_match('/^(19|20)\d{2}$/', $t)) {
            return null;
        }
        $groups = preg_split('/[.,\-_\x{2009} ]/u', $t);
        $decimal = 0.0;
        if (count($groups) > 1 && strlen(end($groups)) <= 2) {
            $decimal = (float) ('0.'.array_pop($groups));
        }

        return (float) implode('', $groups) + $decimal;
    }

    /** Label text and the amounts on a line. Uses OCR's tab columns when present, else free text. */
    private function split(string $line): array
    {
        $cells = array_values(array_filter(array_map('trim', preg_split('/\t|\s{3,}/u', $line)), fn ($c) => $c !== ''));

        if (count($cells) > 1) {
            $label = [];
            $amounts = [];
            foreach ($cells as $cell) {
                $value = $this->amount($cell);
                if ($value === null) {
                    if (! $amounts) {
                        $label[] = $cell;   // text before the first amount is the label
                    }
                } elseif ($label) {
                    $amounts[] = $value;
                }
            }

            return [implode(' ', $label), $amounts];
        }

        // One cell: "Basic Salary Tk. 60,000 720,000".
        if (! preg_match_all('/(?<![\w.,])(?:'.self::MONEY.')(?![\w])/u', $line, $m, PREG_OFFSET_CAPTURE)) {
            return [$line, []];
        }
        $amounts = [];
        $labelEnd = null;
        foreach ($m[0] as [$token, $offset]) {
            $value = $this->amount($token);
            if ($value !== null) {
                $amounts[] = $value;
                $labelEnd ??= $offset;
            }
        }

        return [trim(substr($line, 0, $labelEnd ?? strlen($line))), $amounts];
    }

    /** The yearly amount: by header column if known, else the last amount on the line. */
    private function pick(array $amounts, ?int $yearlyColumn): float
    {
        if ($yearlyColumn !== null && isset($amounts[$yearlyColumn]) && count($amounts) > $yearlyColumn) {
            return $amounts[$yearlyColumn];
        }

        return end($amounts);
    }

    /** For a header row ("Particulars | Monthly | Yearly"), the index of the yearly column. */
    private function headerColumn(string $line): ?int
    {
        $cells = array_values(array_filter(array_map('trim', preg_split('/\t|\s{3,}/u', mb_strtolower($line))), fn ($c) => $c !== ''));
        $monthly = '/'.implode('|', $this->config['monthly_headers']).'/u';
        $yearly = '/'.implode('|', $this->config['yearly_headers']).'/u';
        if (count($cells) < 2 || ! preg_grep($monthly, $cells)) {
            return null;
        }
        $amountCells = array_values(array_slice($cells, 1));   // the first cell is the label column
        foreach ($amountCells as $i => $cell) {
            if (preg_match($yearly, $cell) && ! preg_match($monthly, $cell)) {
                return $i;
            }
        }

        return null;
    }

    private function match(string $label): ?string
    {
        $label = mb_strtolower($label);
        if ($label === '') {
            return null;
        }
        foreach ($this->config['labels'] as $field => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/'.$pattern.'/u', $label)) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function employer(array $lines): ?string
    {
        foreach (array_slice($lines, 0, 6) as $line) {
            $clean = trim(preg_replace('/\s+/', ' ', str_replace("\t", ' ', $line)));
            if (preg_match('/\b(limited|ltd\.?|plc|bank|company|corporation|group|industries|international)\b/i', $clean)
                && ! preg_match('/certif|salary|statement/i', $clean)) {
                return mb_substr($clean, 0, 120);
            }
        }

        return null;
    }

    private function employee(string $text): ?string
    {
        $flat = preg_replace('/\s+/', ' ', $text);
        if (preg_match('/(?:certify that|certified that|name\s*(?:of (?:the )?employee)?\s*[:\-])\s*(?:mr\.?|mrs\.?|ms\.?|miss|dr\.?)?\s*([A-Za-z][A-Za-z .\'-]{2,60}?)\s*(?:[,(]|\.\s|\.$|\s(?:son|daughter|s\/o|d\/o|w\/o|tin|employee|designation|id|has|is|was|bearing)\b)/i', $flat, $m)) {
            return trim($m[1], ' .');
        }

        return null;
    }

    private function tin(string $text): ?string
    {
        if (preg_match('/\bt\.?i\.?n\b[^\d]{0,20}(\d{12})\b/i', $text, $m) || preg_match('/(?<!\d)(\d{12})(?!\d)/', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    /** The assessment year key in config/tax.php, if the certificate's period can be recognised. */
    private function year(string $text): ?string
    {
        $flat = preg_replace('/\s+/', ' ', $text);
        $assessmentStart = match (true) {
            (bool) preg_match('/assessment\s*year\D{0,10}(20\d{2})\s*[-–\/]\s*\d{2,4}/i', $flat, $m) => (int) $m[1],
            (bool) preg_match('/(?:income|financial|fiscal)\s*year\D{0,10}(20\d{2})\s*[-–\/]\s*\d{2,4}/i', $flat, $m) => (int) $m[1] + 1,
            (bool) preg_match('/jul\w*\.?,?\s*(?:\d{1,2},?\s*)?20\d{2}.{0,25}?jun\w*\.?,?\s*(?:\d{1,2},?\s*)?(20\d{2})/i', $flat, $m) => (int) $m[1],
            (bool) preg_match('/0?1[.\/-]0?7[.\/-]20\d{2}.{0,20}?30[.\/-]0?6[.\/-](20\d{2})/', $flat, $m) => (int) $m[1],
            (bool) preg_match('/\b(20\d{2})\s*[-–]\s*(?:20)?\d{2}\b/', $flat, $m) => (int) $m[1] + 1,
            default => null,
        };
        if ($assessmentStart === null) {
            return null;
        }
        foreach ($this->years as $key => $year) {
            if ((int) substr($year['income_year_end'], 0, 4) === $assessmentStart) {
                return $key;
            }
        }

        return null;
    }
}
