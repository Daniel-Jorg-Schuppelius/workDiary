<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectMappingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{ExternalReference, PluginSetting, Project, User};
use App\Plugins\OpenProject\OpenProjectPlugin;
use App\Plugins\OpenProject\Services\OpenProjectStructureSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Struktur-Zuordnungen des OpenProject-Plugins (Vollscan 2026-08-23,
 * MVP-723): Die Mapping-Zeilen sprechen ihre {@see ExternalReference} über den
 * Sqid an — vorher standen rohe Datenbank-IDs in Formular-URLs, was die
 * projektweite Sqid-Regel verletzte und die IDs nach außen trug.
 */
class OpenProjectMappingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => OpenProjectPlugin::ID,
            'enabled' => true,
            'settings' => ['base_url' => 'https://op.example.test', 'api_token' => 'test-token'],
        ]);
    }

    private function projectReference(Project $project): ExternalReference {
        return ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => OpenProjectPlugin::ID,
            'external_type' => OpenProjectStructureSync::EXT_TYPE_PROJECT,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => 'op-42',
            'synced_at' => now(),
        ]);
    }

    public function test_mapping_list_links_by_sqid_not_raw_id(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha']);
        $reference = $this->projectReference($project);

        $this->actingAs($this->admin)
            ->get(route('admin.openproject.mappings.index'))
            ->assertOk()
            ->assertSee(route('admin.openproject.mappings.update', $reference->sqid));
    }

    public function test_update_and_delete_mapping_by_sqid(): void {
        $alpha = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha']);
        $beta = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beta']);
        $reference = $this->projectReference($alpha);

        $this->actingAs($this->admin)
            ->post(route('admin.openproject.mappings.update', $reference->sqid), ['target_id' => $beta->sqid])
            ->assertRedirect();

        $this->assertDatabaseHas('external_references', [
            'id' => $reference->id,
            'referenceable_id' => $beta->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.openproject.mappings.delete', $reference->sqid))
            ->assertRedirect();

        $this->assertDatabaseMissing('external_references', ['id' => $reference->id]);
    }

    /** Rohe IDs sind keine gültige Adresse mehr — sonst bliebe der Umweg offen. */
    public function test_raw_id_is_rejected(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha']);
        $reference = $this->projectReference($project);

        $this->actingAs($this->admin)
            ->post(route('admin.openproject.mappings.delete', (string) $reference->id), [])
            ->assertNotFound();
    }
}
