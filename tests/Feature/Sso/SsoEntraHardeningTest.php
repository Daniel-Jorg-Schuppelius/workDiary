<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoEntraHardeningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sso;

use App\Enums\Auth\SsoProtocol;
use App\Models\{Organization, SsoConnection, SsoIdentity, User};
use App\Services\Auth\Sso\{EntraIssuer, SsoLoginException, SsoLoginService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Entra-Härtung + JIT-Provisioning (Feature 057-Ausbau, MS365-Plan G1/G2):
 * tenant-spezifischer Issuer als Konfigurations-Guard, E-Mail-Linking für
 * Entra gesperrt (nOAuth — auch zur Laufzeit für Alt-Konfigurationen), JIT
 * legt NEUE Konten mit Standardrolle an, verknüpft aber niemals bestehende
 * (E-Mail-Kollision ⇒ Ablehnung) und respektiert das Lizenz-Nutzerlimit.
 */
final class SsoEntraHardeningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function connection(array $attributes = []): SsoConnection {
        return SsoConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'protocol' => SsoProtocol::Oidc->value,
            'label' => 'Test-IdP',
            'active' => true,
            'issuer' => 'https://idp.example.org',
            'client_id' => 'client-1',
        ]);
    }

    // ── EntraIssuer-Heuristik ───────────────────────────────────────────

    public function test_entra_issuer_detection_and_tenant_specificity(): void {
        $this->assertTrue(EntraIssuer::isEntra('https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/v2.0'));
        $this->assertTrue(EntraIssuer::isEntra('https://sts.windows.net/11111111-2222-3333-4444-555555555555/'));
        $this->assertFalse(EntraIssuer::isEntra('https://idp.example.org'));

        $this->assertTrue(EntraIssuer::isTenantSpecific('https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/v2.0'));
        $this->assertFalse(EntraIssuer::isTenantSpecific('https://login.microsoftonline.com/common/v2.0'));
        $this->assertFalse(EntraIssuer::isTenantSpecific('https://login.microsoftonline.com/organizations/v2.0'));
        $this->assertFalse(EntraIssuer::isTenantSpecific('https://login.microsoftonline.com/consumers/v2.0'));
    }

    // ── Konfigurations-Guards (Admin) ───────────────────────────────────

    public function test_admin_rejects_multi_tenant_entra_issuer(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.sso.connections.save'), [
                'protocol' => 'oidc',
                'label' => 'Entra ID',
                'issuer' => 'https://login.microsoftonline.com/common/v2.0',
                'client_id' => 'client-1',
                'client_secret' => 'top-secret',
            ])
            ->assertSessionHasErrors(['issuer']);

        $this->assertSame(0, SsoConnection::query()->count());
    }

    public function test_admin_rejects_email_link_for_entra_connections(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.sso.connections.save'), [
                'protocol' => 'oidc',
                'label' => 'Entra ID',
                'issuer' => 'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/v2.0',
                'client_id' => 'client-1',
                'client_secret' => 'top-secret',
                'allow_email_link' => '1',
            ])
            ->assertSessionHasErrors(['allow_email_link']);

        // Ohne E-Mail-Linking ist der tenant-spezifische Issuer zulässig.
        $this->actingAs($this->admin)
            ->post(route('admin.sso.connections.save'), [
                'protocol' => 'oidc',
                'label' => 'Entra ID',
                'issuer' => 'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/v2.0',
                'client_id' => 'client-1',
                'client_secret' => 'top-secret',
                'jit_provisioning' => '1',
                'jit_role' => 'user',
            ])
            ->assertSessionHas('success');

        $connection = SsoConnection::query()->firstOrFail();
        $this->assertTrue($connection->jit_provisioning);
        $this->assertSame('user', $connection->jit_role);
    }

    // ── Laufzeit-Abwehr (Alt-Konfigurationen) ───────────────────────────

    public function test_email_link_is_refused_at_runtime_for_entra_issuer(): void {
        $connection = $this->connection([
            'issuer' => 'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/v2.0',
            'allow_email_link' => true, // Alt-Konfiguration vor dem Guard
        ]);
        User::factory()->create(['organization_id' => $this->organization->id, 'email' => 'opfer@firma.example']);

        $this->expectException(SsoLoginException::class);
        app(SsoLoginService::class)->resolveUser($connection, ['subject' => 'attacker-sub', 'email' => 'opfer@firma.example']);
    }

    // ── JIT-Provisioning (G2) ───────────────────────────────────────────

    public function test_jit_disabled_keeps_rejecting_unknown_identities(): void {
        $connection = $this->connection();

        $this->expectException(SsoLoginException::class);
        app(SsoLoginService::class)->resolveUser($connection, ['subject' => 'neu-1', 'email' => 'neu@firma.example']);
    }

    public function test_jit_creates_new_user_with_default_role_once(): void {
        $connection = $this->connection(['jit_provisioning' => true, 'jit_role' => 'user']);

        $user = app(SsoLoginService::class)->resolveUser($connection, [
            'subject' => 'neu-1', 'email' => 'Neu@Firma.example', 'name' => 'Neue Person',
        ]);

        $this->assertSame('neu@firma.example', $user->email);
        $this->assertSame('Neue Person', $user->name);
        $this->assertSame($this->organization->id, $user->organization_id);
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertTrue($user->hasRole('user'));
        $this->assertDatabaseHas('sso_identities', ['sso_connection_id' => $connection->id, 'user_id' => $user->id, 'subject' => 'neu-1']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'sso.user_provisioned']);

        // Zweiter Login derselben Identität: kein zweites Konto.
        $again = app(SsoLoginService::class)->resolveUser($connection, ['subject' => 'neu-1', 'email' => 'neu@firma.example']);
        $this->assertSame($user->id, $again->id);
        $this->assertSame(1, SsoIdentity::query()->where('sso_connection_id', $connection->id)->count());
    }

    public function test_jit_rejects_email_collision_instead_of_linking(): void {
        $connection = $this->connection(['jit_provisioning' => true]);
        $existing = User::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kollision@firma.example']);

        try {
            app(SsoLoginService::class)->resolveUser($connection, ['subject' => 'fremd-1', 'email' => 'kollision@firma.example']);
            $this->fail('SsoLoginException erwartet.');
        } catch (SsoLoginException) {
            // erwartet — niemals stilles Verknüpfen (nOAuth-Schutz).
        }

        $this->assertSame(0, SsoIdentity::query()->where('user_id', $existing->id)->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'sso.login_rejected']);
    }

    public function test_jit_requires_email_claim(): void {
        $connection = $this->connection(['jit_provisioning' => true]);

        $this->expectException(SsoLoginException::class);
        app(SsoLoginService::class)->resolveUser($connection, ['subject' => 'ohne-mail', 'email' => null]);
    }
}
