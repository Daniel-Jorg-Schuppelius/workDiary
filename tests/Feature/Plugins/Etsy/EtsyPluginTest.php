<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyPluginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Etsy;

use App\Models\{EtsyConnection, PluginSetting};
use App\Plugins\Etsy\EtsyPlugin;
use App\Plugins\PluginHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-494 (Phase 66): Plugin-Vertrag + Healthcheck-Zustände — unkonfiguriert/
 * unverbunden = degraded (zählt nicht für Auto-Disable), Auth-Fehler =
 * failing, 90-Tage-Refresh-Warnung vor Ablauf der Etsy-Frist.
 */
final class EtsyPluginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private EtsyPlugin $plugin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->plugin = new EtsyPlugin();
    }

    public function test_contract_basics(): void {
        $this->assertSame('etsy', EtsyPlugin::ID);
        $this->assertSame([], $this->plugin->capabilities());
        $this->assertTrue($this->plugin->isPerOrganization());

        $schema = $this->plugin->settingsSchema();
        $keys = array_column($schema, 'key');
        $this->assertSame(['keystring', 'shared_secret', 'webhook_secret', 'import_from', 'sync_page_budget'], $keys);
        // Secrets als password-Felder — nie im Klartext-Formular.
        foreach ($schema as $field) {
            if (in_array($field['key'], ['keystring', 'shared_secret', 'webhook_secret'], true)) {
                $this->assertSame('password', $field['type'], $field['key']);
            }
        }
    }

    public function test_health_is_degraded_without_configuration(): void {
        $health = $this->plugin->healthCheck();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->toArray()['status']);
    }

    public function test_health_is_degraded_without_connection(): void {
        $this->configure();

        $health = $this->plugin->healthCheck();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->toArray()['status']);
    }

    public function test_health_probes_shop_when_connected(): void {
        $this->configure();
        $this->connect();
        FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/shops/77' => FakePluginHttp::response(['shop_id' => 77]),
        ]);

        $health = $this->plugin->healthCheck();

        $this->assertTrue($health->isOk());
    }

    public function test_health_fails_on_auth_error(): void {
        $this->configure();
        $this->connect();
        FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/shops/77' => FakePluginHttp::response(['error' => 'invalid api key'], 401),
        ]);

        $health = $this->plugin->healthCheck();

        $this->assertTrue($health->isFailing());
    }

    public function test_health_warns_before_refresh_token_expiry(): void {
        $this->configure();
        $connection = $this->connect();
        // Rotation liegt 81 Tage zurück → Reconnect-Warnung (90-Tage-Frist).
        $connection->timestamps = false;
        $connection->forceFill(['refresh_issued_at' => now()->subDays(81)])->saveQuietly();

        $health = $this->plugin->healthCheck();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->toArray()['status']);
        $this->assertStringContainsString('neu verbinden', (string) $health->toArray()['message']);
    }

    private function configure(): void {
        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EtsyPlugin::ID,
            'enabled' => true,
            'settings' => ['keystring' => 'ks-1', 'shared_secret' => 'sec-1'],
        ]);
    }

    private function connect(): EtsyConnection {
        return EtsyConnection::create([
            'organization_id' => $this->organization->id,
            'shop_id' => 77,
            'etsy_user_id' => 12345,
            'access_token' => '12345.tok',
            'status' => EtsyConnection::STATUS_ACTIVE,
            'webhook_token' => 'hook-123',
        ]);
    }
}
