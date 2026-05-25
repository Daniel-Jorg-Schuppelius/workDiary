<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\{Project, Task, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TaskActivityTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Activity-Project',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_project_task_stores_activity_fields(): void {
        $this->actingAs($this->owner)
            ->post(route('projects.tasks.store', $this->project), [
                'title' => 'Backend-Arbeit',
                'status' => TaskStatus::Open->value,
                'priority' => TaskPriority::Medium->value,
                'hourly_rate' => '95.50',
                'internal_rate' => '40.00',
                'time_budget' => 600,
                'budget' => '2000',
                'budget_type' => 'month',
                'billable' => '1',
            ])
            ->assertRedirect();

        $task = Task::where('title', 'Backend-Arbeit')->firstOrFail();
        $this->assertSame($this->project->id, $task->project_id);
        $this->assertFalse($task->is_global);
        $this->assertSame('95.50', (string) $task->hourly_rate);
        $this->assertSame('40.00', (string) $task->internal_rate);
        $this->assertSame(600, $task->time_budget);
        $this->assertSame('2000.00', (string) $task->budget);
        $this->assertSame('month', $task->budget_type);
        $this->assertTrue($task->billable);
    }

    public function test_invalid_budget_type_is_rejected(): void {
        $this->actingAs($this->owner)
            ->post(route('projects.tasks.store', $this->project), [
                'title' => 'Bad',
                'status' => TaskStatus::Open->value,
                'priority' => TaskPriority::Medium->value,
                'budget_type' => 'weekly',
            ])
            ->assertSessionHasErrors('budget_type');
    }

    public function test_global_task_can_be_created_without_project(): void {
        $this->actingAs($this->owner)
            ->post(route('tasks.global.store'), [
                'title' => 'Pauschal-Wartung',
                'status' => TaskStatus::Open->value,
                'priority' => TaskPriority::Medium->value,
                'hourly_rate' => '120',
                'time_budget' => 60,
            ])
            ->assertRedirect(route('tasks.global.index'));

        $task = Task::where('title', 'Pauschal-Wartung')->firstOrFail();
        $this->assertTrue($task->is_global);
        $this->assertNull($task->project_id);
        $this->assertSame($this->organization->id, $task->organization_id);
        $this->assertSame($this->owner->id, $task->created_by);
    }

    public function test_global_index_only_returns_global_tasks_of_organization(): void {
        // Lokale Task (kein is_global)
        Task::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'title' => 'Lokal',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Medium->value,
            'created_by' => $this->owner->id,
        ]);
        // Globale Task
        Task::create([
            'organization_id' => $this->organization->id,
            'project_id' => null,
            'title' => 'Global1',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Medium->value,
            'is_global' => true,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('tasks.global.index'))
            ->assertOk()
            ->assertSee('Global1')
            ->assertDontSee('Lokal');
    }

    public function test_global_destroy_rejects_non_global_task(): void {
        $task = Task::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'title' => 'Lokal',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Medium->value,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('tasks.global.destroy', $task))
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }
}
