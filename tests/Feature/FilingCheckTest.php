<?php

namespace Tests\Feature;

use Tests\TestCase;

class FilingCheckTest extends TestCase
{
    public function test_page_renders_in_english_and_bangla(): void
    {
        $this->get('/must-i-file')->assertOk()
            ->assertSee('Do I need to file a return?')
            ->assertSee('Employees and shareholder directors of a company must file');
        $this->withCookie('kh_locale', 'bn')->get('/must-i-file')->assertOk()
            ->assertSee('আমাকে কি রিটার্ন দিতে হবে?');
    }

    public function test_income_test_uses_the_tax_rules_for_each_category(): void
    {
        $categories = collect($this->get('/must-i-file')->viewData('boot')['categories'])->keyBy('key');

        // Tax starts where taxable income (salary less the ⅓ exemption) passes the tax-free limit.
        $this->assertEquals(400000, $categories['general']['threshold']);
        $this->assertEquals(600000, $categories['general']['starts_at']);
        $this->assertEquals(675000, $categories['women_senior']['starts_at']);
        $this->assertEquals(825000, $categories['freedom_fighter']['starts_at']);
    }

    public function test_every_rule_is_complete_and_sourced(): void
    {
        $check = config('filing_check');

        $this->assertNotEmpty($check['sources']);
        foreach ($check['sources'] as $source) {
            $this->assertStringStartsWith('https://', $source['url']);
        }
        $ids = [];
        foreach ($check['obligations'] as $rule) {
            $this->assertNotEmpty($rule['id']);
            $this->assertNotEmpty($rule['question']);
            $this->assertNotEmpty($rule['reason']);
            $ids[] = $rule['id'];
        }
        $this->assertSame($ids, array_unique($ids));
        $this->assertNotEmpty($check['psr_services']);
        $this->assertNotEmpty($check['tin_only']);
    }
}
