<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantRegistrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Enums\User\UserRole;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    // ── Feature-Flag deaktiviert (Standard) ─────────────────────────────────

    public function test_register_returns_404_when_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $this->get(route('register'))->assertNotFound();
        $this->post(route('register'))->assertNotFound();
    }

    // ── Formular abrufbar ────────────────────────────────────────────────────

    public function test_register_form_shown_when_enabled(): void
    {
        config(['app.registration_enabled' => true]);

        $this->get(route('register'))
            ->assertOk()
            ->assertViewIs('auth.register');
    }

    public function test_authenticated_user_is_redirected_from_register(): void
    {
        config(['app.registration_enabled' => true]);
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('register'))
            ->assertRedirect(route('dashboard'));
    }

    // ── Erfolgreiche Registrierung ───────────────────────────────────────────

    public function test_registration_creates_org_and_admin_user(): void
    {
        config(['app.registration_enabled' => true]);

        $response = $this->post(route('register'), [
            'org_name' => 'Test GmbH',
            'name' => 'Max Mustermann',
            'email' => 'max@test.de',
            'password' => 'Password123!Strong',
            'password_confirmation' => 'Password123!Strong',
        ]);

        $response->assertRedirect(route('dashboard'));

        // Organisation wurde angelegt
        $org = Organization::where('name', 'Test GmbH')->first();
        $this->assertNotNull($org);
        $this->assertSame(Organization::PLAN_FREE, $org->plan);
        $this->assertTrue($org->is_active);

        // User wurde angelegt und hat Admin-Rolle
        $user = User::where('email', 'max@test.de')->first();
        $this->assertNotNull($user);
        $this->assertSame($org->id, $user->organization_id);
        $this->assertTrue($user->hasRole(UserRole::Admin->value));
        $this->assertSame($user->id, $org->fresh()->owner_id);

        // User ist eingeloggt
        $this->assertAuthenticatedAs($user);
    }

    // ── Validierungsfehler ───────────────────────────────────────────────────

    public function test_registration_requires_all_fields(): void
    {
        config(['app.registration_enabled' => true]);

        $this->post(route('register'), [])
            ->assertSessionHasErrors(['org_name', 'name', 'email', 'password']);
    }

    public function test_registration_requires_unique_email(): void
    {
        config(['app.registration_enabled' => true]);
        User::factory()->create(['email' => 'taken@test.de']);

        $this->post(route('register'), [
            'org_name' => 'Neue Firma',
            'name' => 'Hans',
            'email' => 'taken@test.de',
            'password' => 'Password123!Strong',
            'password_confirmation' => 'Password123!Strong',
        ])->assertSessionHasErrors('email');
    }

    public function test_registration_requires_password_confirmation(): void
    {
        config(['app.registration_enabled' => true]);

        $this->post(route('register'), [
            'org_name' => 'Firma',
            'name' => 'Hans',
            'email' => 'hans@test.de',
            'password' => 'Password123!Strong',
            'password_confirmation' => 'wrong',
        ])->assertSessionHasErrors('password');
    }

    public function test_login_page_shows_register_link_when_enabled(): void
    {
        config(['app.registration_enabled' => true]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('register'));
    }

    public function test_login_page_hides_register_link_when_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee(route('register'));
    }
}
