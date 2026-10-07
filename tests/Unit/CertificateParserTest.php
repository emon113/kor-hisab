<?php

namespace Tests\Unit;

use App\Services\SalaryCertificate\CertificateParser;
use PHPUnit\Framework\TestCase;

class CertificateParserTest extends TestCase
{
    private CertificateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CertificateParser(
            require __DIR__.'/../../config/salary_certificate.php',
            (require __DIR__.'/../../config/tax.php')['years'],
        );
    }

    private function fixture(string $name): array
    {
        return $this->parser->parse(file_get_contents(__DIR__."/../Fixtures/certificates/{$name}.txt"));
    }

    public function test_real_ocr_output_with_misread_separators(): void
    {
        // Captured from OCR.space: "720,000" came back as "720.000", "60-000" and "6_000".
        $r = $this->fixture('ocrspace-table');

        $this->assertSame([
            'basic' => 720000.0, 'house_rent' => 360000.0, 'medical' => 72000.0, 'conveyance' => 48000.0,
            'festival_bonus' => 120000.0, 'employer_pf' => 72000.0, 'tds' => 42000.0,
        ], $r['figures']);
        $this->assertSame(1392000.0, $r['gross_total']);
        $this->assertSame(array_sum($r['figures']) - $r['figures']['tds'], $r['gross_total']);   // components add up
        $this->assertSame(['employer' => 'ACME BANGLADESH LIMITED', 'employee' => 'Rahim Uddin', 'tin' => '123456789012', 'year' => '2026-27'], $r['meta']);
    }

    public function test_free_text_certificate_with_lakh_grouping(): void
    {
        $r = $this->fixture('free-text');

        $this->assertSame(720000.0, $r['figures']['basic']);
        $this->assertSame(360000.0, $r['figures']['house_rent']);           // "3,60,000/-"
        $this->assertSame(120000.0, $r['figures']['festival_bonus']);       // two Eid bonuses add up
        $this->assertSame(36000.0, $r['figures']['other_allowances']);      // lunch + mobile
        $this->assertSame(72000.0, $r['figures']['employee_pf']);           // own PF: an investment, not income
        $this->assertSame(40500.0, $r['figures']['tds']);
        $this->assertSame(1396000.0, $r['gross_total']);
        $this->assertSame('Nusrat Jahan', $r['meta']['employee']);
        $this->assertSame('Beximco Pharmaceuticals Limited', $r['meta']['employer']);
        $this->assertSame('2026-27', $r['meta']['year']);                   // income year 2025-26
    }

    public function test_bangla_certificate_with_bangla_digits(): void
    {
        $r = $this->fixture('bangla');

        $this->assertSame(600000.0, $r['figures']['basic']);
        $this->assertSame(300000.0, $r['figures']['house_rent']);
        $this->assertSame(60000.0, $r['figures']['medical']);
        $this->assertSame(36000.0, $r['figures']['conveyance']);
        $this->assertSame(100000.0, $r['figures']['festival_bonus']);
        $this->assertSame(24000.0, $r['figures']['tds']);
        $this->assertSame(1096000.0, $r['gross_total']);
        $this->assertSame('2026-27', $r['meta']['year']);                   // 01/07/2025 – 30/06/2026
    }

    public function test_amounts_are_read_leniently(): void
    {
        $cases = [
            '720,000' => 720000.0, '720.000' => 720000.0, '60-000' => 60000.0, '6_000' => 6000.0,
            '1.392.000' => 1392000.0, '13,92,000' => 1392000.0, '13,92,000.50' => 1392000.5, 'Tk. 48,000/-' => 48000.0,
            '৳৭২,০০০' => 72000.0, '-' => 0.0, '—' => 0.0, 'nil' => 0.0, '850' => 850.0,
        ];
        foreach ($cases as $text => $expected) {
            $this->assertSame($expected, $this->parser->amount($text), "Reading \"{$text}\"");
        }
        foreach (['2026', 'Basic', '12', ''] as $text) {
            $this->assertNull($this->parser->amount($text), "\"{$text}\" is not an amount");
        }
    }

    public function test_monthly_and_yearly_columns(): void
    {
        $r = $this->parser->parse("Particulars\tYearly\tMonthly\nBasic\t600,000\t50,000\nHouse Rent\t300,000\t25,000");

        // The yearly column is the first here, so the header decides, not position.
        $this->assertSame(600000.0, $r['figures']['basic']);
        $this->assertSame(300000.0, $r['figures']['house_rent']);
    }

    public function test_year_detection(): void
    {
        $year = fn (string $t) => $this->parser->parse($t)['meta']['year'];

        $this->assertSame('2026-27', $year('Assessment Year 2026-2027'));
        $this->assertSame('2027-28', $year('Income year 2026-27'));
        $this->assertSame('2026-27', $year('for the period July 1, 2025 to June 30, 2026'));
        $this->assertNull($year('No dates here'));
    }
}
