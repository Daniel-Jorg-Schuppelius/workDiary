<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\Ideas\IdeaShareRole;
use App\Enums\User\Permission as P;
use App\Models\{IdeaMap, IdeaMapShare, Organization, Team, User};
use App\Policies\IdeaMapPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Ideenlandkarten (Feature 054) — privat-by-default OHNE Admin-Bypass:
 * Inhaltszugriff löst AUSSCHLIESSLICH über Eigentümer + aktive Freigaben auf;
 * auch (Plattform-)Admins sehen private Karteninhalte NIE. Für Betrieb gibt es
 * getrennt viewMeta/manageLifecycle (nur Metadaten, Recht ideas.manageLifecycle).
 * Teamfreigaben werden beim Zugriff aufgelöst; Org-Grenze schlägt jede Freigabe.
 */
final class IdeaMapPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private IdeaMapPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new IdeaMapPolicy;
    }

    private function map(User $owner, ?\Carbon\CarbonInterface $archivedAt = null): IdeaMap {
        /** @var IdeaMap $map */
        $map = IdeaMap::factory()->create([
            'organization_id' => $owner->organization_id,
            'created_by' => $owner->id,
            'owner_user_id' => $owner->id,
            'archived_at' => $archivedAt,
        ]);

        return $map;
    }

    private function share(IdeaMap $map, IdeaShareRole $role, ?User $user = null, ?Team $team = null): void {
        IdeaMapShare::create([
            'organization_id' => $map->organization_id,
            'idea_map_id' => $map->id,
            'user_id' => $user?->id,
            'team_id' => $team?->id,
            'role' => $role,
        ]);
    }

    public function test_owner_has_full_content_access(): void {
        $owner = $this->actorIn($this->organization, [P::IdeasViewAny, P::IdeasCreate]);
        $map = $this->map($owner);

        $this->assertTrue($this->policy->viewAny($owner));
        $this->assertTrue($this->policy->create($owner));
        $this->assertTrue($this->policy->view($owner, $map));
        $this->assertTrue($this->policy->update($owner, $map));
        $this->assertTrue($this->policy->share($owner, $map));
        $this->assertTrue($this->policy->delete($owner, $map));
        $this->assertTrue($this->policy->restore($owner, $map));
        $this->assertTrue($this->policy->export($owner, $map));
    }

    public function test_private_by_default_org_membership_grants_nothing(): void {
        $owner = $this->actorIn($this->organization);
        $colleague = $this->actorIn($this->organization, [P::IdeasViewAny, P::IdeasCreate]);
        $map = $this->map($owner);

        $this->assertFalse($this->policy->view($colleague, $map), 'Org-Zugehörigkeit allein gewährt KEINEN Kartenzugriff.');
        $this->assertFalse($this->policy->update($colleague, $map));
        $this->assertFalse($this->policy->export($colleague, $map));
    }

    public function test_share_roles_grant_graded_access(): void {
        $owner = $this->actorIn($this->organization);
        $viewer = $this->actorIn($this->organization);
        $editor = $this->actorIn($this->organization);
        $map = $this->map($owner);
        $this->share($map, IdeaShareRole::Viewer, $viewer);
        $this->share($map, IdeaShareRole::Editor, $editor);

        $this->assertTrue($this->policy->view($viewer, $map));
        $this->assertFalse($this->policy->update($viewer, $map), 'Viewer-Freigabe erlaubt kein Bearbeiten.');

        $this->assertTrue($this->policy->view($editor, $map));
        $this->assertTrue($this->policy->update($editor, $map));
        // Eigentümer-exklusiv bleibt exklusiv:
        $this->assertFalse($this->policy->share($editor, $map));
        $this->assertFalse($this->policy->delete($editor, $map));
        $this->assertFalse($this->policy->export($editor, $map));
    }

    public function test_team_share_resolves_membership_at_access_time(): void {
        $owner = $this->actorIn($this->organization);
        $member = $this->actorIn($this->organization);
        $outsider = $this->actorIn($this->organization);
        /** @var Team $team */
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach($member->id);
        $map = $this->map($owner);
        $this->share($map, IdeaShareRole::Editor, null, $team);

        $this->assertTrue($this->policy->view($member, $map));
        $this->assertTrue($this->policy->update($member, $map));
        $this->assertFalse($this->policy->view($outsider, $map), 'Ohne Team-Mitgliedschaft keine Team-Freigabe.');

        // Wer das Team verlässt, verliert den Zugriff SOFORT.
        $team->members()->detach($member->id);
        $this->assertFalse($this->policy->view($member, $map));
    }

    public function test_archived_maps_are_not_editable_even_by_owner(): void {
        $owner = $this->actorIn($this->organization);
        $archived = $this->map($owner, now());

        $this->assertFalse($this->policy->update($owner, $archived));
        $this->assertTrue($this->policy->view($owner, $archived), 'Lesen bleibt erlaubt.');
        $this->assertTrue($this->policy->restore($owner, $archived));
    }

    public function test_admins_have_no_content_bypass(): void {
        $owner = $this->actorIn($this->organization);
        $map = $this->map($owner);

        $orgAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $platformAdmin = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);

        foreach ([$orgAdmin, $platformAdmin] as $admin) {
            $this->assertTrue(Gate::forUser($admin)->denies('view', $map), 'Admins sehen private Karteninhalte NIE.');
            $this->assertTrue(Gate::forUser($admin)->denies('update', $map));
            $this->assertTrue(Gate::forUser($admin)->denies('export', $map));
            $this->assertTrue(Gate::forUser($admin)->denies('share', $map));
        }
    }

    public function test_lifecycle_rights_expose_metadata_only(): void {
        $owner = $this->actorIn($this->organization);
        $lifecycle = $this->actorIn($this->organization, [P::IdeasManageLifecycle]);
        $map = $this->map($owner);

        $this->assertTrue($this->policy->viewMeta($lifecycle, $map));
        $this->assertTrue($this->policy->manageLifecycle($lifecycle, $map));
        // Lifecycle-Recht öffnet KEINEN Inhaltszugriff.
        $this->assertFalse($this->policy->view($lifecycle, $map));
        $this->assertFalse($this->policy->export($lifecycle, $map));
    }

    public function test_org_boundary_beats_every_share(): void {
        $owner = $this->actorIn($this->organization);
        $map = $this->map($owner);

        $foreignOrg = Organization::factory()->create();
        $foreignUser = $this->actorIn($foreignOrg, [P::IdeasViewAny, P::IdeasManageLifecycle]);
        // Selbst mit (fachlich nie erzeugter) direkter Freigabe: Fremd-Org bleibt draußen.
        $this->share($map, IdeaShareRole::Editor, $foreignUser);

        $this->actAsTeam($foreignOrg);
        $this->assertFalse($this->policy->view($foreignUser, $map));
        $this->assertFalse($this->policy->update($foreignUser, $map));
        $this->assertFalse($this->policy->viewMeta($foreignUser, $map));
        $this->assertFalse($this->policy->manageLifecycle($foreignUser, $map));
    }
}
