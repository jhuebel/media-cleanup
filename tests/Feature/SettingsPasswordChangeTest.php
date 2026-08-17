<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_updates_the_password_when_current_password_is_correct(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('settings')
            ->set('current_password', 'password')
            ->set('new_password', 'a-new-strong-password')
            ->set('new_password_confirmation', 'a-new-strong-password')
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('a-new-strong-password', $this->admin()->fresh()->password));
    }

    public function test_rejects_the_change_when_current_password_is_wrong(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('settings')
            ->set('current_password', 'not-the-password')
            ->set('new_password', 'a-new-strong-password')
            ->set('new_password_confirmation', 'a-new-strong-password')
            ->call('changePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $this->admin()->fresh()->password));
    }

    public function test_rejects_a_mismatched_confirmation(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('settings')
            ->set('current_password', 'password')
            ->set('new_password', 'a-new-strong-password')
            ->set('new_password_confirmation', 'does-not-match')
            ->call('changePassword')
            ->assertHasErrors('new_password');

        $this->assertTrue(Hash::check('password', $this->admin()->fresh()->password));
    }

    public function test_username_cannot_be_changed_through_mass_assignment(): void
    {
        $admin = $this->admin();

        $admin->update(['username' => 'someone-else']);

        $this->assertSame('admin', $admin->fresh()->username);
    }
}
