<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sso;

use App\Enums\Auth\SsoProtocol;
use App\Models\{AuditLog, Organization, SsoConnection, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * SSO-Pflicht (Feature 057, DoD MVP-120): erzwingt eine Organisation SSO,
 * ist der Passwort-Login serverseitig gesperrt (LegacyUserProvider) — nur
 * Break-Glass-Konten (users.sso_exempt) dürfen weiter lokal anmelden, jede
 * Nutzung wird auditiert. Andere Mandanten bleiben unbeeinflusst.
 */
final class SsoEnforcementTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function enforcedConnection(?int $organizationId = null): SsoConnection {
        return SsoConnection::query()->create([
            'organization_id' => $organizationId ?? $this->organization->id,
            'protocol' => SsoProtocol::Oidc->value,
            'label' => 'Enforced IdP',
            'active' => true,
            'enforced' => true,
            'issuer' => 'https://idp.example',
            'client_id' => 'client',
        ]);
    }

    private function makeUser(array $attributes = []): User {
        return User::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'password' => bcrypt('secret-password'),
            'is_new_system' => true,
        ], $attributes));
    }

    public function test_password_login_is_blocked_when_sso_is_enforced(): void {
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        $this->enforcedConnection();
        $user = $this->makeUser();

        $response = $this->post('/login', [
            'username' => $user->email,
            'password' => 'secret-password',
        ]);

        // Freundliche Umleitung zum SSO-Start statt Fehlermeldung.
        $response->assertRedirect(route('sso.start', ['slug' => $this->organization->slug]));
        $this->assertGuest();
    }

    public function test_provider_blocks_password_even_without_controller_redirect(): void {
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        $this->enforcedConnection();
        $user = $this->makeUser();

        // Harte Sperre im Provider — unabhängig vom Controller-Vorab-Check.
        $provider = new \App\Legacy\Auth\LegacyUserProvider(app('hash'));
        $this->assertFalse(
            $provider->validateCredentials($user, ['password' => 'secret-password']),
            'Der Auth-Provider muss den Passwort-Login bei SSO-Pflicht ablehnen.'
        );
    }

    public function test_break_glass_account_can_still_login_and_is_audited(): void {
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        $connection = $this->enforcedConnection();
        $user = $this->makeUser(['sso_exempt' => true]);

        $response = $this->post('/login', [
            'username' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_type', SsoConnection::class)
                ->where('auditable_id', $connection->id)
                ->where('event', 'sso.break_glass_used')
                ->exists(),
            'Break-Glass-Anmeldung muss auditiert werden.'
        );
    }

    public function test_inactive_or_unenforced_connection_does_not_block(): void {
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        SsoConnection::query()->create([
            'organization_id' => $this->organization->id,
            'protocol' => SsoProtocol::Oidc->value,
            'label' => 'Optional IdP',
            'active' => true,
            'enforced' => false,
            'issuer' => 'https://idp.example',
            'client_id' => 'client',
        ]);
        $user = $this->makeUser();

        $this->post('/login', [
            'username' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_enforcement_does_not_affect_other_tenants(): void {
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        $otherOrg = Organization::factory()->create();
        $this->enforcedConnection($otherOrg->id);
        $user = $this->makeUser();

        $this->post('/login', [
            'username' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }
}
