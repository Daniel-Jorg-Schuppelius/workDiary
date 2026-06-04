<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanningViewsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\{Project, Task, Team, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class PlanningViewsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_project_timeline_renders_with_task_bar(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);

        $task = Task::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'title' => 'Zeitstrahl-Aufgabe',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Medium->value,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
        ]);
        $task->syncAssignees([$member->id]);

        $this->actingAs($admin)
            ->get(route('projects.planning', $project))
            ->assertOk()
            ->assertSee('Zeitstrahl-Aufgabe')
            ->assertSee($member->name);
    }

    public function test_team_workload_renders_member_tasks(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach($member->id);

        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $task = Task::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'title' => 'Auslastungs-Aufgabe',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Medium->value,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ]);
        $task->syncAssignees([$member->id]);

        $this->actingAs($member)
            ->get(route('teams.workload', $team))
            ->assertOk()
            ->assertSee('Auslastungs-Aufgabe')
            ->assertSee($member->name);
    }
}
