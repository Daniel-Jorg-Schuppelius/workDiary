<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskConnectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\SevDesk;

use App\Models\{Organization, PluginSetting, PluginState};
use App\Plugins\PluginHealth;
use App\Plugins\SevDesk\Api\SevDeskClient;
use App\Plugins\SevDesk\{SevDeskConfig, SevDeskPlugin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-125 (Bauturbo A4): Verbindung + Healthcheck des sevDesk-Plugins.
 * Auth = roher API-Token im Authorization-Header (ohne Bearer-Präfix),
 * Probe = GET /Tools/bookkeepingSystemVersion; die erkannte Buchhaltungs-
 * Version wird je Mandant gecacht (Update 2.0 = Kontologik je Account).
 */
class SevDeskConnectionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function configure(?string $apiKey = 'tok-123', ?Organization $org = null): PluginSetting {
        return PluginSetting::create([
            'organization_id' => ($org ?? $this->organization)->id,
            'plugin_id' => SevDeskPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => $apiKey],
        ]);
    }

    public function test_healthcheck_without_token_is_degraded_not_configured(): void {
        $health = app(SevDeskPlugin::class)->healthCheck();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->status);
        $this->assertSame('not_configured', $health->code);
    }

    public function test_empty_encrypted_token_counts_as_not_configured(): void {
        // Leere encrypted-Strings dürfen NIE als Wert durchrutschen (?:-Regel).
        $this->configure(apiKey: '');

        $this->assertNull(SevDeskConfig::resolve($this->organization->id)['api_key']);
        $this->assertSame('not_configured', app(SevDeskPlugin::class)->healthCheck()->code);
    }

    public function test_healthcheck_probes_version_endpoint_and_caches_it_per_org(): void {
        $this->configure();

        $fake = FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Tools/bookkeepingSystemVersion*' => FakePluginHttp::response(['objects' => ['version' => '2.0']]),
        ]);

        $health = app(SevDeskPlugin::class)->healthCheck();

        $this->assertSame(PluginHealth::STATUS_OK, $health->status);
        $this->assertStringContainsString('2.0', $health->message);

        // Token roh im Authorization-Header — OHNE "Bearer "-Präfix (sevDesk-Vertrag).
        $fake->assertSent(fn($r) => $r->getHeaderLine('Authorization') === 'tok-123');

        // Version je Mandant gecacht — Folge-Aufrufe (Client) treffen die API nicht erneut.
        $this->assertSame('2.0', Cache::get(SevDeskClient::versionCacheKey($this->organization->id)));
    }

    public function test_healthcheck_with_rejected_token_is_failing_auth(): void {
        $this->configure();

        FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Tools/bookkeepingSystemVersion*' => FakePluginHttp::response(['error' => 'unauthorized'], 401),
        ]);

        $health = app(SevDeskPlugin::class)->healthCheck();

        $this->assertSame(PluginHealth::STATUS_FAILING, $health->status);
        $this->assertSame('auth', $health->code);
    }

    public function test_version_cache_is_isolated_per_organization_and_settings_do_not_leak(): void {
        $this->configure();

        $other = Organization::factory()->create();
        $this->configure(apiKey: 'tok-other', org: $other);

        // Mandant A meldet 2.0, Mandant B (frische Probe) 1.0.
        Cache::put(SevDeskClient::versionCacheKey($this->organization->id), '2.0', 600);

        FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Tools/bookkeepingSystemVersion*' => FakePluginHttp::response(['objects' => ['version' => 'Version 1.0']]),
        ]);

        app()->instance('currentOrganization', $other);
        $health = app(SevDeskPlugin::class)->healthCheck();

        $this->assertSame(PluginHealth::STATUS_OK, $health->status);
        $this->assertSame('1.0', Cache::get(SevDeskClient::versionCacheKey($other->id)));
        // Mandant A bleibt unberührt.
        $this->assertSame('2.0', Cache::get(SevDeskClient::versionCacheKey($this->organization->id)));

        // Config-Isolation: Mandant B sieht nie den Token von Mandant A.
        $this->assertSame('tok-other', SevDeskConfig::resolve($other->id)['api_key']);
        $this->assertSame('tok-123', SevDeskConfig::resolve($this->organization->id)['api_key']);
    }

    public function test_healthcheck_command_records_failing_state_per_organization(): void {
        $this->configure();

        FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Tools/bookkeepingSystemVersion*' => FakePluginHttp::response(['error' => 'unauthorized'], 401),
        ]);

        $this->artisan('plugin:healthcheck', ['plugin' => SevDeskPlugin::ID, '--no-fail' => true])
            ->assertSuccessful();

        $state = PluginState::query()
            ->where('plugin_id', SevDeskPlugin::ID)
            ->where('organization_id', $this->organization->id)
            ->firstOrFail();
        $this->assertSame(PluginHealth::STATUS_FAILING, $state->last_health_status);
        // Fehler zählt org-bezogen auf den Auto-Disable-Zähler ein
        // (Schwellen-Mechanik generisch in PluginManagerAutoDisableTest).
        $this->assertGreaterThanOrEqual(1, (int) $state->failure_count);
    }
}
