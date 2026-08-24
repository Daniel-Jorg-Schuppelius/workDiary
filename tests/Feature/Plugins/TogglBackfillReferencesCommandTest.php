<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglBackfillReferencesCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, ExternalReference, PluginSetting};
use App\Plugins\Support\MatchingTimeImportService;
use App\Plugins\Toggl\TogglPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7: toggl:backfill-references trägt für namensbasiert
 * verknüpfte Kunden/Projekte die stabilen Toggl-IDs nach (einmaliger Sync
 * gegen den Workspace, HTTP gefakt).
 */
final class TogglBackfillReferencesCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function enableToggl(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => ['api_token' => 'test-token', 'workspace_id' => 7],
        ]);
    }

    public function test_backfills_stable_client_ids_for_name_linked_customers(): void {
        $this->enableToggl();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme',
        ]);
        // Namensbasierte Bestandsverknüpfung, wie sie ältere Importe hinterließen.
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_CLIENT,
            'external_id' => 'Acme',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
        ]);

        FakePluginHttp::fake([
            'https://api.track.toggl.com/api/v9/workspaces/7/clients*' => FakePluginHttp::response([['id' => 5, 'name' => 'Acme']]),
            'https://api.track.toggl.com/api/v9/workspaces/7/projects*' => FakePluginHttp::response([]),
            'https://api.track.toggl.com/*' => FakePluginHttp::response([]),
        ]);

        $this->artisan('toggl:backfill-references', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('Projekt-IDs: 0, Client-IDs: 1 nachgetragen.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('external_references', [
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_CLIENT_ID,
            'external_id' => '5',
            'referenceable_id' => $customer->id,
        ]);
    }

    public function test_organizations_without_toggl_are_skipped(): void {
        FakePluginHttp::fake(); // jeder Request wäre ein Fehler im Skip-Pfad

        $this->artisan('toggl:backfill-references', ['--organization' => (string) $this->organization->id])
            ->assertExitCode(0);

        $this->assertSame(0, ExternalReference::query()->count());
    }
}
