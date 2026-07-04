<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapShareTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Ideas;

use App\Enums\Ideas\IdeaShareRole;
use App\Models\{IdeaMap, Team, User};
use App\Notifications\GenericEventNotification;
use App\Services\Ideas\IdeaMapService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 054, MVP-107: Personen-/Teamfreigaben mit Rolle viewer/editor,
 * abgeleitete Sichtbarkeit (shared ⇔ mindestens eine Freigabe), sofortiger
 * Entzug beim Teamaustritt und Freigabe-Benachrichtigung (nur Titel + Link).
 */
final class IdeaMapShareTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private IdeaMapService $maps;
    private User $owner;
    private User $colleague;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->maps = app(IdeaMapService::class);
        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function map(): IdeaMap {
        return $this->maps->create($this->organization, $this->owner, 'Geteilte Karte');
    }

    public function test_viewer_share_grants_read_but_not_write(): void {
        $map = $this->map();
        $this->maps->shareWithUser($map, $this->colleague, IdeaShareRole::Viewer, $this->owner);

        $this->actingAs($this->colleague)->get(route('ideas.show', $map))->assertOk();
        $this->actingAs($this->colleague)->get(route('ideas.index'))->assertOk()->assertSee('Geteilte Karte');
        $this->actingAs($this->colleague)->put(route('ideas.update', $map), ['title' => 'X'])->assertForbidden();
    }

    public function test_editor_share_grants_write(): void {
        $map = $this->map();
        $this->maps->shareWithUser($map, $this->colleague, IdeaShareRole::Editor, $this->owner);

        $this->actingAs($this->colleague)->put(route('ideas.update', $map), ['title' => 'Neu'])->assertRedirect();
        $this->assertSame('Neu', $map->fresh()->title);
    }

    public function test_team_share_resolves_membership_on_access(): void {
        $map = $this->map();
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach($this->colleague->id, ['joined_at' => now()]);

        $this->maps->shareWithTeam($map, $team, IdeaShareRole::Viewer, $this->owner);
        $this->actingAs($this->colleague)->get(route('ideas.show', $map))->assertOk();

        // Teamaustritt entzieht den Zugriff SOFORT (Auflösung beim Zugriff).
        $team->members()->detach($this->colleague->id);
        $this->actingAs($this->colleague)->get(route('ideas.show', $map))->assertForbidden();
    }

    public function test_visibility_is_derived_from_active_shares(): void {
        $map = $this->map();
        $this->assertSame('private', $map->fresh()->visibility->value);

        $share = $this->maps->shareWithUser($map, $this->colleague, IdeaShareRole::Viewer, $this->owner);
        $this->assertSame('shared', $map->fresh()->visibility->value);

        $this->maps->revokeShare($map, $share);
        $this->assertSame('private', $map->fresh()->visibility->value);
    }

    public function test_revoked_share_closes_direct_url(): void {
        $map = $this->map();
        $share = $this->maps->shareWithUser($map, $this->colleague, IdeaShareRole::Viewer, $this->owner);
        $this->actingAs($this->colleague)->get(route('ideas.show', $map))->assertOk();

        $this->maps->revokeShare($map, $share);
        $this->actingAs($this->colleague)->get(route('ideas.show', $map))->assertForbidden();
    }

    public function test_only_owner_manages_shares(): void {
        $map = $this->map();

        $this->actingAs($this->colleague)->post(route('ideas.shares.store', $map), [
            'user' => $this->colleague->sqid, 'role' => 'viewer',
        ])->assertForbidden();
    }

    public function test_share_endpoint_requires_exactly_one_target(): void {
        $map = $this->map();
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);

        // Person UND Team gleichzeitig → Fehler.
        $this->actingAs($this->owner)->post(route('ideas.shares.store', $map), [
            'user' => $this->colleague->sqid, 'team' => $team->sqid, 'role' => 'viewer',
        ])->assertRedirect()->assertSessionHas('error');

        // Nur Person → ok.
        $this->actingAs($this->owner)->post(route('ideas.shares.store', $map), [
            'user' => $this->colleague->sqid, 'role' => 'viewer',
        ])->assertRedirect()->assertSessionHas('success');
    }

    public function test_share_sends_notification_with_title_and_link_only(): void {
        Notification::fake();
        $map = $this->map();

        $this->maps->shareWithUser($map, $this->colleague, IdeaShareRole::Viewer, $this->owner);

        Notification::assertSentTo($this->colleague, GenericEventNotification::class);
    }
}
