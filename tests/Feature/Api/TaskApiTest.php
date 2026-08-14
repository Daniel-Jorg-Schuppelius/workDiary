<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Project\ProjectStatus;
use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\{Project, Task, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TaskApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    private User $other;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'API-Test-Project',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_requires_authentication(): void {
        $this->getJson(route('api.tasks.index'))->assertUnauthorized();
        $this->postJson(route('api.tasks.store', $this->project), [])->assertUnauthorized();
    }

    public function test_store_under_project(): void {
        Sanctum::actingAs($this->owner, ['*']);

        $this->postJson(route('api.tasks.store', $this->project), [
            'title' => 'T1',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Medium->value,
        ])->assertCreated()->assertJsonPath('data.title', 'T1');

        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'title' => 'T1',
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_validation_errors_on_store(): void {
        Sanctum::actingAs($this->owner, ['*']);

        $this->postJson(route('api.tasks.store', $this->project), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'status', 'priority']);
    }

    public function test_owner_can_update_and_delete_but_other_cannot(): void {
        $task = $this->makeTask($this->owner);

        Sanctum::actingAs($this->other, ['*']);
        $this->putJson(route('api.tasks.update', $task), [
            'title' => 'Hijacked',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Medium->value,
        ])->assertForbidden();
        $this->deleteJson(route('api.tasks.destroy', $task))->assertForbidden();

        Sanctum::actingAs($this->owner, ['*']);
        $this->putJson(route('api.tasks.update', $task), [
            'title' => 'Renamed',
            'status' => TaskStatus::InProgress->value,
            'priority' => TaskPriority::High->value,
        ])->assertOk()->assertJsonPath('data.title', 'Renamed');

        $this->deleteJson(route('api.tasks.destroy', $task))->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_index_filters_by_project_and_paginates(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->makeTask($this->owner, ['title' => 'Task ' . $i]);
        }
        $otherProject = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Other',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->owner->id,
        ]);
        $this->makeTask($this->owner, ['title' => 'Ausreißer'], $otherProject);

        Sanctum::actingAs($this->owner, ['*']);

        $this->getJson(route('api.tasks.index', ['project' => $this->project->sqid, 'per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeTask(User $creator, array $overrides = [], ?Project $project = null): Task {
        $project ??= $this->project;

        return Task::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'title' => 'Task',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Medium->value,
            'created_by' => $creator->id,
        ], $overrides));
    }
}
