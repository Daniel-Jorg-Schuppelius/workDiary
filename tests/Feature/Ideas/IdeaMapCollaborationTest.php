<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapCollaborationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Ideas;

use App\Enums\Ideas\IdeaShareRole;
use App\Models\{IdeaMap, User};
use App\Services\Ideas\{IdeaMapService, IdeaNodeService};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 054, MVP-108: Bearbeitungspräsenz (Heartbeat, Cache-TTL) und
 * aggregierter Änderungsverlauf (Karte + Knoten) — beides nur für Personen,
 * die die Karte laut Policy sehen dürfen.
 */
final class IdeaMapCollaborationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private IdeaMapService $maps;
    private User $owner;
    private User $colleague;
    private IdeaMap $map;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->maps = app(IdeaMapService::class);
        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->map = $this->maps->create($this->organization, $this->owner, 'Kollaborations-Karte');
    }

    public function test_presence_lists_other_active_editors(): void {
        $this->maps->shareWithUser($this->map, $this->colleague, IdeaShareRole::Editor, $this->owner);

        // Eigentümer meldet sich; sieht (noch) niemanden sonst.
        $this->actingAs($this->owner)->postJson(route('ideas.maps.presence', $this->map))
            ->assertOk()->assertJsonPath('editing', []);

        // Kollege meldet sich; sieht den Eigentümer.
        $this->actingAs($this->colleague)->postJson(route('ideas.maps.presence', $this->map))
            ->assertOk()->assertJsonPath('editing.0', $this->owner->name);
    }

    public function test_presence_requires_map_visibility(): void {
        $this->actingAs($this->colleague)->postJson(route('ideas.maps.presence', $this->map))->assertForbidden();
    }

    public function test_history_aggregates_map_and_node_events(): void {
        $root = $this->map->rootNode()->firstOrFail();
        $node = app(IdeaNodeService::class)->create($this->map, $root, 'Neuer Knoten', $this->owner);
        app(IdeaNodeService::class)->update($node, ['title' => 'Umbenannt'], expectedVersion: 1, actor: $this->owner);

        $response = $this->actingAs($this->owner)->getJson(route('ideas.maps.history', $this->map))->assertOk();

        $events = collect($response->json('entries'))->pluck('event');
        $this->assertTrue($events->contains('created'), 'Knoten-Anlage fehlt im Verlauf');
        $this->assertTrue($events->contains('updated'), 'Knoten-Änderung fehlt im Verlauf');
    }

    public function test_history_requires_map_visibility(): void {
        $this->actingAs($this->colleague)->getJson(route('ideas.maps.history', $this->map))->assertForbidden();
    }
}
