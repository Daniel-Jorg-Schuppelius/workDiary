<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResellingSettingsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Domain\DomainProviderEnvironment;
use App\Models\Domain\DomainProviderConnection;
use App\Models\{PluginSetting, User};
use App\Plugins\DomainReselling\{DomainResellingConfig, DomainResellingPlugin};
use App\Plugins\Support\Domain\DomainRateBudgetException;
use App\Services\Domain\DomainAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeDomainResellingTransport, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Konfigurierbarkeit der DomainReselling-Betriebsparameter über den
 * generischen Plugin-Dialog (settingsSchema + plugin_settings):
 *  - Dialog rendert die Number-Felder + Zugangsdaten-Hinweis
 *  - Speichern persistiert, Bounds-Validierung greift
 *  - resolve() bevorzugt Org-Werte vor config(), fällt sonst zurück
 *  - Endpoint-Allowlist bleibt NIE org-überschreibbar (Sicherheitsinvariante)
 */
class DomainResellingSettingsTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_settings_dialog_renders_operational_fields_and_connection_note(): void {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.plugins.edit', DomainResellingPlugin::ID));

        $response->assertOk();
        foreach (['timeout', 'check_budget_per_hour', 'check_cache_ttl', 'list_page_size', 'stale_after_hours'] as $key) {
            $response->assertSee('settings[' . $key . ']', false);
        }
        // Hinweis + Link auf die Verbindungsverwaltung (custom settingsView).
        $response->assertSee(route('admin.domain-provider.index'), false);
    }

    public function test_admin_can_save_operational_settings(): void {
        $this->actingAs($this->admin)
            ->put(route('admin.plugins.update', DomainResellingPlugin::ID), [
                'enabled' => '1',
                'settings' => [
                    'timeout' => '45',
                    'check_budget_per_hour' => '100',
                    'check_cache_ttl' => '600',
                    'list_page_size' => '50',
                    'stale_after_hours' => '48',
                ],
            ])
            ->assertRedirect();

        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('plugin_id', DomainResellingPlugin::ID)
            ->firstOrFail();

        $this->assertTrue($row->enabled);
        $this->assertSame('45', $row->settings['timeout']);
        $this->assertSame('100', $row->settings['check_budget_per_hour']);
        $this->assertSame('600', $row->settings['check_cache_ttl']);
        $this->assertSame('50', $row->settings['list_page_size']);
        $this->assertSame('48', $row->settings['stale_after_hours']);
    }

    public function test_out_of_range_values_are_rejected(): void {
        $this->actingAs($this->admin)
            ->from(route('admin.plugins.index'))
            ->put(route('admin.plugins.update', DomainResellingPlugin::ID), [
                'enabled' => '1',
                'settings' => [
                    'timeout' => '999',
                    'list_page_size' => '1',
                ],
            ])
            ->assertSessionHasErrors(['settings.timeout', 'settings.list_page_size']);

        $this->assertDatabaseMissing('plugin_settings', [
            'organization_id' => $this->organization->id,
            'plugin_id' => DomainResellingPlugin::ID,
        ]);
    }

    public function test_non_integer_values_are_rejected(): void {
        // Die schema-seitige numeric-Rule ließe 2.5 durch — validateSettings()
        // verlangt eine ganze Zahl.
        $this->actingAs($this->admin)
            ->from(route('admin.plugins.index'))
            ->put(route('admin.plugins.update', DomainResellingPlugin::ID), [
                'enabled' => '1',
                'settings' => ['timeout' => '2.5'],
            ])
            ->assertSessionHasErrors(['settings.timeout']);
    }

    public function test_resolve_prefers_org_settings_over_config(): void {
        config()->set('plugins.domainreselling.timeout', 20);
        $this->enablePluginFor($this->organization, DomainResellingPlugin::ID, [
            'timeout' => '45',
            'list_page_size' => '50',
        ]);

        $resolved = DomainResellingConfig::resolve($this->organization->id);

        $this->assertSame(45, $resolved['timeout']);
        $this->assertSame(50, $resolved['list_page_size']);
        // Nicht gesetzte Keys behalten den config()-Default.
        $this->assertSame(300, $resolved['check_budget_per_hour']);
        $this->assertTrue($resolved['enabled']);
    }

    public function test_resolve_falls_back_to_config_without_org_row(): void {
        config()->set('plugins.domainreselling.timeout', 33);

        $resolved = DomainResellingConfig::resolve($this->organization->id);

        $this->assertSame(33, $resolved['timeout']);
        $this->assertSame(300, $resolved['check_budget_per_hour']);
        $this->assertSame(300, $resolved['check_cache_ttl']);
        $this->assertSame(100, $resolved['list_page_size']);
        $this->assertSame(24, $resolved['stale_after_hours']);
    }

    public function test_endpoints_and_call_path_are_never_org_overridable(): void {
        // Vergiftete Org-Settings dürfen die Allowlist NICHT aushebeln —
        // sonst gingen Zugangsdaten an einen fremden Host.
        $this->enablePluginFor($this->organization, DomainResellingPlugin::ID, [
            'endpoints' => ['production' => 'https://evil.example'],
            'call_path' => '/evil.cgi',
        ]);

        $resolved = DomainResellingConfig::resolve($this->organization->id);

        $this->assertSame('/api/call.cgi', $resolved['call_path']);
        $this->assertSame('https://api.domainreselling.de', $resolved['endpoints']['production']);
        $this->assertSame('https://api.domainreselling.de', DomainResellingConfig::endpointUrl(DomainProviderEnvironment::Production));
        $this->assertSame('https://api-ote.domainreselling.de', DomainResellingConfig::endpointUrl(DomainProviderEnvironment::Ote));
    }

    public function test_availability_budget_respects_org_override(): void {
        // config()-Default bleibt 300 — das Org-Setting 1 muss greifen.
        $this->enablePluginFor($this->organization, DomainResellingPlugin::ID, [
            'check_budget_per_hour' => '1',
        ]);
        FakeDomainResellingTransport::fake([
            'CheckDomains' => FakeDomainResellingTransport::properties([['domain' => 'neu.de', 'status' => 'available']]),
        ]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $svc = app(DomainAvailabilityService::class);

        $svc->check($connection, ['neu.de']); // verbraucht Budget 1

        $this->expectException(DomainRateBudgetException::class);
        $svc->check($connection, ['zweite.de']);
    }
}
