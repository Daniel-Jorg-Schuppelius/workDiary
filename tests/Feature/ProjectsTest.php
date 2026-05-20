<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
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
                'status' => ProjectStatus::Active->value,
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
            'status' => ProjectStatus::Active->value,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('projects.edit', $project))
            ->assertForbidden();
    }
}
