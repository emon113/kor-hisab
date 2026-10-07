<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnGuideTest extends TestCase
{
    use RefreshDatabase;

    private array $input = [
        'year' => '2026-27',
        'category' => 'general',
        'gross_income' => 1335524,
        'investments' => ['dps' => 120000],
        'tds_paid' => 30000,
        'filing' => 'standard',
    ];

    public function test_blank_guide_lists_the_form_lines(): void
    {
        $this->get('/return-guide')->assertOk()
            ->assertSee('Where each number goes on your return')
            ->assertSee('Gross Tax on Taxable Income')
            ->assertSee('Open this guide from the calculator');
    }

    public function test_guide_shows_figures_from_the_calculator_inputs(): void
    {
        $this->get('/return-guide?'.http_build_query($this->input))->assertOk()
            ->assertSee('৳890,349')     // taxable salary on line 1 / Schedule 1 line 15
            ->assertSee('৳46,552')      // tax for the year
            ->assertSee('৳16,552');     // still to pay after TDS
    }

    public function test_invalid_inputs_are_rejected(): void
    {
        $this->get('/return-guide?gross_income=-1&year=2026-27&category=general')
            ->assertRedirect()
            ->assertSessionHasErrors('gross_income');
    }

    public function test_saved_calculation_guide_is_owner_only(): void
    {
        $owner = User::factory()->create();
        $id = $this->actingAs($owner)->postJson('/calculations', $this->input + ['title' => 'Mine'])->json('calculation.id');

        $this->actingAs($owner)->get("/calculations/{$id}/return-guide")->assertOk()->assertSee('৳46,552')->assertSee('Mine');
        $this->actingAs(User::factory()->create())->get("/calculations/{$id}/return-guide")->assertNotFound();
    }

    public function test_guide_renders_in_bangla(): void
    {
        $this->withCookie('kh_locale', 'bn')->get('/return-guide?'.http_build_query($this->input))->assertOk()
            ->assertSee('রিটার্নের কোন ঘরে কোন সংখ্যা বসবে')
            ->assertSee('৳৮৯০,৩৪৯');
    }
}
