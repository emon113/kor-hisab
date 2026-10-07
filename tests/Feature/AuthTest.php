<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_render_for_guests(): void
    {
        $this->get('/')->assertOk()->assertSee('Your details');
        $this->get('/target-tax')->assertOk()->assertSee('Work backwards from a tax amount');
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_saved_calculations_require_sign_in(): void
    {
        $this->get('/calculations')->assertRedirect('/login');
    }

    public function test_a_visitor_can_register(): void
    {
        $this->post('/register', [
            'name' => 'Emon',
            'email' => 'emon@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'emon@example.com']);
    }

    public function test_registration_rejects_weak_passwords(): void
    {
        $this->post('/register', [
            'name' => 'Emon', 'email' => 'emon@example.com', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_a_user_can_sign_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'secret123',
            'password' => 'newpass456',
            'password_confirmation' => 'newpass456',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('newpass456', $user->fresh()->password));
    }

    public function test_seeder_creates_the_owner_account_once(): void
    {
        config(['korhishab.seed_user' => ['name' => 'Emon', 'email' => 'owner@example.com', 'password' => 'secret123']]);

        $this->seed();
        $this->seed();

        $this->assertSame(1, User::where('email', 'owner@example.com')->count());
        $this->assertSame(1, User::where('email', 'owner@example.com')->first()->calculations()->count());
    }
}
