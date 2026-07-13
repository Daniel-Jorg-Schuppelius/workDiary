<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointConnectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Sharepoint;

use App\Models\{SharepointConnection, User};
use App\Plugins\{PluginDiscovery, PluginHealth};
use App\Plugins\Sharepoint\Api\{SharepointDriveClient, SharepointOAuth};
use App\Plugins\Sharepoint\SharepointPlugin;
use GuzzleHttp\{Client as GuzzleClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-330 (Bauturbo A10): SharePoint-OAuth-Verbindung je Organisation —
 * state org- und sitzungsgebunden + einmalig (Replay-Schutz), PKCE im
 * Authorize-Redirect, Tokens verschlüsselt at-rest und nie in Audit-Payloads,
 * Refresh bei 401 (genau ein Retry), Site-/Bibliotheks-Auswahl serverseitig
 * über Graph validiert.
 */
final class SharepointConnectionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        config()->set('plugins.sharepoint.client_id', 'test-client');
        config()->set('plugins.sharepoint.client_secret', 'test-secret');
    }

    /**
     * Ersetzt den OAuth-Singleton durch eine Variante mit Guzzle-MockHandler.
     *
     * @param  array<string, mixed>  $tokenResponse
     */
    private function fakeTokenEndpoint(array $tokenResponse): void {
        $mock = new MockHandler([
            new Psr7Response(200, ['Content-Type' => 'application/json'], (string) json_encode($tokenResponse)),
        ]);
        $client = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        app()->instance(SharepointOAuth::class, new SharepointOAuth($client));
    }

    /** @param  array<int|string, mixed>  $query */
    private function queryParam(array $query, string $key): string {
        $value = $query[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /** Startet den Flow und liefert den state aus der Redirect-URL (inkl. PKCE-Prüfung). */
    private function startFlowAndGetState(): string {
        $response = $this->actingAs($this->admin)->post(route('admin.sharepoint.oauth.start'));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('test-client', $query['client_id'] ?? null);
        $this->assertStringContainsString('Files.ReadWrite.All', $this->queryParam($query, 'scope'));
        $this->assertStringContainsString('offline_access', $this->queryParam($query, 'scope'));
        // PKCE (S256) muss im Authorize-Redirect stecken.
        $this->assertNotSame('', $this->queryParam($query, 'code_challenge'));
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $state = $this->queryParam($query, 'state');
        $this->assertNotSame('', $state);

        return $state;
    }

    /** @param  array<string, mixed>  $attributes */
    private function connection(array $attributes = []): SharepointConnection {
        return SharepointConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-123',
            'status' => SharepointConnection::STATUS_ACTIVE,
        ]);
    }

    public function test_is_discovered_without_capability(): void {
        $this->assertContains(SharepointPlugin::class, PluginDiscovery::classes());

        $plugin = new SharepointPlugin();
        $this->assertSame([], $plugin->capabilities());
        $this->assertTrue($plugin->isPerOrganization());
    }

    public function test_admin_panel_requires_admin(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('admin.sharepoint.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.sharepoint.index'))->assertOk();
    }

    public function test_oauth_callback_stores_encrypted_connection_and_audits(): void {
        $this->fakeTokenEndpoint([
            'access_token' => 'secret-token-123',
            'refresh_token' => 'refresh-token-456',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);

        $state = $this->startFlowAndGetState();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.sharepoint.oauth.callback', ['state' => $state, 'code' => 'auth-code']));
        $this->assertNull(session('error'), 'Unerwarteter Fehler-Flash: ' . (string) session('error'));
        $response->assertRedirect(route('admin.sharepoint.index'))->assertSessionHas('success');

        $connection = SharepointConnection::query()->firstOrFail();
        $this->assertSame(SharepointConnection::STATUS_ACTIVE, $connection->status);
        $this->assertSame('secret-token-123', $connection->access_token); // entschlüsselt über Cast
        $this->assertSame('refresh-token-456', $connection->refresh_token);
        $this->assertNotNull($connection->token_expires_at);
        // Ohne gewählte Bibliothek noch nicht betriebsbereit.
        $this->assertFalse($connection->isActive());

        // At-rest verschlüsselt: Rohwerte enthalten die Tokens nicht im Klartext.
        $rawAccess = (string) DB::table('sharepoint_connections')->where('id', $connection->id)->value('access_token');
        $rawRefresh = (string) DB::table('sharepoint_connections')->where('id', $connection->id)->value('refresh_token');
        $this->assertStringNotContainsString('secret-token-123', $rawAccess);
        $this->assertStringNotContainsString('refresh-token-456', $rawRefresh);

        // Audit ohne Token-Payload.
        $this->assertDatabaseHas('audit_logs', ['event' => 'sharepoint.connected']);
        $auditChanges = (string) DB::table('audit_logs')->where('event', 'sharepoint.connected')->value('changes');
        $this->assertStringNotContainsString('secret-token-123', $auditChanges);
        $this->assertStringNotContainsString('refresh-token-456', $auditChanges);
    }

    public function test_oauth_state_is_single_use(): void {
        $this->fakeTokenEndpoint(['access_token' => 'secret-token-123', 'token_type' => 'Bearer']);

        $state = $this->startFlowAndGetState();

        $this->actingAs($this->admin)
            ->get(route('admin.sharepoint.oauth.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertSessionHas('success');

        // Replay desselben state → abgelehnt.
        $this->actingAs($this->admin)
            ->get(route('admin.sharepoint.oauth.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertSessionHas('error');
    }

    public function test_oauth_callback_rejects_unknown_state(): void {
        $this->actingAs($this->admin)
            ->get(route('admin.sharepoint.oauth.callback', ['state' => 'forged', 'code' => 'auth-code']))
            ->assertRedirect(route('admin.sharepoint.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, SharepointConnection::query()->count());
    }

    public function test_select_target_validates_site_and_drive_server_side(): void {
        $this->connection();

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/sites/site-1/drives*' => FakePluginHttp::response(['value' => [['id' => 'drive-1', 'name' => 'Dokumente']]]),
            'https://graph.microsoft.com/v1.0/sites/site-1*' => FakePluginHttp::response(['id' => 'site-1', 'displayName' => 'Bau-Projekte']),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.sharepoint.target.store'), ['site_id' => 'site-1', 'drive_id' => 'drive-1'])
            ->assertRedirect()->assertSessionHas('success');

        $connection = SharepointConnection::query()->firstOrFail();
        $this->assertSame('site-1', $connection->site_id);
        $this->assertSame('Bau-Projekte', $connection->site_name);   // serverseitig aufgelöst
        $this->assertSame('drive-1', $connection->drive_id);
        $this->assertSame('Dokumente', $connection->drive_name);
        $this->assertTrue($connection->isActive());
        $this->assertDatabaseHas('audit_logs', ['event' => 'sharepoint.target_selected']);
    }

    public function test_select_target_rejects_drive_outside_site(): void {
        $this->connection();

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/sites/site-1/drives*' => FakePluginHttp::response(['value' => [['id' => 'drive-1', 'name' => 'Dokumente']]]),
            'https://graph.microsoft.com/v1.0/sites/site-1*' => FakePluginHttp::response(['id' => 'site-1', 'displayName' => 'Bau-Projekte']),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.sharepoint.target.store'), ['site_id' => 'site-1', 'drive_id' => 'fremd-drive'])
            ->assertRedirect()->assertSessionHas('error');

        $connection = SharepointConnection::query()->firstOrFail();
        $this->assertNull($connection->drive_id); // nichts unterschoben
    }

    public function test_401_triggers_refresh_and_exactly_one_retry(): void {
        // Token-Endpunkt (Refresh) liefert ein frisches Token-Set.
        $this->fakeTokenEndpoint([
            'access_token' => 'new-token',
            'refresh_token' => 'new-refresh',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);

        $connection = $this->connection([
            'access_token' => 'old-token',
            'refresh_token' => 'refresh-1',
            'token_expires_at' => null, // nicht abgelaufen → kein Vorab-Refresh
        ]);

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/sites/site-1/drives*' => [
                FakePluginHttp::response(['error' => ['code' => 'InvalidAuthenticationToken']], 401),
                FakePluginHttp::response(['value' => []]),
            ],
        ]);

        $drives = (new SharepointDriveClient($connection))->listDrives('site-1');

        $this->assertSame([], $drives);
        // Genau ein Retry: 401 → Refresh → zweiter Versuch, nicht mehr.
        $fake->assertSentCount(2);
        $requests = $fake->recorded();
        $this->assertSame('Bearer old-token', $requests[0]['request']->getHeader('Authorization')[0] ?? '');
        $this->assertSame('Bearer new-token', $requests[1]['request']->getHeader('Authorization')[0] ?? '');

        // Refresh-Ergebnis verschlüsselt persistiert.
        $fresh = $connection->fresh();
        $this->assertInstanceOf(SharepointConnection::class, $fresh);
        $this->assertSame('new-token', $fresh->access_token);
        $this->assertSame('new-refresh', $fresh->refresh_token);
        $raw = (string) DB::table('sharepoint_connections')->where('id', $connection->id)->value('access_token');
        $this->assertStringNotContainsString('new-token', $raw);
    }

    public function test_health_reflects_drive_probe(): void {
        $this->connection(['drive_id' => 'drive-1', 'drive_name' => 'Dokumente']);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/drives/drive-1*' => FakePluginHttp::response(['id' => 'drive-1']),
        ]);
        $this->assertTrue((new SharepointPlugin())->healthCheck()->isOk());

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/drives/drive-1*' => FakePluginHttp::response(['error' => ['code' => 'InvalidAuthenticationToken']], 401),
        ]);
        $this->assertTrue((new SharepointPlugin())->healthCheck()->isFailing());
    }

    public function test_health_degraded_without_connection(): void {
        $this->assertSame(PluginHealth::STATUS_DEGRADED, (new SharepointPlugin())->healthCheck()->status);
    }
}
