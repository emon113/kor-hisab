<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WealthStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WealthTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'receipts' => ['income_shown' => 890349, 'exempt_income' => 445175],
            'expenses' => ['food_clothing' => 600000, 'tax_paid' => 46552],
            'liabilities' => ['institutional' => 300000],
            'assets' => ['bank' => 400000, 'sanchaypatra_dps' => 700000],
            'opening_net_wealth' => 100000,
        ], $overrides);
    }

    public function test_wealth_pages_require_sign_in(): void
    {
        $this->get('/wealth')->assertRedirect('/login');
        $this->get('/wealth/2026-27')->assertRedirect('/login');
    }

    public function test_a_user_can_save_and_reopen_a_statement(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/wealth/2026-27', $this->payload())
            ->assertOk()
            ->assertJsonPath('result.net_wealth', 800000)
            ->assertJsonPath('result.expected_net_wealth', 788972)   // 1,00,000 + 13,35,524 − 6,46,552
            ->assertJsonPath('result.status', 'balanced');            // ৳11,028 gap is within 1% of sources

        $statement = $user->wealthStatements()->first();
        $this->assertSame('2026-27', $statement->tax_year);
        $this->assertSame(400000, $statement->assets['bank']);
        $this->assertSame(0, $statement->assets['vehicle']);

        $this->actingAs($user)->get('/wealth')->assertOk()->assertSee('AY 2026-27')->assertSee('৳800,000');
        $this->actingAs($user)->get('/wealth/2026-27')->assertOk()->assertSee('Save statement');
        $this->actingAs($user)->get('/wealth/2026-27/print')->assertOk()->assertSee('Statement of assets, liabilities and expenses');
    }

    public function test_next_year_carries_over_and_uses_last_years_net_wealth(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->putJson('/wealth/2026-27', $this->payload())->assertOk();

        // The new year's editor starts from last year's assets and liabilities.
        $this->actingAs($user)->get('/wealth/2027-28')->assertOk()
            ->assertSee('carried over from AY 2026-27');

        // Last year's assets − liabilities (8 lakh) is the starting point; the opening field is ignored.
        $this->actingAs($user)->putJson('/wealth/2027-28', $this->payload([
            'receipts' => ['income_shown' => 1000000, 'exempt_income' => 0],
            'expenses' => ['food_clothing' => 700000, 'tax_paid' => 0],
            'assets' => ['bank' => 800000, 'sanchaypatra_dps' => 600000],
            'opening_net_wealth' => 999999999,
        ]))->assertOk()
            ->assertJsonPath('result.previous_net_wealth', 800000)
            ->assertJsonPath('result.expected_net_wealth', 1100000)
            ->assertJsonPath('result.net_wealth', 1100000)
            ->assertJsonPath('result.status', 'balanced');
    }

    public function test_income_is_prefilled_from_a_saved_calculation_for_that_year(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/calculations', [
            'title' => 'My salary', 'year' => '2026-27', 'category' => 'general',
            'gross_income' => 1335524, 'tds_paid' => 30000,
        ])->assertCreated();

        $page = $this->actingAs($user)->get('/wealth/2026-27')->assertOk()->assertSee('My salary');
        $boot = $page->viewData('boot');
        $this->assertEquals(890349, $boot['data']['receipts']['income_shown']);
        $this->assertEquals(445175, $boot['data']['receipts']['exempt_income']);
        $this->assertEquals(30000, $boot['data']['expenses']['tax_paid']);
    }

    public function test_input_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/wealth/2026-27', $this->payload(['assets' => ['bank' => -5]]))
            ->assertStatus(422)->assertJsonValidationErrors('assets.bank');
        $this->actingAs($user)->putJson('/wealth/2026-27', $this->payload(['assets' => ['yacht' => 5]]))
            ->assertStatus(422)->assertJsonValidationErrors('assets');
        $this->actingAs($user)->get('/wealth/1990-91')->assertNotFound();
        $this->actingAs($user)->putJson('/wealth/1990-91', $this->payload())->assertNotFound();
    }

    public function test_statements_are_private_to_each_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->putJson('/wealth/2026-27', $this->payload())->assertOk();

        $this->actingAs($other)->get('/wealth')->assertOk()->assertDontSee('৳800,000');
        $this->actingAs($other)->get('/wealth/2026-27/print')->assertNotFound();
        $this->actingAs($other)->delete('/wealth/2026-27')->assertNotFound();

        // Saving the same year as another user creates their own statement.
        $this->actingAs($other)->putJson('/wealth/2026-27', $this->payload(['assets' => ['bank' => 1]]))->assertOk();
        $this->assertSame(400000, $owner->wealthStatements()->first()->assets['bank']);
        $this->assertSame(2, WealthStatement::count());

        $this->actingAs($owner)->delete('/wealth/2026-27')->assertRedirect('/wealth');
        $this->assertSame(1, WealthStatement::count());
    }

    public function test_statement_pages_render_in_bangla(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->putJson('/wealth/2026-27', $this->payload())->assertOk();

        $this->withCookie('kh_locale', 'bn')->actingAs($user)->get('/wealth')->assertOk()
            ->assertSee('সম্পদ ও দায়')->assertSee('করবর্ষ ২০২৬-২৭');
    }
}
