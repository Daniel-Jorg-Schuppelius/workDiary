<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Ideas;

use App\Enums\Ideas\IdeaShareRole;
use App\Models\{IdeaMap, User};
use App\Services\Ideas\{IdeaMapExportService, IdeaMapService, IdeaNodeService};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 054, MVP-110: JSON-/PDF-Export nur für den Eigentümer (auch
 * Editoren dürfen NICHT exportieren), stabiles JSON-Schema mit Sqids statt
 * interner IDs, Exporte auditiert.
 */
final class IdeaMapExportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private IdeaMapService $maps;
    private User $owner;
    private IdeaMap $map;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->maps = app(IdeaMapService::class);
        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->map = $this->maps->create($this->organization, $this->owner, 'Export-Karte');
        $root = $this->map->rootNode()->firstOrFail();
        app(IdeaNodeService::class)->create($this->map, $root, 'Unterpunkt', $this->owner);
    }

    public function test_json_export_has_stable_schema_without_internal_ids(): void {
        $response = $this->actingAs($this->owner)->get(route('ideas.export.json', $this->map))
            ->assertOk()
            ->assertJsonPath('format', IdeaMapExportService::FORMAT)
            ->assertJsonPath('map.title', 'Export-Karte')
            ->assertJsonPath('tree.children.0.title', 'Unterpunkt');

        // Keine internen IDs im Export (nur Sqids).
        $this->assertStringNotContainsString('"id"', (string) $response->getContent());

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $this->map->getMorphClass(),
            'auditable_id' => $this->map->id,
            'event' => 'idea_map.exported',
        ]);
    }

    public function test_pdf_export_renders_for_owner(): void {
        $this->actingAs($this->owner)->get(route('ideas.export.pdf', $this->map))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_export_is_owner_only_even_for_editors(): void {
        $editor = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->maps->shareWithUser($this->map, $editor, IdeaShareRole::Editor, $this->owner);

        $this->actingAs($editor)->get(route('ideas.export.json', $this->map))->assertForbidden();
        $this->actingAs($editor)->get(route('ideas.export.pdf', $this->map))->assertForbidden();
    }
}
