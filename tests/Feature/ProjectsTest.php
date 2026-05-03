<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectsTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_user_can_create_project_via_web_route(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'name' => 'Neues Projekt',
                'description' => 'Beschreibung',
                'status' => Project::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Neues Projekt',
            'created_by' => $user->id,
        ]);
    }

    public function test_non_owner_cannot_edit_foreign_project(): void {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $project = Project::create([
            'name' => 'Fremdes Projekt',
            'status' => Project::STATUS_ACTIVE,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('projects.edit', $project))
            ->assertForbidden();
    }
}
