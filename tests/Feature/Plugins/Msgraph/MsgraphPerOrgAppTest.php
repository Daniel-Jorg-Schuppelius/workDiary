<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphPerOrgAppTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Msgraph;

use App\Models\{PluginSetting, User};
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Plugins\Sharepoint\SharepointConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Per-Org-App-Registrierung (Feature 102 Variante B): Organisationen können
 * eine EIGENE Entra-App im Plugin-Settings-Dialog hinterlegen (encrypted
 * Overlay über die Instanz-ENV); Endpunkte/Scopes bleiben config-only.
 * Plattformweite Verbraucher (Backupziel) erzwingen die Instanz-App über
 * {@see MsgraphConfig::INSTANCE}; SharePoint erbt das Org-Overlay.
 */
final class MsgraphPerOrgAppTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const ORG_TENANT = '11111111-2222-3333-4444-555555555555';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        config()->set('plugins.msgraph.client_id', 'instanz-client');
        config()->set('plugins.msgraph.client_secret', 'instanz-secret');
    }

    private function orgApp(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => MsgraphPlugin::ID,
            'enabled' => true,
            'settings' => [
                'client_id' => 'org-client',
                'client_secret' => 'org-secret',
                'tenant' => self::ORG_TENANT,
            ],
        ]);
    }

    public function test_org_overlay_wins_and_instance_is_fallback(): void {
        // Ohne Overlay: Instanz-ENV.
        $this->assertSame('instanz-client', MsgraphConfig::resolve()['client_id']);

        $this->orgApp();
        $config = MsgraphConfig::resolve((int) $this->organization->id);
        $this->assertSame('org-client', $config['client_id']);
        $this->assertSame('org-secret', $config['client_secret']);
        // Der per-Org-Tenant landet in den Login-Endpunkten.
        $this->assertStringContainsString(self::ORG_TENANT, $config['authorize_url']);
        $this->assertStringContainsString(self::ORG_TENANT, $config['token_url']);
        // Endpunkt-Basis bleibt config-only.
        $this->assertSame('https://graph.microsoft.com/v1.0', $config['api_base']);
    }

    public function test_instance_sentinel_ignores_org_overlay(): void {
        $this->orgApp();

        // Org-Kontext ist gebunden (WithOrganization) — Kontext-Auflösung
        // nutzt das Overlay, der INSTANCE-Sentinel erzwingt die Instanz-App
        // (plattformweite Verbraucher wie das Backupziel).
        $this->assertSame('org-client', MsgraphConfig::resolve()['client_id']);
        $this->assertSame('instanz-client', MsgraphConfig::resolve(MsgraphConfig::INSTANCE)['client_id']);
    }

    public function test_oauth_start_uses_org_app(): void {
        $this->orgApp();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)->post(route('admin.msgraph.mail.oauth.start'));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('client_id=org-client', $location);
        $this->assertStringContainsString(self::ORG_TENANT, $location);
    }

    public function test_sharepoint_inherits_org_overlay(): void {
        $this->orgApp();

        $config = SharepointConfig::resolve((int) $this->organization->id);
        $this->assertSame('org-client', $config['client_id']);
        $this->assertStringContainsString(self::ORG_TENANT, $config['authorize_url']);
    }

    public function test_settings_validation_checks_tenant_format(): void {
        $plugin = new MsgraphPlugin();

        $this->assertSame([], $plugin->validateSettings(['tenant' => self::ORG_TENANT]));
        $this->assertSame([], $plugin->validateSettings(['tenant' => 'common']));
        $this->assertSame([], $plugin->validateSettings(['tenant' => '']));
        $this->assertArrayHasKey('tenant', $plugin->validateSettings(['tenant' => 'kaputt']));
    }
}
