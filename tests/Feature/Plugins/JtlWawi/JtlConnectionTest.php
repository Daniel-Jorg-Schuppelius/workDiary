<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlConnectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\JtlWawi;

use App\Models\{JtlConnection, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 078, MVP-317: Verbindung beider Betriebsarten — SSRF-Leitplanke
 * mit auditiertem Private-Network-Opt-in, App-Registrierung mit einmaligem
 * API-Key (verschlüsselt at-rest, nie in Payloads), Scope-Preflight mit
 * sichtbarem Blocked-State, Cloud-Token-Austausch.
 */
final class JtlConnectionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const BASE = 'https://192.168.10.20:5883/api/eazybusiness';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_non_admin_cannot_open_admin_page(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('admin.jtl.index'))->assertForbidden();
    }

    public function test_private_base_url_is_blocked_without_optin(): void {
        $response = $this->actingAs($this->admin)->post(route('admin.jtl.connection.store'), [
            'mode' => JtlConnection::MODE_ON_PREMISE,
            'base_url' => self::BASE,
            'api_version' => '2.0',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('jtl_connections', ['organization_id' => $this->organization->id]);
    }

    public function test_private_base_url_is_allowed_with_audited_optin(): void {
        $response = $this->actingAs($this->admin)->post(route('admin.jtl.connection.store'), [
            'mode' => JtlConnection::MODE_ON_PREMISE,
            'base_url' => self::BASE,
            'api_version' => '2.0',
            'allow_private_network' => '1',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('jtl_connections', [
            'organization_id' => $this->organization->id,
            'mode' => JtlConnection::MODE_ON_PREMISE,
            'allow_private_network' => true,
            'status' => JtlConnection::STATUS_DRAFT,
        ]);
    }

    public function test_registration_flow_stores_encrypted_api_key_and_activates(): void {
        $connection = $this->makeOnPremiseConnection();

        $fake = FakePluginHttp::fake([
            self::BASE . '/v2/authentication/*' => FakePluginHttp::response([
                'requestStatusInfo' => ['status' => 2],
                'token' => ['apiKey' => 'KEY-SECRET-123'],
                'grantedScopes' => ['items.read', 'warehouse.read', 'inventory.read', 'inventory.write'],
            ]),
            self::BASE . '/v2/authentication' => FakePluginHttp::response([
                'registrationRequestId' => 'REG-1',
                'status' => 0,
            ], 201),
        ]);

        $this->actingAs($this->admin)->post(route('admin.jtl.connection.register'))->assertSessionHas('success');

        $connection->refresh();
        $this->assertSame(JtlConnection::STATUS_PENDING_REGISTRATION, $connection->status);
        $this->assertSame('REG-1', $connection->registration_id);
        $fake->assertSent(static function (RequestInterface $request): bool {
            return str_contains((string) $request->getUri(), '/v2/authentication')
                && $request->getHeaderLine('x-challengecode') !== ''
                && $request->getHeaderLine('api-version') === '2.0';
        });

        $this->actingAs($this->admin)->post(route('admin.jtl.connection.check'))->assertSessionHas('success');

        $connection->refresh();
        $this->assertSame(JtlConnection::STATUS_ACTIVE, $connection->status);
        $this->assertSame(JtlConnection::REGISTRATION_ACCEPTED, $connection->registration_status);
        $this->assertSame('KEY-SECRET-123', $connection->api_key);

        // At-rest verschlüsselt + nie in Array-/Audit-Payloads.
        $raw = (string) DB::table('jtl_connections')->where('id', $connection->id)->value('api_key');
        $this->assertStringNotContainsString('KEY-SECRET-123', $raw);
        $this->assertArrayNotHasKey('api_key', $connection->toArray());
    }

    public function test_scope_preflight_blocks_visibly_when_scopes_missing(): void {
        $connection = $this->makeOnPremiseConnection([
            'registration_id' => 'REG-2',
            'challenge_code' => 'challenge-xyz',
            'status' => JtlConnection::STATUS_PENDING_REGISTRATION,
        ]);

        FakePluginHttp::fake([
            self::BASE . '/v2/authentication/*' => FakePluginHttp::response([
                'requestStatusInfo' => ['status' => 2],
                'token' => ['apiKey' => 'KEY-LIMITED'],
                'grantedScopes' => ['items.read'],
            ]),
        ]);

        $this->actingAs($this->admin)->post(route('admin.jtl.connection.check'));

        $connection->refresh();
        $this->assertSame(JtlConnection::STATUS_BLOCKED, $connection->status);
        $this->assertSame('missing_scopes', $connection->blocked_reason);
    }

    public function test_cloud_connection_exchanges_client_credentials_for_token(): void {
        FakePluginHttp::fake([
            'https://auth.jtl-cloud.com/oauth2/token*' => FakePluginHttp::response([
                'access_token' => 'JWT-TOKEN-XYZ',
                'expires_in' => 86399,
            ]),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.jtl.connection.store'), [
            'mode' => JtlConnection::MODE_CLOUD,
            'api_version' => '2.0',
            'tenant_id' => 'f2f8ab7e-0000-0000-0000-000000000000',
            'client_id' => 'client-abc',
            'client_secret' => 'secret-def',
        ]);

        $response->assertSessionHas('success');

        $connection = JtlConnection::query()->where('organization_id', $this->organization->id)->firstOrFail();
        $this->assertSame(JtlConnection::STATUS_ACTIVE, $connection->status);
        $this->assertSame('JWT-TOKEN-XYZ', $connection->access_token);
        $this->assertTrue($connection->hasValidCloudToken());

        $raw = (string) DB::table('jtl_connections')->where('id', $connection->id)->value('client_secret');
        $this->assertStringNotContainsString('secret-def', $raw);
    }

    public function test_registration_answers_are_isolated_per_tenant(): void {
        $this->makeOnPremiseConnection();
        $otherOrg = \App\Models\Organization::factory()->create();
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);

        // Fremder Admin sieht die Verbindung dieser Organisation nicht.
        $response = $this->actingAs($otherAdmin)->post(route('admin.jtl.connection.register'));
        $response->assertNotFound();
    }

    /** @param array<string, mixed> $overrides */
    private function makeOnPremiseConnection(array $overrides = []): JtlConnection {
        return JtlConnection::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'mode' => JtlConnection::MODE_ON_PREMISE,
            'base_url' => self::BASE,
            'api_version' => '2.0',
            'allow_private_network' => true,
            'status' => JtlConnection::STATUS_DRAFT,
        ], $overrides));
    }
}
