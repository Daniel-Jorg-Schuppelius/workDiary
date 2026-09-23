<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SessionRevocationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-17 (session-1): Austritt, SCIM-Deaktivierung und
 * Portal-Widerruf löschten nur Zeilen der Tabelle `sessions` — mit Redis als
 * Sitzungsspeicher arbeitete das Konto unbegrenzt weiter. Die Sperre gehört
 * daher in den Auth-Provider, der Passwortwechsel in die Sitzungsprüfung.
 */
final class SessionRevocationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_deactivated_user_loses_a_running_session(): void {
        $user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'is_new_system' => true,
            'password' => Hash::make('Geheim!2026'),
        ]);

        $this->post(route('login'), ['username' => $user->email, 'password' => 'Geheim!2026']);
        $this->assertAuthenticatedAs($user);
        $this->get(route('account.profile.edit'))->assertOk();

        // Austritt/SCIM-Deaktivierung von anderswo — ohne Zutun der Sitzung.
        $user->forceFill(['deactivated_at' => now()])->save();
        // Wie ein neuer Request-Prozess: der Guard löst den Nutzer neu auf.
        $this->app['auth']->forgetGuards();

        $this->get(route('account.profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_revoked_portal_user_loses_a_running_session(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $portalUser = User::factory()
            ->kunde((int) $customer->id, (int) $this->organization->id)
            ->create([
                'organization_id' => $this->organization->id,
                'email' => 'portal@example.test',
                'password' => Hash::make('Geheim!2026'),
            ]);

        $this->post(route('customer.login.attempt'), ['email' => 'portal@example.test', 'password' => 'Geheim!2026']);
        $this->assertAuthenticatedAs($portalUser, 'customer');

        $portalUser->forceFill(['deactivated_at' => now()])->save();
        $this->app['auth']->forgetGuards();

        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
    }

    /** Der Sitzungsspeicher ist dabei egal — die Sperre sitzt im Auth-Provider. */
    public function test_auth_providers_reject_deactivated_accounts_by_id(): void {
        $user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'deactivated_at' => now(),
        ]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $portalUser = User::factory()
            ->kunde((int) $customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id, 'deactivated_at' => now()]);

        $internal = new \App\Legacy\Auth\LegacyUserProvider(app(\Illuminate\Contracts\Hashing\Hasher::class));
        $portal = new \App\Auth\CustomerUserProvider(app(\Illuminate\Contracts\Hashing\Hasher::class));

        $this->assertNull($internal->retrieveById($user->getKey()));
        $this->assertNull($portal->retrieveById($portalUser->getKey()));
    }

    public function test_password_change_ends_other_sessions_regardless_of_driver(): void {
        $user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'is_new_system' => true,
            'password' => Hash::make('Geheim!2026'),
        ]);

        $this->actingAs($user)->get(route('account.profile.edit'))->assertOk();

        // Passwortwechsel von anderswo (Reset, Admin, zweite Sitzung).
        $user->forceFill(['password' => Hash::make('Neu!2026')])->save();

        $this->get(route('account.profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
