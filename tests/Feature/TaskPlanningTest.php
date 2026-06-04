<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskPlanningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\{Project, Task, Team, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TaskPlanningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Project $project;
    private User $teamMember;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->teamMember = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach($this->teamMember->id);

        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->project->teams()->attach($team->id);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array {
        return array_merge([
            'title' => 'Planungsaufgabe',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Medium->value,
        ], $overrides);
    }

    public function test_task_with_start_and_due_date_is_saved(): void {
        $this->actingAs($this->admin)
            ->post(route('projects.tasks.store', $this->project), $this->payload([
                'start_date' => '2026-07-01',
                'due_date' => '2026-07-10',
                'assignee_ids' => [$this->teamMember->id],
            ]))
            ->assertRedirect();

        $task = Task::where('project_id', $this->project->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('2026-07-01', $task->start_date?->format('Y-m-d'));
        $this->assertSame('2026-07-10', $task->due_date?->format('Y-m-d'));
        // Mehrfach-Zuweisung über Pivot; primärer Bearbeiter = erster Eintrag.
        $this->assertTrue($task->assignees()->whereKey($this->teamMember->id)->exists());
        $this->assertSame($this->teamMember->id, $task->assigned_to);
    }

    public function test_task_can_be_assigned_to_multiple_team_members(): void {
        $second = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        // zweites Mitglied ebenfalls ins Projekt-Team holen
        $this->project->members()->attach($second->id);

        $this->actingAs($this->admin)
            ->post(route('projects.tasks.store', $this->project), $this->payload([
                'assignee_ids' => [$this->teamMember->id, $second->id],
            ]))
            ->assertRedirect();

        $task = Task::where('project_id', $this->project->id)->first();
        $this->assertEqualsCanonicalizing(
            [$this->teamMember->id, $second->id],
            $task->assignees()->pluck('users.id')->all()
        );
    }

    public function test_due_date_before_start_date_is_rejected(): void {
        $this->actingAs($this->admin)
            ->post(route('projects.tasks.store', $this->project), $this->payload([
                'start_date' => '2026-07-10',
                'due_date' => '2026-07-01',
            ]))
            ->assertSessionHasErrors('due_date');
    }

    public function test_assignee_must_belong_to_project_team(): void {
        $outsider = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->post(route('projects.tasks.store', $this->project), $this->payload([
                'assignee_ids' => [$outsider->id],
            ]))
            ->assertSessionHasErrors('assignee_ids.0');
    }

    public function test_team_member_can_plan_task_they_did_not_create(): void {
        $task = Task::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'created_by' => $this->admin->id,
        ]);

        // Team-Mitglied (nicht Ersteller) darf die Aufgabe bearbeiten.
        $this->actingAs($this->teamMember)
            ->put(route('projects.tasks.update', [$this->project, $task]), $this->payload([
                'title' => 'Vom Team geplant',
            ]))
            ->assertRedirect();

        $this->assertSame('Vom Team geplant', $task->fresh()->title);
    }

    public function test_schedule_endpoint_updates_dates(): void {
        $task = Task::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('projects.tasks.schedule', [$this->project, $task]), [
                'start_date' => '2026-08-01',
                'due_date' => '2026-08-05',
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'start_date' => '2026-08-01', 'due_date' => '2026-08-05']);

        $task->refresh();
        $this->assertSame('2026-08-01', $task->start_date?->format('Y-m-d'));
        $this->assertSame('2026-08-05', $task->due_date?->format('Y-m-d'));
    }

    public function test_schedule_endpoint_rejects_due_before_start(): void {
        $task = Task::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('projects.tasks.schedule', [$this->project, $task]), [
                'start_date' => '2026-08-10',
                'due_date' => '2026-08-01',
            ])
            ->assertStatus(422);
    }

    public function test_unrelated_user_cannot_plan_task(): void {
        $outsider = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $task = Task::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($outsider)
            ->put(route('projects.tasks.update', [$this->project, $task]), $this->payload())
            ->assertForbidden();
    }
}
