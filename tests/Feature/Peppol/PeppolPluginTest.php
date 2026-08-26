<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolPluginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Peppol;

use App\Plugins\Contracts\{PeppolTransportProvider, PluginCapability};
use App\Plugins\PeppolAccessPoint\PeppolAccessPointPlugin;
use App\Plugins\PluginHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Provider-Plugin (Feature 066, MVP-734): Vertrag, Konfigurationsschema und
 * Healthcheck je Organisation. Der Healthcheck muss die drei Zustände sauber
 * trennen — nicht konfiguriert, falsch konfiguriert, nicht erreichbar.
 */
class PeppolPluginTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private const AP_BASE = 'https://ap.example.test/v1';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function plugin(): PeppolAccessPointPlugin {
        return app(PeppolAccessPointPlugin::class);
    }

    public function test_plugin_advertises_the_peppol_transport_contract(): void {
        $plugin = $this->plugin();

        $this->assertInstanceOf(PeppolTransportProvider::class, $plugin);
        $this->assertContains(PluginCapability::PeppolTransport, $plugin->capabilities());
        $this->assertSame(PeppolTransportProvider::class, PluginCapability::PeppolTransport->interface());
    }

    public function test_settings_schema_marks_the_access_key_as_secret(): void {
        $fields = collect($this->plugin()->settingsSchema())->keyBy('key');

        $this->assertTrue($fields->has('base_url'));
        $this->assertTrue($fields->has('sender_participant_id'));
        $this->assertSame('password', $fields['api_key']['type'] ?? null);
    }

    public function test_health_reports_missing_credentials_without_calling_the_provider(): void {
        $fake = FakePluginHttp::fake();

        $health = $this->withPluginOrgContext($this->organization, fn (): PluginHealth => $this->plugin()->healthCheck());

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->toArray()['status']);
        $this->assertSame('not_configured', $health->toArray()['code']);
        $fake->assertNothingSent();
    }

    public function test_health_reports_an_invalid_own_participant_id(): void {
        $this->enablePluginFor($this->organization, PeppolAccessPointPlugin::ID, [
            'base_url' => self::AP_BASE,
            'api_key' => 'geheim',
            'sender_participant_id' => 'DE123456789',
        ]);
        $fake = FakePluginHttp::fake();

        $health = $this->withPluginOrgContext($this->organization, fn (): PluginHealth => $this->plugin()->healthCheck());

        $this->assertSame('sender_invalid', $health->toArray()['code']);
        $fake->assertNothingSent();
    }

    public function test_health_pings_the_status_endpoint_when_configured(): void {
        $this->enablePluginFor($this->organization, PeppolAccessPointPlugin::ID, [
            'base_url' => self::AP_BASE,
            'api_key' => 'geheim',
            'sender_participant_id' => '9930:DE123456789',
        ]);
        FakePluginHttp::fake([self::AP_BASE . '/status*' => FakePluginHttp::response(['ok' => true])]);

        $health = $this->withPluginOrgContext($this->organization, fn (): PluginHealth => $this->plugin()->healthCheck());

        $this->assertTrue($health->isOk());
    }

    public function test_health_fails_when_the_provider_rejects_the_key(): void {
        $this->enablePluginFor($this->organization, PeppolAccessPointPlugin::ID, [
            'base_url' => self::AP_BASE,
            'api_key' => 'falsch',
            'sender_participant_id' => '9930:DE123456789',
        ]);
        FakePluginHttp::fake([self::AP_BASE . '/*' => FakePluginHttp::response([], 401)]);

        $health = $this->withPluginOrgContext($this->organization, fn (): PluginHealth => $this->plugin()->healthCheck());

        $this->assertTrue($health->isFailing());
    }
}
