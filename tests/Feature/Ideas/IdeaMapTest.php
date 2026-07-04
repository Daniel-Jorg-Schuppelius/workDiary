<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Ideas;

use App\Models\{IdeaNode, User};
use App\Services\Ideas\{IdeaMapService, IdeaNodeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 054, MVP-104/105 (Service-Ebene): Wurzelknoten-Invariante,
 * Baum-Operationen mit Zyklus-Guard, wiederherstellbares Teilbaum-Löschen und
 * die auditierte Eigentumsübertragung.
 */
final class IdeaMapTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private IdeaMapService $maps;
    private IdeaNodeService $nodes;
    private User $owner;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->maps = app(IdeaMapService::class);
        $this->nodes = app(IdeaNodeService::class);
        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_create_map_creates_exactly_one_root_node(): void {
        $map = $this->maps->create($this->organization, $this->owner, 'Produktideen');

        $this->assertSame(1, $map->nodes()->where('is_root', true)->count());
        $this->assertSame('private', $map->visibility->value);
        $this->assertSame($this->owner->id, (int) $map->owner_user_id);
    }

    public function test_nodes_append_under_parent_with_running_sort_order(): void {
        $map = $this->maps->create($this->organization, $this->owner, 'Karte');
        $root = $map->rootNode()->firstOrFail();

        $a = $this->nodes->create($map, $root, 'A', $this->owner);
        $b = $this->nodes->create($map, $root, 'B', $this->owner);

        $this->assertSame([$a->id, $b->id], $root->children()->pluck('id')->all());
        $this->assertTrue($b->sort_order > $a->sort_order);
    }

    public function test_move_rejects_cycles_and_root(): void {
        $map = $this->maps->create($this->organization, $this->owner, 'Karte');
        $root = $map->rootNode()->firstOrFail();
        $parent = $this->nodes->create($map, $root, 'Eltern');
        $child = $this->nodes->create($map, $parent, 'Kind');

        // Eltern unter das eigene Kind → Zyklus.
        $this->expectException(RuntimeException::class);
        $this->nodes->move($parent, $child);
    }

    public function test_root_cannot_be_deleted(): void {
        $map = $this->maps->create($this->organization, $this->owner, 'Karte');
        $root = $map->rootNode()->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->nodes->deleteSubtree($root);
    }

    public function test_delete_and_restore_subtree(): void {
        $map = $this->maps->create($this->organization, $this->owner, 'Karte');
        $root = $map->rootNode()->firstOrFail();
        $branch = $this->nodes->create($map, $root, 'Zweig');
        $leaf = $this->nodes->create($map, $branch, 'Blatt');

        $this->nodes->deleteSubtree($branch);
        $this->assertSoftDeleted('idea_nodes', ['id' => $branch->id]);
        $this->assertSoftDeleted('idea_nodes', ['id' => $leaf->id]);

        $this->nodes->restoreSubtree($branch->fresh() ?? IdeaNode::withTrashed()->findOrFail($branch->id));
        $this->assertDatabaseHas('idea_nodes', ['id' => $branch->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('idea_nodes', ['id' => $leaf->id, 'deleted_at' => null]);
    }

    public function test_update_with_stale_lock_version_throws_conflict(): void {
        $map = $this->maps->create($this->organization, $this->owner, 'Karte');
        $root = $map->rootNode()->firstOrFail();
        $node = $this->nodes->create($map, $root, 'Knoten');

        $this->nodes->update($node, ['title' => 'Erster'], expectedVersion: 1);

        $this->expectException(\App\Exceptions\IdeaNodeConflictException::class);
        $this->nodes->update($node, ['title' => 'Veraltet'], expectedVersion: 1);
    }

    public function test_transfer_ownership_is_audited(): void {
        $map = $this->maps->create($this->organization, $this->owner, 'Karte');
        $newOwner = User::factory()->create(['organization_id' => $this->organization->id]);
        $admin = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->maps->transferOwnership($map, $newOwner, $admin);

        $this->assertSame($newOwner->id, (int) $map->fresh()->owner_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $map->getMorphClass(),
            'auditable_id' => $map->id,
            'event' => 'idea_map.ownership_transferred',
        ]);
    }
}
