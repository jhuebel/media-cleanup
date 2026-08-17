<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_guests_are_redirected_to_login_from_protected_routes(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/jobs')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_the_seeded_admin_account_has_the_expected_default_credentials(): void
    {
        $admin = $this->admin();

        $this->assertSame('admin', $admin->username);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_login_with_correct_credentials_succeeds(): void
    {
        Livewire::test('login')
            ->set('username', 'admin')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->admin());
    }

    public function test_login_with_incorrect_password_fails(): void
    {
        Livewire::test('login')
            ->set('username', 'admin')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        RateLimiter::clear('admin|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            Livewire::test('login')
                ->set('username', 'admin')
                ->set('password', 'wrong-password')
                ->call('login');
        }

        Livewire::test('login')
            ->set('username', 'admin')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session_and_protected_routes_redirect_again(): void
    {
        $this->actingAs($this->admin());

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->get('/')->assertRedirect('/login');
    }
}
