<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTeamAssignmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Project, Team, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ProjectTeamAssignmentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function user(): User {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_assignable_users_is_union_of_team_members_and_individual_members(): void {
        $teamMember = $this->user();
        $shared = $this->user();      // sowohl Team- als auch Einzelmitglied
        $individual = $this->user();

        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach([$teamMember->id, $shared->id]);

        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $project->teams()->attach($team->id);
        $project->members()->attach([$shared->id, $individual->id]);

        $ids = $project->assignableUsers()->pluck('id')->all();

        // Vereinigung, dedupliziert: teamMember, shared (einmal), individual.
        $this->assertEqualsCanonicalizing([$teamMember->id, $shared->id, $individual->id], $ids);
    }

    public function test_for_user_scope_matches_team_membership_and_individual_membership(): void {
        $viaTeam = $this->user();
        $viaIndividual = $this->user();
        $outsider = $this->user();

        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach($viaTeam->id);

        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $project->teams()->attach($team->id);
        $project->members()->attach($viaIndividual->id);

        $this->assertTrue(Project::forUser($viaTeam)->whereKey($project->id)->exists());
        $this->assertTrue(Project::forUser($viaIndividual)->whereKey($project->id)->exists());
        $this->assertFalse(Project::forUser($outsider)->whereKey($project->id)->exists());
    }

    public function test_admin_can_assign_teams_and_members_via_update(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $member = $this->user();
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Auftrag X']);

        $this->actingAs($admin)
            ->put(route('projects.update', $project), [
                'name' => 'Auftrag X',
                'status' => \App\Enums\Project\ProjectStatus::Active->value,
                'team_ids' => [$team->id],
                'member_ids' => [$member->id],
            ])
            ->assertRedirect();

        $this->assertTrue($project->teams()->whereKey($team->id)->exists());
        $this->assertTrue($project->members()->whereKey($member->id)->exists());
    }
}
