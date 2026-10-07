<?php

namespace Tests\Feature;

use App\Models\Calculation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationTest extends TestCase
{
    use RefreshDatabase;

    private array $input = [
        'year' => '2026-27',
        'category' => 'general',
        'gross_income' => 1335524,
        'investments' => ['dps' => 120000, 'savings_certificate' => 0, 'mutual_fund' => 0, 'shares' => 0],
        'tds_paid' => 30000,
        'filing' => 'standard',
    ];

    public function test_anyone_can_calculate(): void
    {
        $this->postJson('/calculate', $this->input)
            ->assertOk()
            ->assertJsonPath('summary.liability', 46552)
            ->assertJsonPath('summary.payable', 16552)
            ->assertJsonStructure(['summary', 'slabs', 'investments', 'predictions', 'scenarios', 'charts' => ['heatmap', 'rate_curve', 'future']]);
    }

    public function test_calculation_input_is_validated(): void
    {
        $this->postJson('/calculate', ['year' => '1999-00', 'category' => 'general', 'gross_income' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year', 'gross_income']);
    }

    public function test_guests_cannot_save(): void
    {
        $this->postJson('/calculations', $this->input + ['title' => 'Mine'])->assertUnauthorized();
    }

    public function test_a_user_can_save_update_and_delete(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user)->postJson('/calculations', $this->input + ['title' => 'Current job'])
            ->assertCreated()
            ->json('calculation.id');

        $calc = Calculation::findOrFail($id);
        $this->assertSame(46552, $calc->liability);
        $this->assertSame('Current job', $calc->title);

        $this->actingAs($user)->putJson("/calculations/{$id}", ['gross_income' => 1600000, 'title' => 'After raise'] + $this->input)
            ->assertOk();
        $this->assertSame('After raise', $calc->fresh()->title);
        $this->assertSame(1600000, $calc->fresh()->gross_income);

        $this->actingAs($user)->get("/calculations/{$id}")->assertOk()->assertSee('After raise');
        $this->actingAs($user)->get('/calculations')->assertOk()->assertSee('After raise');

        $this->actingAs($user)->delete("/calculations/{$id}")->assertRedirect('/calculations');
        $this->assertModelMissing($calc);
    }

    public function test_users_cannot_touch_each_others_calculations(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->actingAs($owner)->postJson('/calculations', $this->input + ['title' => 'Private'])->json('calculation.id');

        $this->actingAs($other)->get("/calculations/{$id}")->assertNotFound();
        $this->actingAs($other)->putJson("/calculations/{$id}", $this->input + ['title' => 'Hacked'])->assertNotFound();
        $this->actingAs($other)->delete("/calculations/{$id}")->assertNotFound();
        $this->assertSame('Private', Calculation::find($id)->title);
    }

    public function test_two_calculations_can_be_compared(): void
    {
        $user = User::factory()->create();
        $a = $this->actingAs($user)->postJson('/calculations', $this->input + ['title' => 'Now'])->json('calculation.id');
        $b = $this->actingAs($user)->postJson('/calculations', ['gross_income' => 1800000, 'title' => 'Offer'] + $this->input)->json('calculation.id');

        $this->actingAs($user)->get("/calculations/compare?a={$a}&b={$b}")
            ->assertOk()->assertSee('Now')->assertSee('Offer');
    }

    public function test_target_tax_solver_endpoint(): void
    {
        $this->postJson('/target-tax/solve', [
            'target_tax' => 50000, 'rebate_mode' => 'none', 'year' => '2026-27', 'category' => 'general',
        ])->assertOk()->assertJsonPath('gross', 1250000)->assertJsonPath('tax', 50000);
    }

    public function test_user_can_save_default_salary_ratios(): void
    {
        $user = User::factory()->create();
        $ratios = ['basic' => 60, 'house_rent' => 30, 'medical' => 5, 'conveyance' => 5, 'festival_bonus' => 0, 'other_bonus' => 0, 'overtime' => 0];

        $this->actingAs($user)->postJson('/target-tax/ratios', ['ratios' => $ratios])->assertOk();

        $this->assertEquals(60, $user->fresh()->salary_ratios['basic']);
    }
}
