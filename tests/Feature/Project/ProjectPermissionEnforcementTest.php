<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectPermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Project;

use App\Enums\Project\ProjectStatus;
use App\Enums\User\{Permission, UserRole};
use App\Models\Platform\User;
use App\Models\Project\{Milestone, Project, Task};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Projekte, Aufgaben und Meilensteine prüfen das Recht (lk-06); Eigentum
 * und Projektbeteiligung gelten zusätzlich.
 */
class ProjectPermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_creating_projects_follows_project_create(): void {
        foreach ([UserRole::Teamleitung, UserRole::User, UserRole::Buchhaltung] as $role) {
            $this->assertTrue(Gate::forUser($this->userWithRole($role->value))->allows('create', Project::class), $role->value);
        }
        foreach ([UserRole::Aussendienst, UserRole::Geschaeftsfuehrung, UserRole::Support] as $role) {
            $this->assertFalse(Gate::forUser($this->userWithRole($role->value))->allows('create', Project::class), $role->value);
        }
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('create', Project::class));

        $this->actingAs($this->userWithRole(UserRole::Aussendienst->value))
            ->get(route('projects.create'))
            ->assertForbidden();
    }

    public function test_archiving_and_deleting_projects_follow_their_rights(): void {
        $owner = $this->userWithRole(UserRole::User->value);
        $project = $this->project($owner);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $colleague = $this->userWithRole(UserRole::User->value);

        $this->assertTrue(Gate::forUser($lead)->allows('archive', $project));
        $this->assertTrue(Gate::forUser($owner)->allows('archive', $project));
        $this->assertFalse(Gate::forUser($colleague)->allows('archive', $project));

        $this->actingAs($lead)
            ->post(route('reports.project-inactive.archive'), ['project_ids' => [$project->sqid]])
            ->assertRedirect();
        $this->assertSame(ProjectStatus::Archived, $project->refresh()->status);

        $deleter = $this->actorIn($this->organization, [Permission::ProjectDelete]);
        $this->assertTrue(Gate::forUser($deleter)->allows('delete', $project));
        $this->assertFalse(Gate::forUser($lead)->allows('delete', $project));
        $this->assertFalse(Gate::forUser($deleter)->allows('delete', $this->project($owner, ['is_default' => true])));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('delete', $project));
    }

    public function test_project_billing_needs_project_billing_manage(): void {
        $project = $this->project($this->orgAdmin());

        $this->actingAs($this->userWithRole(UserRole::Teamleitung->value))
            ->patch(route('projects.billing-settings.update', $project), ['billing_increment_minutes' => 15])
            ->assertForbidden();
        $this->actingAs($this->userWithRole(UserRole::Buchhaltung->value))
            ->get(route('projects.billing-rules.create', $project))
            ->assertOk();
        $this->actingAs($this->userWithRole(UserRole::Teamleitung->value))
            ->get(route('projects.billing-rules.create', $project))
            ->assertForbidden();
    }

    public function test_tasks_and_milestones_follow_their_manage_rights(): void {
        $worker = $this->userWithRole(UserRole::User->value);
        $field = $this->userWithRole(UserRole::Aussendienst->value);
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);
        $project = $this->project($worker);

        $this->assertTrue(Gate::forUser($worker)->allows('create', Task::class));
        $this->assertTrue(Gate::forUser($field)->allows('create', Task::class));
        $this->assertFalse(Gate::forUser($accounting)->allows('create', Task::class));

        $this->assertTrue(Gate::forUser($worker)->allows('create', Milestone::class));
        $this->assertFalse(Gate::forUser($field)->allows('create', Milestone::class));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('create', Milestone::class));

        $ownTask = Task::factory()->create(['project_id' => $project->id, 'created_by' => $accounting->id]);
        $this->assertFalse(Gate::forUser($accounting)->allows('update', $ownTask));
        $workerTask = Task::factory()->create(['project_id' => $project->id, 'created_by' => $worker->id]);
        $this->assertTrue(Gate::forUser($worker)->allows('update', $workerTask));
    }

    public function test_viewing_projects_follows_project_view(): void {
        $project = $this->project($this->orgAdmin());

        foreach ([UserRole::Callcenter, UserRole::Personalverwaltung, UserRole::Aussendienst] as $role) {
            $this->assertTrue(Gate::forUser($this->userWithRole($role->value))->allows('view', $project), $role->value);
        }
        $this->assertFalse(Gate::forUser($this->actorIn($this->organization, [Permission::ProjectViewAny]))->allows('view', $project));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('view', $project));
    }

    /** @param array<string, mixed> $attributes */
    private function project(User $creator, array $attributes = []): Project {
        return Project::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'created_by' => $creator->id,
        ], $attributes));
    }
}
