<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('6-digit owner PIN')
            ->assertSee('name="pin"', false)
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="email"', false);
    }

    public function test_users_can_authenticate_using_the_shared_pin(): void
    {
        $user = User::factory()->create(['email' => 'manager@example.com']);
        config([
            'manager.shared_pin' => '483927',
            'manager.user_email' => $user->email,
        ]);

        $response = $this->post('/login', ['pin' => '483927']);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_cannot_authenticate_with_an_incorrect_pin(): void
    {
        $user = User::factory()->create(['email' => 'manager@example.com']);
        config([
            'manager.shared_pin' => '483927',
            'manager.user_email' => $user->email,
        ]);

        $this->from('/login')->post('/login', ['pin' => '000000'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_pin_sign_in_is_rate_limited(): void
    {
        $user = User::factory()->create(['email' => 'manager@example.com']);
        config([
            'manager.shared_pin' => '483927',
            'manager.user_email' => $user->email,
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->from('/login')->post('/login', ['pin' => '000000'])->assertRedirect('/login');
        }

        $this->from('/login')->post('/login', ['pin' => '000000'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_password_credentials_are_not_accepted_by_pin_sign_in(): void
    {
        $user = User::factory()->create(['email' => 'manager@example.com']);
        config([
            'manager.shared_pin' => '483927',
            'manager.user_email' => $user->email,
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
