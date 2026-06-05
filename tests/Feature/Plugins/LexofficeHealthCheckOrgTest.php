<?php
/*
 * Created on   : Thu Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeHealthCheckOrgTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Organization, PluginSetting, PluginState};
use App\Plugins\Lexoffice\{LexofficeMapper, LexofficePlugin, LexofficeService};
use App\Plugins\PluginHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Healthcheck ist pro Organisation: der geplante (globale) `plugin:healthcheck`
 * bindet je Organisation den Kontext, sodass der jeweils in der DB gespeicherte
 * Schlüssel geprüft wird — nicht der env-Fallback. Das war die Ursache für
 * dauerhafte 401-Meldungen trotz gültigem UI-Schlüssel.
 */
class LexofficeHealthCheckOrgTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        app()->forgetInstance('currentOrganization');
    }

    /** Injizierter Service ist bewusst unkonfiguriert → ein OK kann nur aus der DB-Org stammen. */
    private function plugin(): LexofficePlugin {
        return new LexofficePlugin(new LexofficeService(null, new LexofficeMapper));
    }

    private function orgWithKey(string $key): Organization {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        PluginSetting::query()->create([
            'organization_id' => $org->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => $key],
        ]);
        app()->forgetInstance('currentOrganization');

        return $org;
    }

    public function test_uses_bound_org_db_key(): void {
        $org = $this->orgWithKey('valid-db-key');
        app()->instance('currentOrganization', $org);
        Http::fake(['https://api.lexoffice.io/v1/profile' => Http::response(['organizationId' => 'o1'], 200)]);

        $health = $this->plugin()->healthCheck();

        $this->assertSame(PluginHealth::STATUS_OK, $health->status);
    }

    public function test_failing_when_bound_org_key_rejected(): void {
        $org = $this->orgWithKey('bad-db-key');
        app()->instance('currentOrganization', $org);
        Http::fake(['https://api.lexoffice.io/v1/profile' => Http::response('unauthorized', 401)]);

        $health = $this->plugin()->healthCheck();

        $this->assertSame(PluginHealth::STATUS_FAILING, $health->status);
        $this->assertStringContainsString('401', $health->message);
    }

    public function test_scheduled_command_checks_each_org_with_its_db_key(): void {
        // Kein gebundener Kontext (wie der Cron). Der Command muss je Org binden
        // und den DB-Key prüfen → per-Org-Zustand „ok".
        $org = $this->orgWithKey('valid-db-key');
        Http::fake(['https://api.lexoffice.io/v1/profile' => Http::response(['organizationId' => 'o1'], 200)]);

        $this->artisan('plugin:healthcheck lexoffice --no-fail')->assertExitCode(0);

        $state = PluginState::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('organization_id', $org->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertSame(PluginHealth::STATUS_OK, $state->last_health_status);
    }
}
