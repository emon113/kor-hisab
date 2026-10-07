<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tax\SalaryPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private array $tax = [
        'category' => 'women_senior',
        'disabled_children' => 1,
        'new_taxpayer' => '1',
        'monthly_salary' => 150000,
        'basic_pct' => 60,
        'bonus_count' => 2,
        'bonus_base' => 'basic',
        'employer_pf' => 9000,
        'employer' => 'Acme Ltd',
    ];

    private function userWithProfile(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/account/tax', $this->tax)->assertRedirect();

        return $user->fresh();
    }

    public function test_tax_profile_is_saved(): void
    {
        $user = $this->userWithProfile();

        $this->assertSame('women_senior', $user->preferences['category']);
        $this->assertTrue($user->preferences['new_taxpayer']);
        $this->assertEquals(150000, $user->preferences['monthly_salary']);
        $this->actingAs($user)->get('/account')->assertOk()->assertSee('Acme Ltd')->assertSee('Tax profile');
    }

    public function test_tax_profile_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account/tax', ['category' => 'pirate'] + $this->tax)
            ->assertSessionHasErrorsIn('tax', 'category');
        $this->actingAs($user)->put('/account/tax', ['basic_pct' => 0] + $this->tax)
            ->assertSessionHasErrorsIn('tax', 'basic_pct');
        $this->assertNull($user->fresh()->preferences);
    }

    public function test_every_page_starts_from_the_profile(): void
    {
        $user = $this->userWithProfile();
        $annual = SalaryPackage::annual(['monthly' => 150000, 'basic_pct' => 60, 'bonus_count' => 2, 'bonus_base' => 'basic', 'employer_pf' => 9000]);

        $calc = $this->actingAs($user)->get('/')->viewData('boot')['report']['input'];
        $this->assertSame('women_senior', $calc['category']);
        $this->assertSame(1, $calc['disabled_children']);
        $this->assertTrue($calc['new_taxpayer']);
        $this->assertEquals($annual['gross'], $calc['gross_income']);   // 18,00,000 + 1,80,000 bonuses + 1,08,000 PF

        $this->assertSame('women_senior', $this->actingAs($user)->get('/target-tax')->viewData('boot')['input']['category']);
        $this->assertSame('women_senior', $this->actingAs($user)->get('/must-i-file')->viewData('boot')['category']);

        $offer = $this->actingAs($user)->get('/compare-offers')->viewData('boot')['input']['offers'][0];
        $this->assertSame('Acme Ltd', $offer['name']);
        $this->assertEquals(150000, $offer['monthly']);

        $months = $this->actingAs($user)->get('/tds-planner')->viewData('boot')['input']['months'];
        $this->assertEquals(150000, $months['jul']['salary']);
        $this->assertSame(2, collect($months)->where('bonus', 90000)->count());
    }

    public function test_a_profile_without_bonuses_places_none(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/account/tax', ['bonus_count' => 0] + $this->tax);

        $months = $this->actingAs($user)->get('/tds-planner')->viewData('boot')['input']['months'];
        $this->assertSame(0, collect($months)->where('bonus', '>', 0)->count());
    }

    public function test_guests_keep_the_defaults(): void
    {
        $this->assertSame('general', $this->get('/')->viewData('boot')['report']['input']['category']);
        $this->assertSame('Current job', $this->get('/compare-offers')->viewData('boot')['input']['offers'][0]['name']);
    }

    public function test_display_settings_follow_the_account(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->actingAs($user)->put('/account/display', ['locale' => 'bn', 'digits' => 'latin', 'grouping' => 'lakh'])
            ->assertRedirect()
            ->assertCookie('kh_locale', 'bn')
            ->assertPlainCookie('kh_digits', 'latin')
            ->assertPlainCookie('kh_grouping', 'lakh')
            ->assertSessionHas('sync_display', ['digits' => 'latin', 'grouping' => 'lakh']);

        // Without the cookie (a new device), the saved language still applies to a signed-in user.
        $this->actingAs($user->fresh())->get('/')->assertSee('<html lang="bn"', false);

        // Signing in on another device brings the settings along.
        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertCookie('kh_locale', 'bn')
            ->assertSessionHas('sync_display');
    }
}
