<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sso;

use App\Enums\Auth\SsoProtocol;
use App\Models\{Organization, SsoConnection, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Admin-Verwaltung der OIDC-/SAML-Verbindungen (Feature 057): Anlegen/
 * Aktualisieren je Protokoll, Secret-Handling (leer = unverändert, nie ''),
 * SSRF-Leitplanke, Verbindungstest, Break-Glass-Toggle und Mandantengrenze.
 */
final class SsoAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_admin_can_create_oidc_connection(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.sso.connections.save'), [
                'protocol' => 'oidc',
                'label' => 'Entra ID',
                'issuer' => 'https://login.example.org/tenant',
                'client_id' => 'client-1',
                'client_secret' => 'top-secret',
                'active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $connection = SsoConnection::query()->where('protocol', SsoProtocol::Oidc->value)->firstOrFail();
        $this->assertSame('client-1', $connection->client_id);
        $this->assertSame('top-secret', $connection->client_secret);
        $this->assertTrue($connection->active);
        // Secret niemals serialisieren (Audit/JSON).
        $this->assertArrayNotHasKey('client_secret', $connection->toArray());
    }

    public function test_empty_secret_keeps_stored_secret(): void {
        $connection = SsoConnection::query()->create([
            'organization_id' => $this->organization->id,
            'protocol' => SsoProtocol::Oidc->value,
            'label' => 'Entra ID',
            'issuer' => 'https://login.example.org/tenant',
            'client_id' => 'client-1',
            'client_secret' => 'keep-me',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.sso.connections.save'), [
                'protocol' => 'oidc',
                'label' => 'Entra ID (neu)',
                'issuer' => 'https://login.example.org/tenant',
                'client_id' => 'client-1',
                'client_secret' => '',
            ])
            ->assertRedirect();

        $this->assertSame('keep-me', $connection->fresh()?->client_secret);
    }

    public function test_private_issuer_requires_optin(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.sso.connections.save'), [
                'protocol' => 'oidc',
                'label' => 'Internes Keycloak',
                'issuer' => 'https://192.168.1.10/realms/firma',
                'client_id' => 'client-1',
            ])
            ->assertSessionHasErrors('issuer');

        $this->actingAs($this->admin)
            ->post(route('admin.sso.connections.save'), [
                'protocol' => 'oidc',
                'label' => 'Internes Keycloak',
                'issuer' => 'https://192.168.1.10/realms/firma',
                'client_id' => 'client-1',
                'allow_private_network' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_saml_connection_requires_certificate(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.sso.connections.save'), [
                'protocol' => 'saml',
                'label' => 'ADFS',
                'idp_entity_id' => 'https://adfs.example.org/saml',
                'idp_sso_url' => 'https://adfs.example.org/saml/sso',
            ])
            ->assertSessionHasErrors('idp_certificate');
    }

    public function test_connection_test_reports_discovery_result(): void {
        $connection = SsoConnection::query()->create([
            'organization_id' => $this->organization->id,
            'protocol' => SsoProtocol::Oidc->value,
            'label' => 'Entra ID',
            'issuer' => 'https://idp.example',
            'client_id' => 'client-1',
        ]);

        Http::fake([
            'https://idp.example/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://idp.example',
                'authorization_endpoint' => 'https://idp.example/authorize',
                'token_endpoint' => 'https://idp.example/token',
                'jwks_uri' => 'https://idp.example/jwks',
            ]),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.sso.connections.test', $connection->sqid))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_destroy_removes_connection_and_identities(): void {
        $connection = SsoConnection::query()->create([
            'organization_id' => $this->organization->id,
            'protocol' => SsoProtocol::Oidc->value,
            'label' => 'Entra ID',
            'issuer' => 'https://idp.example',
            'client_id' => 'client-1',
        ]);
        \App\Models\SsoIdentity::query()->create([
            'sso_connection_id' => $connection->id,
            'user_id' => $this->admin->id,
            'subject' => 'subject-1',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.sso.connections.destroy', $connection->sqid))
            ->assertRedirect();

        $this->assertDatabaseMissing('sso_connections', ['id' => $connection->id]);
        $this->assertDatabaseMissing('sso_identities', ['sso_connection_id' => $connection->id]);
    }

    public function test_break_glass_toggle_is_org_scoped(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.sso.break-glass.toggle'), ['user' => $user->sqid])
            ->assertRedirect();
        $this->assertTrue((bool) $user->fresh()?->sso_exempt);

        // Fremder Mandant: 404, keine Änderung.
        $foreign = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAs($this->admin)
            ->post(route('admin.sso.break-glass.toggle'), ['user' => $foreign->sqid])
            ->assertNotFound();
        $this->assertFalse((bool) $foreign->fresh()?->sso_exempt);
    }

    public function test_non_admin_cannot_manage_connections(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('admin.sso.connections.save'), [
                'protocol' => 'oidc',
                'label' => 'X',
                'issuer' => 'https://idp.example',
                'client_id' => 'c',
            ])
            ->assertForbidden();
    }

    public function test_admin_page_shows_connection_sections(): void {
        $this->actingAs($this->admin)
            ->get(route('admin.sso.index'))
            ->assertOk()
            ->assertSee(__('sso.oidc_heading'))
            ->assertSee(__('sso.saml_heading'))
            ->assertSee(__('sso.break_glass_heading'));
    }
}
