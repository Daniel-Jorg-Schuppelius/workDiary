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
use App\Support\MorphMap;
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

    /**
     * Mandanten-Review 2026-09-13: Der Controller entschied die SSO-Pflicht über
     * einen ZWEITEN, mandantenübergreifenden Lookup nach dem Anmeldenamen
     * (`where('name', …)->orWhere('email', …)->first()`). Angemeldet wird aber
     * das Konto, das die Passwortprüfung ergeben hat — der Provider sucht dafür
     * ausschließlich über die (eindeutige) E-Mail. `users.name` ist NICHT
     * eindeutig: trug ein Konto eines anderen Mandanten als Namen die E-Mail
     * dieses Nutzers, war offen, welche der beiden Zeilen die Abfrage lieferte.
     * Fiel die Wahl auf das fremde Konto und war dieses `sso_exempt` bzw. seine
     * Organisation ohne Zwang, entfiel die Umleitung und der SSO-pflichtige
     * Nutzer kam mit Passwort herein (die Provider-Sperre ist für die
     * Passwortprüfung ausgesetzt, danach folgt direkt `Auth::login()`).
     *
     * Der Test bildet diese Mehrdeutigkeit nach und hält fest, dass die
     * Entscheidung am authentifizierten Konto hängt: Beide Organisationen
     * erzwingen SSO, umgeleitet werden muss zur EIGENEN. Er reproduziert den
     * alten Zustand nicht zwingend — welche Zeile die Abfrage lieferte, hing am
     * Ausführungsplan; genau diese Unbestimmtheit war der Mangel.
     */
    public function test_the_sso_decision_follows_the_authenticated_account(): void {
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        $this->enforcedConnection();
        $enforced = $this->makeUser();

        // Fremder Mandant, ebenfalls SSO-pflichtig: sein Anzeigename ist die
        // E-Mail-Adresse des obigen Kontos (so provisionieren Verzeichnisdienste).
        $other = Organization::factory()->create(['plan' => Organization::PLAN_ENTERPRISE]);
        $this->enforcedConnection($other->id);
        User::factory()->create([
            'organization_id' => $other->id,
            'name' => $enforced->email,
            'password' => bcrypt('anderes-passwort'),
            'is_new_system' => true,
        ]);

        $this->post('/login', [
            'username' => $enforced->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('sso.start', ['slug' => $this->organization->slug]));

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
                ->where('auditable_type', MorphMap::stableKey(SsoConnection::class))
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
