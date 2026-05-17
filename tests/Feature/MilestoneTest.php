<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MilestoneTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class MilestoneTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();

        $this->user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test-Projekt',
            'status' => Project::STATUS_ACTIVE,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_user_can_create_milestone(): void
    {
        $this->actingAs($this->user)
            ->post(route('projects.milestones.store', $this->project), [
                'title' => 'Sprint 1',
                'description' => null,
                'due_date' => '2030-06-01',
                'is_completed' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('milestones', [
            'project_id' => $this->project->id,
            'title' => 'Sprint 1',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_owner_can_update_milestone(): void
    {
        $milestone = Milestone::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Alt',
            'is_completed' => false,
            'position' => 0,
        ]);

        $this->actingAs($this->user)
            ->put(route('projects.milestones.update', [$this->project, $milestone]), [
                'title' => 'Neu',
                'is_completed' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('milestones', [
            'id' => $milestone->id,
            'title' => 'Neu',
            'is_completed' => true,
        ]);
    }

    public function test_non_owner_cannot_update_milestone(): void
    {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $milestone = Milestone::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Alt',
            'is_completed' => false,
            'position' => 0,
        ]);

        $this->actingAs($other)
            ->put(route('projects.milestones.update', [$this->project, $milestone]), [
                'title' => 'Hack',
                'is_completed' => false,
            ])
            ->assertForbidden();
    }

    public function test_non_owner_cannot_delete_milestone(): void
    {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $milestone = Milestone::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Zu löschen',
            'is_completed' => false,
            'position' => 0,
        ]);

        $this->actingAs($other)
            ->delete(route('projects.milestones.destroy', [$this->project, $milestone]))
            ->assertForbidden();
    }

    public function test_owner_can_delete_milestone(): void
    {
        $milestone = Milestone::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Weg',
            'is_completed' => false,
            'position' => 0,
        ]);

        $this->actingAs($this->user)
            ->delete(route('projects.milestones.destroy', [$this->project, $milestone]))
            ->assertRedirect();

        $this->assertDatabaseMissing('milestones', ['id' => $milestone->id]);
    }
}
