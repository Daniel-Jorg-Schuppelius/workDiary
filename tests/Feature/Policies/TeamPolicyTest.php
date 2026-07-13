<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Organization, Team};
use App\Policies\TeamPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Teams: feingranulare team.*-Rechte (via hasEffectivePermission, also auch
 * Gruppen-vererbt), jeder Objektzugriff hart organisationsgebunden; create
 * verlangt zusätzlich eine Org-Zugehörigkeit (org-loser User nie).
 */
final class TeamPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private TeamPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new TeamPolicy;
    }

    private function team(?int $orgId = null): Team {
        $team = new Team;
        $team->organization_id = $orgId ?? $this->organization->id;

        return $team;
    }

    public function test_permission_matrix_in_own_org(): void {
        $team = $this->team();

        $manager = $this->actorIn($this->organization, [P::TeamViewAny, P::TeamView, P::TeamCreate, P::TeamUpdate, P::TeamDelete, P::TeamManageMembers]);
        $this->assertTrue($this->policy->viewAny($manager));
        $this->assertTrue($this->policy->view($manager, $team));
        $this->assertTrue($this->policy->create($manager));
        $this->assertTrue($this->policy->update($manager, $team));
        $this->assertTrue($this->policy->delete($manager, $team));
        $this->assertTrue($this->policy->manageMembers($manager, $team));

        $viewer = $this->actorIn($this->organization, [P::TeamViewAny, P::TeamView]);
        $this->assertTrue($this->policy->view($viewer, $team));
        $this->assertFalse($this->policy->create($viewer));
        $this->assertFalse($this->policy->update($viewer, $team));
        $this->assertFalse($this->policy->delete($viewer, $team));
        $this->assertFalse($this->policy->manageMembers($viewer, $team));
    }

    public function test_foreign_org_team_is_denied_even_with_permissions(): void {
        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::TeamViewAny, P::TeamView, P::TeamUpdate, P::TeamDelete, P::TeamManageMembers]);
        $team = $this->team(); // Primär-Org

        $this->actAsTeam($foreignOrg);
        $this->assertFalse($this->policy->view($attacker, $team));
        $this->assertFalse($this->policy->update($attacker, $team));
        $this->assertFalse($this->policy->delete($attacker, $team));
        $this->assertFalse($this->policy->manageMembers($attacker, $team));
    }

    public function test_orgless_user_cannot_create_teams(): void {
        $orgless = $this->orglessActor();

        $this->assertFalse($this->policy->create($orgless));
        $this->assertFalse($this->policy->view($orgless, $this->team()));
    }
}
