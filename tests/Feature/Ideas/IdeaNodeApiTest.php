<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Ideas;

use App\Enums\Ideas\IdeaShareRole;
use App\Models\{IdeaMap, IdeaNode, User};
use App\Services\Ideas\{IdeaMapService, IdeaNodeService};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 054, MVP-106/108: knotenbezogene Editor-API — CRUD, Verschieben mit
 * Zyklus-Guard, Reihenfolge, optimistische Sperre (409 mit Serverstand),
 * Policy-Abdeckung je Endpunkt und Mutationssperre archivierter Karten.
 */
final class IdeaNodeApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private IdeaMapService $maps;
    private IdeaNodeService $nodes;
    private User $owner;
    private User $colleague;
    private IdeaMap $map;
    private IdeaNode $root;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->maps = app(IdeaMapService::class);
        $this->nodes = app(IdeaNodeService::class);
        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->map = $this->maps->create($this->organization, $this->owner, 'API-Karte');
        $this->root = $this->map->rootNode()->firstOrFail();
    }

    public function test_tree_returns_nodes_for_owner_and_denies_strangers(): void {
        $this->actingAs($this->owner)->getJson(route('ideas.maps.tree', $this->map))
            ->assertOk()
            ->assertJsonPath('can_update', true)
            ->assertJsonCount(1, 'nodes');

        $this->actingAs($this->colleague)->getJson(route('ideas.maps.tree', $this->map))->assertForbidden();
    }

    public function test_store_creates_child_node(): void {
        $this->actingAs($this->owner)->postJson(route('ideas.nodes.store', $this->map), [
            'parent' => $this->root->sqid,
            'title' => 'Neue Idee',
        ])->assertCreated()->assertJsonPath('node.title', 'Neue Idee');

        $this->assertSame(2, $this->map->nodes()->count());
    }

    public function test_update_increments_lock_version(): void {
        $node = $this->nodes->create($this->map, $this->root, 'Knoten');

        $this->actingAs($this->owner)->patchJson(route('ideas.nodes.update', [$this->map, $node]), [
            'title' => 'Geändert',
            'lock_version' => 1,
        ])->assertOk()->assertJsonPath('node.lock_version', 2);
    }

    public function test_stale_lock_version_returns_409_with_current_state(): void {
        $node = $this->nodes->create($this->map, $this->root, 'Knoten');
        $this->nodes->update($node, ['title' => 'Fremde Änderung'], expectedVersion: 1);

        $this->actingAs($this->owner)->patchJson(route('ideas.nodes.update', [$this->map, $node]), [
            'title' => 'Meine veraltete Änderung',
            'lock_version' => 1,
        ])->assertStatus(409)->assertJsonPath('current.title', 'Fremde Änderung');

        $this->assertSame('Fremde Änderung', $node->fresh()->title);
    }

    public function test_move_rejects_cycle_with_422(): void {
        $parent = $this->nodes->create($this->map, $this->root, 'Eltern');
        $child = $this->nodes->create($this->map, $parent, 'Kind');

        $this->actingAs($this->owner)->postJson(route('ideas.nodes.move', [$this->map, $parent]), [
            'parent' => $child->sqid,
        ])->assertStatus(422);
    }

    public function test_reorder_changes_sibling_order(): void {
        $a = $this->nodes->create($this->map, $this->root, 'A');
        $b = $this->nodes->create($this->map, $this->root, 'B');

        $this->actingAs($this->owner)->postJson(route('ideas.nodes.reorder', [$this->map, $this->root]), [
            'children' => [$b->sqid, $a->sqid],
        ])->assertOk();

        $this->assertSame([$b->id, $a->id], $this->root->children()->pluck('id')->all());
    }

    public function test_destroy_and_restore_subtree_via_api(): void {
        $branch = $this->nodes->create($this->map, $this->root, 'Zweig');
        $leaf = $this->nodes->create($this->map, $branch, 'Blatt');
        $branchSqid = $branch->sqid;

        $this->actingAs($this->owner)->deleteJson(route('ideas.nodes.destroy', [$this->map, $branch]))->assertOk();
        $this->assertSoftDeleted('idea_nodes', ['id' => $leaf->id]);

        $this->actingAs($this->owner)->postJson(route('ideas.nodes.restore', ['map' => $this->map, 'nodeSqid' => $branchSqid]))->assertOk();
        $this->assertDatabaseHas('idea_nodes', ['id' => $leaf->id, 'deleted_at' => null]);
    }

    public function test_archived_map_rejects_mutations_but_stays_readable(): void {
        $this->maps->archive($this->map);

        $this->actingAs($this->owner)->getJson(route('ideas.maps.tree', $this->map))
            ->assertOk()->assertJsonPath('can_update', false);

        $this->actingAs($this->owner)->postJson(route('ideas.nodes.store', $this->map), [
            'parent' => $this->root->sqid, 'title' => 'X',
        ])->assertForbidden();
    }

    public function test_viewer_reads_tree_but_cannot_mutate(): void {
        $this->maps->shareWithUser($this->map, $this->colleague, IdeaShareRole::Viewer, $this->owner);

        $this->actingAs($this->colleague)->getJson(route('ideas.maps.tree', $this->map))->assertOk();
        $this->actingAs($this->colleague)->postJson(route('ideas.nodes.store', $this->map), [
            'parent' => $this->root->sqid, 'title' => 'X',
        ])->assertForbidden();
    }

    public function test_editor_share_may_mutate(): void {
        $this->maps->shareWithUser($this->map, $this->colleague, IdeaShareRole::Editor, $this->owner);

        $this->actingAs($this->colleague)->postJson(route('ideas.nodes.store', $this->map), [
            'parent' => $this->root->sqid, 'title' => 'Von Kollege',
        ])->assertCreated();
    }

    public function test_node_of_other_map_is_not_bindable(): void {
        $otherMap = $this->maps->create($this->organization, $this->owner, 'Andere Karte');
        $foreignNode = $otherMap->rootNode()->firstOrFail();

        $this->actingAs($this->owner)->patchJson(route('ideas.nodes.update', [$this->map, $foreignNode]), [
            'title' => 'X', 'lock_version' => 1,
        ])->assertNotFound();
    }
}
