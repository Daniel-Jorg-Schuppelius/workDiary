<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Ideas;

use App\Models\{IdeaMap, Organization, User};
use App\Services\Ideas\IdeaMapService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 054, MVP-104/105 (HTTP/Policy): privat-by-default (auch für
 * Org-Admins — kein Bypass auf Inhalte), Mandantengrenze, Sqid-Binding,
 * Modul-Gating (423) und der getrennte `manageLifecycle`-Pfad ohne
 * Inhaltszugriff.
 */
final class IdeaMapControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;
    private User $colleague;
    private User $orgAdmin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->orgAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function mapOf(User $owner, string $title = 'Private Karte'): IdeaMap {
        return app(IdeaMapService::class)->create($this->organization, $owner, $title);
    }

    public function test_store_creates_private_map_with_root_node(): void {
        $this->actingAs($this->owner)->post(route('ideas.store'), [
            'title' => 'Produktideen',
        ])->assertRedirect();

        $map = IdeaMap::query()->firstOrFail();
        $this->assertSame('private', $map->visibility->value);
        $this->assertSame(1, $map->nodes()->where('is_root', true)->count());
    }

    public function test_private_map_is_hidden_from_colleagues_and_org_admin(): void {
        $map = $this->mapOf($this->owner);

        // Kollege derselben Org: weder Direkt-URL noch Index.
        $this->actingAs($this->colleague)->get(route('ideas.show', $map))->assertForbidden();
        $this->actingAs($this->colleague)->get(route('ideas.index'))
            ->assertOk()->assertDontSee('Private Karte');

        // Org-Admin: KEIN Inhaltszugriff (kein Admin-Bypass auf view).
        $this->actingAs($this->orgAdmin)->get(route('ideas.show', $map))->assertForbidden();
    }

    public function test_owner_sees_and_updates_own_map(): void {
        $map = $this->mapOf($this->owner);

        $this->actingAs($this->owner)->get(route('ideas.show', $map))->assertOk()->assertSee('Private Karte');
        $this->actingAs($this->owner)->get(route('ideas.index'))->assertOk()->assertSee('Private Karte');

        $this->actingAs($this->owner)->put(route('ideas.update', $map), [
            'title' => 'Umbenannt',
        ])->assertRedirect();
        $this->assertSame('Umbenannt', $map->fresh()->title);
    }

    public function test_cross_org_access_is_blocked(): void {
        $map = $this->mapOf($this->owner);

        $orgB = Organization::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgB->id);
        $stranger = User::factory()->user()->create(['organization_id' => $orgB->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        // Org-Scope blendet die Karte komplett aus → 404 (keine Existenz-Preisgabe).
        $this->actingAs($stranger)->get(route('ideas.show', $map))->assertNotFound();
    }

    public function test_free_plan_gates_ideas_routes_with_423(): void {
        $org = Organization::factory()->free()->create();
        app()->instance('currentOrganization', $org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user = User::factory()->user()->create(['organization_id' => $org->id]);

        $this->actingAs($user)->get(route('ideas.index'))->assertStatus(423);
    }

    public function test_archive_blocks_updates_but_keeps_map_readable(): void {
        $map = $this->mapOf($this->owner);
        $this->actingAs($this->owner)->post(route('ideas.archive', $map))->assertRedirect();

        $this->actingAs($this->owner)->get(route('ideas.show', $map))->assertOk();
        $this->actingAs($this->owner)->put(route('ideas.update', $map), ['title' => 'X'])->assertForbidden();
    }

    public function test_soft_delete_and_restore_via_trash(): void {
        $map = $this->mapOf($this->owner);
        $sqid = $map->sqid;

        $this->actingAs($this->owner)->delete(route('ideas.destroy', $map))->assertRedirect();
        $this->assertSoftDeleted('idea_maps', ['id' => $map->id]);

        $this->actingAs($this->owner)->post(route('ideas.restore', ['mapSqid' => $sqid]))->assertRedirect();
        $this->assertDatabaseHas('idea_maps', ['id' => $map->id, 'deleted_at' => null]);
    }

    public function test_manage_lifecycle_transfers_ownership_without_content_access(): void {
        $map = $this->mapOf($this->owner);

        // Org-Admin darf Eigentum übertragen (manageLifecycle) …
        $this->actingAs($this->orgAdmin)->post(route('ideas.transfer-ownership', $map), [
            'owner' => $this->colleague->sqid,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame($this->colleague->id, (int) $map->fresh()->owner_user_id);

        // … aber weiterhin keinen Inhalt sehen.
        $this->actingAs($this->orgAdmin)->get(route('ideas.show', $map))->assertForbidden();
    }

    public function test_colleague_cannot_transfer_ownership(): void {
        $map = $this->mapOf($this->owner);

        $this->actingAs($this->colleague)->post(route('ideas.transfer-ownership', $map), [
            'owner' => $this->colleague->sqid,
        ])->assertForbidden();
    }
}
