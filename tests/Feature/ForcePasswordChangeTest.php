<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_user_with_flag_is_redirected_to_password_change(): void
    {
        $user = User::factory()->user()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('account.password.edit'));
    }

    public function test_password_change_page_itself_is_reachable(): void
    {
        $user = User::factory()->user()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->get(route('account.password.edit'))
            ->assertOk();
    }

    public function test_forced_change_does_not_require_current_password(): void
    {
        $user = User::factory()->user()->create(['must_change_password' => true]);

        $this->actingAs($user)->post(route('account.password.update'), [
            'password' => 'NeuesPasswort123!',
            'password_confirmation' => 'NeuesPasswort123!',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NeuesPasswort123!', $user->password));
    }

    public function test_regular_change_requires_current_password(): void
    {
        $user = User::factory()->user()->create([
            'password' => 'AltesPasswort1',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->post(route('account.password.update'), [
            'password' => 'NeuesPasswort123!',
            'password_confirmation' => 'NeuesPasswort123!',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'AltesPasswort1',
            'password' => 'NeuesPasswort123!',
            'password_confirmation' => 'NeuesPasswort123!',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_user_without_flag_can_browse_normally(): void
    {
        $user = User::factory()->user()->create(['must_change_password' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
