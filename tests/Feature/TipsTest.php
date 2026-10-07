<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_page_renders(): void
    {
        $this->get('/save-tax')->assertOk()
            ->assertSee('Pay less tax, legally')
            ->assertSee('Claim your provident fund contributions')
            ->assertDontSee('For you');
        $this->withCookie('kh_locale', 'bn')->get('/save-tax')->assertOk()->assertSee('বৈধভাবে কম কর দিন');
    }

    public function test_signed_in_users_with_a_salary_see_their_own_savings(): void
    {
        $user = User::factory()->create(['preferences' => ['monthly_salary' => 150000, 'bonus_count' => 2, 'basic_pct' => 60, 'bonus_base' => 'basic']]);

        $this->actingAs($user)->get('/save-tax')->assertOk()->assertSee('For you')->assertSee('less tax');
    }

    public function test_calculator_results_include_tips(): void
    {
        $this->postJson('/calculate', ['year' => '2026-27', 'category' => 'general', 'gross_income' => 1800000])
            ->assertOk()
            ->assertJsonPath('tips.0.id', 'rebate_gap')
            ->assertJsonStructure(['tips' => [['id', 'kind', 'icon', 'title', 'about', 'law', 'saving']]]);
    }
}
