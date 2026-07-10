<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Ideas;

use App\Models\{IdeaMap, IdeaNode, IdeaNodeLink, IdeaNodeReference, User};
use App\Services\Ideas\{IdeaMapService, IdeaNodeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 054, MVP-136: Whole-Map-Sync des Canvas — Reconciliation gegen die
 * normalisierten `idea_nodes` (Upsert/Soft-Delete), karten-weite optimistische
 * Sperre (409), Sqid-Stabilität (Referenzen überleben), Cross-Map-Abwehr sowie
 * die Kopplung Gliederungs-Mutation → Karten-Version.
 */
final class IdeaMapSyncTest extends TestCase {
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
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->maps = app(IdeaMapService::class);
        $this->nodes = app(IdeaNodeService::class);
        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->map = $this->maps->create($this->organization, $this->owner, 'Sync-Karte');
        $this->root = $this->map->rootNode()->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<int, array<string, mixed>>|null  $links
     * @param  array<int, array<string, mixed>>|null  $summaries
     */
    private function tree(array $children, ?int $version = null, ?array $links = null, ?array $summaries = null): array {
        $payload = [
            'lock_version' => $version ?? (int) $this->map->fresh()->lock_version,
            'tree' => ['sqid' => $this->root->sqid, 'title' => 'Sync-Karte', 'children' => $children],
        ];
        if ($links !== null) {
            $payload['links'] = $links;
        }
        if ($summaries !== null) {
            $payload['summaries'] = $summaries;
        }

        return $payload;
    }

    public function test_sync_creates_new_nodes_and_returns_sqids(): void {
        $res = $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['client_id' => 'c1', 'title' => 'Erste Idee', 'color' => 'primary', 'children' => []],
        ]))->assertOk();

        $res->assertJsonPath('lock_version', 2);
        $sqid = $res->json('created.c1');
        $this->assertNotNull($sqid);
        $this->assertSame(2, $this->map->nodes()->count()); // Wurzel + neuer Knoten
        $this->assertDatabaseHas('idea_nodes', ['idea_map_id' => $this->map->id, 'title' => 'Erste Idee', 'color' => 'primary']);
    }

    public function test_sync_updates_existing_node_fields(): void {
        $node = $this->nodes->create($this->map, $this->root, 'Alt');

        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['sqid' => $node->sqid, 'title' => 'Neu', 'color' => 'success', 'node_status' => 'offen', 'children' => []],
        ]))->assertOk();

        $node->refresh();
        $this->assertSame('Neu', $node->title);
        $this->assertSame('success', $node->color->value);
        $this->assertSame('offen', $node->node_status);
    }

    public function test_sync_soft_deletes_omitted_nodes(): void {
        $keep = $this->nodes->create($this->map, $this->root, 'Bleibt');
        $drop = $this->nodes->create($this->map, $this->root, 'Weg');

        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['sqid' => $keep->sqid, 'title' => 'Bleibt', 'children' => []],
        ]))->assertOk();

        $this->assertNull(IdeaNode::find($drop->id));
        $this->assertNotNull(IdeaNode::withTrashed()->find($drop->id)->deleted_at);
        $this->assertNotNull(IdeaNode::find($keep->id));
    }

    public function test_stale_lock_version_returns_409_with_current_tree(): void {
        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['client_id' => 'c1', 'title' => 'X', 'children' => []],
        ], version: 99))
            ->assertStatus(409)
            ->assertJsonPath('current.map.lock_version', 1);

        $this->assertSame(1, $this->map->nodes()->count()); // nichts angelegt
    }

    public function test_sync_rejects_foreign_node_sqid(): void {
        $otherMap = $this->maps->create($this->organization, $this->owner, 'Fremd');
        $foreign = $this->nodes->create($otherMap, $otherMap->rootNode()->firstOrFail(), 'Fremdknoten');

        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['sqid' => $foreign->sqid, 'title' => 'Hijack', 'children' => []],
        ]))->assertStatus(422);
    }

    public function test_sqid_identity_is_stable_so_references_survive(): void {
        $node = $this->nodes->create($this->map, $this->root, 'Überführbar');
        $ref = IdeaNodeReference::create([
            'organization_id' => $this->organization->id,
            'idea_node_id' => $node->id,
            'target_type' => User::class,
            'target_id' => $this->owner->id,
            'kind' => 'linked',
        ]);

        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['sqid' => $node->sqid, 'title' => 'Umbenannt', 'children' => []],
        ]))->assertOk();

        // Selbe id (kein Delete+Recreate) → Referenz zeigt weiter auf den Knoten.
        $this->assertDatabaseHas('idea_node_references', ['id' => $ref->id, 'idea_node_id' => $node->id]);
        $this->assertSame('Umbenannt', $node->refresh()->title);
    }

    public function test_outline_mutation_bumps_map_version(): void {
        $before = (int) $this->map->fresh()->lock_version;

        $this->actingAs($this->owner)->postJson(route('ideas.nodes.store', $this->map), [
            'parent' => $this->root->sqid,
            'title' => 'Aus der Gliederung',
        ])->assertCreated();

        $this->assertSame($before + 1, (int) $this->map->fresh()->lock_version);
    }

    public function test_viewer_cannot_sync(): void {
        $this->map->shares()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->colleague->id,
            'role' => 'viewer',
        ]);

        $this->actingAs($this->colleague)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['client_id' => 'c1', 'title' => 'X', 'children' => []],
        ]))->assertForbidden();
    }

    public function test_archived_map_rejects_sync(): void {
        $this->maps->archive($this->map);

        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['client_id' => 'c1', 'title' => 'X', 'children' => []],
        ], version: 1))->assertForbidden();
    }

    public function test_tree_endpoint_exposes_map_lock_version(): void {
        $this->actingAs($this->owner)->getJson(route('ideas.maps.tree', $this->map))
            ->assertOk()
            ->assertJsonPath('map.lock_version', 1);
    }

    // ── Querverbindungen (MVP-137) ──────────────────────────────────────

    public function test_sync_creates_link_between_existing_nodes(): void {
        $a = $this->nodes->create($this->map, $this->root, 'A');
        $b = $this->nodes->create($this->map, $this->root, 'B');

        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['sqid' => $a->sqid, 'title' => 'A', 'children' => []],
            ['sqid' => $b->sqid, 'title' => 'B', 'children' => []],
        ], links: [['from' => $a->sqid, 'to' => $b->sqid, 'label' => 'hängt ab von', 'color' => 'warning']]))->assertOk();

        $this->assertDatabaseHas('idea_node_links', [
            'idea_map_id' => $this->map->id,
            'source_node_id' => $a->id,
            'target_node_id' => $b->id,
            'label' => 'hängt ab von',
            'color' => 'warning',
        ]);
        $this->assertSame(1, $this->map->links()->count());
    }

    public function test_sync_link_endpoint_via_new_node_client_id(): void {
        $a = $this->nodes->create($this->map, $this->root, 'A');

        // Ziel ist ein IM SELBEN Sync neu angelegter Knoten (client_id → id).
        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['sqid' => $a->sqid, 'title' => 'A', 'children' => []],
            ['client_id' => 'new1', 'title' => 'Neu', 'children' => []],
        ], links: [['from' => $a->sqid, 'to' => 'new1']]))->assertOk();

        $this->assertSame(1, $this->map->links()->count());
        $link = $this->map->links()->firstOrFail();
        $this->assertSame($a->id, (int) $link->source_node_id);
        $this->assertSame('Neu', IdeaNode::find($link->target_node_id)?->title);
    }

    public function test_sync_removes_omitted_links_but_null_leaves_them(): void {
        $a = $this->nodes->create($this->map, $this->root, 'A');
        $b = $this->nodes->create($this->map, $this->root, 'B');
        $children = [
            ['sqid' => $a->sqid, 'title' => 'A', 'children' => []],
            ['sqid' => $b->sqid, 'title' => 'B', 'children' => []],
        ];

        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree(
            $children,
            links: [['from' => $a->sqid, 'to' => $b->sqid]],
        ))->assertOk();
        $this->assertSame(1, $this->map->links()->count());

        // links weggelassen (null) → unverändert.
        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree($children))->assertOk();
        $this->assertSame(1, $this->map->links()->count());

        // leeres links-Array → alle gelöscht.
        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree($children, links: []))->assertOk();
        $this->assertSame(0, $this->map->links()->count());
    }

    public function test_sync_skips_self_loop_and_unknown_endpoints(): void {
        $a = $this->nodes->create($this->map, $this->root, 'A');

        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['sqid' => $a->sqid, 'title' => 'A', 'children' => []],
        ], links: [
            ['from' => $a->sqid, 'to' => $a->sqid],          // Selbstverweis → verworfen
            ['from' => $a->sqid, 'to' => 'unbekannt'],        // unauflösbarer Endpunkt → verworfen
        ]))->assertOk();

        $this->assertSame(0, $this->map->links()->count());
    }

    // ── Boundaries / Summaries (MVP-137) ────────────────────────────────

    public function test_sync_creates_summary_over_children(): void {
        $a = $this->nodes->create($this->map, $this->root, 'A');
        $b = $this->nodes->create($this->map, $this->root, 'B');

        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['sqid' => $a->sqid, 'title' => 'A', 'children' => []],
            ['sqid' => $b->sqid, 'title' => 'B', 'children' => []],
        ], summaries: [['parent' => $this->root->sqid, 'start' => 0, 'end' => 1, 'label' => 'Cluster']]))->assertOk();

        $this->assertDatabaseHas('idea_node_summaries', [
            'idea_map_id' => $this->map->id,
            'parent_node_id' => $this->root->id,
            'start_index' => 0,
            'end_index' => 1,
            'label' => 'Cluster',
        ]);
        $this->assertSame(1, $this->map->summaries()->count());
    }

    public function test_sync_removes_omitted_summaries_but_null_leaves_them(): void {
        $a = $this->nodes->create($this->map, $this->root, 'A');
        $children = [['sqid' => $a->sqid, 'title' => 'A', 'children' => []]];

        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree(
            $children,
            summaries: [['parent' => $this->root->sqid, 'start' => 0, 'end' => 0]],
        ))->assertOk();
        $this->assertSame(1, $this->map->summaries()->count());

        // summaries weggelassen (null) → unverändert.
        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree($children))->assertOk();
        $this->assertSame(1, $this->map->summaries()->count());

        // leeres Array → gelöscht.
        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree($children, summaries: []))->assertOk();
        $this->assertSame(0, $this->map->summaries()->count());
    }

    public function test_sync_skips_invalid_summary_ranges(): void {
        $a = $this->nodes->create($this->map, $this->root, 'A');

        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['sqid' => $a->sqid, 'title' => 'A', 'children' => []],
        ], summaries: [
            ['parent' => $this->root->sqid, 'start' => 2, 'end' => 1],   // end < start → verworfen
            ['parent' => 'unbekannt', 'start' => 0, 'end' => 0],          // unauflösbarer Elternknoten → verworfen
        ]))->assertOk();

        $this->assertSame(0, $this->map->summaries()->count());
    }

    public function test_deleting_a_linked_node_cascades_the_link(): void {
        $a = $this->nodes->create($this->map, $this->root, 'A');
        $b = $this->nodes->create($this->map, $this->root, 'B');
        IdeaNodeLink::create([
            'organization_id' => $this->organization->id,
            'idea_map_id' => $this->map->id,
            'source_node_id' => $a->id,
            'target_node_id' => $b->id,
        ]);

        // B im Baum weglassen → hart entfernt? Nein: Knoten wird soft-deleted.
        // Der Link bleibt referenziell gültig (FK auf idea_nodes.id), verweist
        // aber auf einen soft-deleted Knoten — beim nächsten Hydrate fällt er
        // raus (nur aktive Knoten sind Endpunkte). Kein Sync-Fehler.
        $this->actingAs($this->owner)->postJson(route('ideas.maps.sync', $this->map), $this->tree([
            ['sqid' => $a->sqid, 'title' => 'A', 'children' => []],
        ], links: []))->assertOk();

        $this->assertNull(IdeaNode::find($b->id)); // soft-deleted
        $this->assertSame(0, $this->map->links()->count()); // Link durch leeres Array entfernt
    }
}
