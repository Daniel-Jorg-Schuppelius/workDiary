<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TaskTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;
    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();

        $this->user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name'            => 'Test-Projekt',
            'status'          => Project::STATUS_ACTIVE,
            'created_by'      => $this->user->id,
        ]);
    }

    public function test_user_can_create_task(): void {
        $this->actingAs($this->user)
            ->post(route('projects.tasks.store', $this->project), [
                'title'    => 'Neue Aufgabe',
                'status'   => Task::STATUS_OPEN,
                'priority' => Task::PRIORITY_MEDIUM,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'title'      => 'Neue Aufgabe',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_user_can_create_sub_task(): void {
        $parent = Task::create([
            'organization_id' => $this->organization->id,
            'project_id'      => $this->project->id,
            'created_by'      => $this->user->id,
            'title'           => 'Eltern-Aufgabe',
            'status'          => Task::STATUS_OPEN,
            'priority'        => Task::PRIORITY_MEDIUM,
            'position'        => 0,
        ]);

        $this->actingAs($this->user)
            ->post(route('projects.tasks.store', $this->project), [
                'title'          => 'Sub-Task',
                'status'         => Task::STATUS_OPEN,
                'priority'       => Task::PRIORITY_LOW,
                'parent_task_id' => $parent->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'title'          => 'Sub-Task',
            'parent_task_id' => $parent->id,
        ]);
    }

    public function test_complete_toggle_marks_task_done_and_back_open(): void {
        $task = Task::create([
            'organization_id' => $this->organization->id,
            'project_id'      => $this->project->id,
            'created_by'      => $this->user->id,
            'title'           => 'Zu erledigen',
            'status'          => Task::STATUS_OPEN,
            'priority'        => Task::PRIORITY_MEDIUM,
            'position'        => 0,
        ]);

        // Toggle → done
        $this->actingAs($this->user)
            ->patch(route('projects.tasks.complete', [$this->project, $task]))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => Task::STATUS_DONE]);

        // Toggle zurück → open
        $this->actingAs($this->user)
            ->patch(route('projects.tasks.complete', [$this->project, $task]))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => Task::STATUS_OPEN]);
    }

    public function test_owner_can_update_task(): void {
        $task = Task::create([
            'organization_id' => $this->organization->id,
            'project_id'      => $this->project->id,
            'created_by'      => $this->user->id,
            'title'           => 'Alt',
            'status'          => Task::STATUS_OPEN,
            'priority'        => Task::PRIORITY_MEDIUM,
            'position'        => 0,
        ]);

        $this->actingAs($this->user)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'title'    => 'Neu',
                'status'   => Task::STATUS_IN_PROGRESS,
                'priority' => Task::PRIORITY_HIGH,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id'     => $task->id,
            'title'  => 'Neu',
            'status' => Task::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_non_owner_cannot_update_task(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $task  = Task::create([
            'organization_id' => $this->organization->id,
            'project_id'      => $this->project->id,
            'created_by'      => $this->user->id,
            'title'           => 'Fremde',
            'status'          => Task::STATUS_OPEN,
            'priority'        => Task::PRIORITY_MEDIUM,
            'position'        => 0,
        ]);

        $this->actingAs($other)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'title'    => 'Hack',
                'status'   => Task::STATUS_OPEN,
                'priority' => Task::PRIORITY_MEDIUM,
            ])
            ->assertForbidden();
    }

    public function test_non_owner_cannot_delete_task(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $task  = Task::create([
            'organization_id' => $this->organization->id,
            'project_id'      => $this->project->id,
            'created_by'      => $this->user->id,
            'title'           => 'Zu löschen',
            'status'          => Task::STATUS_OPEN,
            'priority'        => Task::PRIORITY_MEDIUM,
            'position'        => 0,
        ]);

        $this->actingAs($other)
            ->delete(route('projects.tasks.destroy', [$this->project, $task]))
            ->assertForbidden();
    }

    public function test_owner_can_delete_task(): void {
        $task = Task::create([
            'organization_id' => $this->organization->id,
            'project_id'      => $this->project->id,
            'created_by'      => $this->user->id,
            'title'           => 'Weg',
            'status'          => Task::STATUS_OPEN,
            'priority'        => Task::PRIORITY_MEDIUM,
            'position'        => 0,
        ]);

        $this->actingAs($this->user)
            ->delete(route('projects.tasks.destroy', [$this->project, $task]))
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
