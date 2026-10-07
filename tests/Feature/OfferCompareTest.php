<?php

namespace Tests\Feature;

use Tests\TestCase;

class OfferCompareTest extends TestCase
{
    private array $input = [
        'year' => '2026-27',
        'category' => 'general',
        'investment_mode' => 'none',
        'offers' => [
            ['name' => 'Now', 'monthly' => 100000, 'basic_pct' => 60, 'bonus_count' => 2, 'bonus_base' => 'basic'],
            ['name' => 'Offer', 'monthly' => 120000, 'basic_pct' => 60, 'bonus_count' => 2, 'bonus_base' => 'basic'],
        ],
    ];

    public function test_page_renders_in_english_and_bangla(): void
    {
        $this->get('/compare-offers')->assertOk()->assertSee('Compare job offers')->assertSee('Best take-home');
        $this->withCookie('kh_locale', 'bn')->get('/compare-offers')->assertOk()->assertSee('চাকরির অফার তুলনা');
    }

    public function test_compute_endpoint(): void
    {
        $this->postJson('/compare-offers/compute', $this->input)
            ->assertOk()
            ->assertJsonPath('best', 1)
            ->assertJsonPath('offers.0.gross', 1320000)
            ->assertJsonPath('offers.1.gross', 1584000)
            ->assertJsonStructure(['offers' => [['name', 'tax', 'take_home_monthly', 'total_value', 'vs_first']]]);
    }

    public function test_two_or_three_offers_only(): void
    {
        $one = $this->input;
        $one['offers'] = [$this->input['offers'][0]];
        $this->postJson('/compare-offers/compute', $one)->assertStatus(422)->assertJsonValidationErrors('offers');

        $four = $this->input;
        $four['offers'] = array_fill(0, 4, $this->input['offers'][0]);
        $this->postJson('/compare-offers/compute', $four)->assertStatus(422)->assertJsonValidationErrors('offers');

        $bad = $this->input;
        $bad['offers'][1]['basic_pct'] = 0;
        $this->postJson('/compare-offers/compute', $bad)->assertStatus(422)->assertJsonValidationErrors('offers.1.basic_pct');
    }
}
