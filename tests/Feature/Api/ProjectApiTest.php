<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Project\ProjectStatus;
use App\Models\{Project, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ProjectApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    private User $other;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_requires_authentication(): void {
        $this->getJson(route('api.projects.index'))->assertUnauthorized();
    }

    public function test_create_and_show(): void {
        Sanctum::actingAs($this->owner, ['*']);

        $response = $this->postJson(route('api.projects.store'), [
            'name' => 'Webshop',
            'status' => ProjectStatus::Active->value,
        ])->assertCreated()->assertJsonPath('data.name', 'Webshop');

        $id = (string) $response->json('data.id');
        $this->assertSame($response->json('data.id'), Sqid::encode(Project::class, Project::firstOrFail()->id));

        $this->getJson(route('api.projects.show', $id))
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    public function test_validation_errors_on_store(): void {
        Sanctum::actingAs($this->owner, ['*']);

        $this->postJson(route('api.projects.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'status']);
    }

    public function test_owner_can_update_but_other_cannot(): void {
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'P1',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->owner->id,
        ]);

        Sanctum::actingAs($this->other, ['*']);
        $this->putJson(route('api.projects.update', $project), [
            'name' => 'Hijacked',
            'status' => ProjectStatus::Active->value,
        ])->assertForbidden();

        Sanctum::actingAs($this->owner, ['*']);
        $this->putJson(route('api.projects.update', $project), [
            'name' => 'Renamed',
            'status' => ProjectStatus::Active->value,
        ])->assertOk()->assertJsonPath('data.name', 'Renamed');
    }

    public function test_delete_is_forbidden_by_policy(): void {
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'NoDelete',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->owner->id,
        ]);

        Sanctum::actingAs($this->owner, ['*']);
        $this->deleteJson(route('api.projects.destroy', $project))->assertForbidden();
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_index_paginates(): void {
        for ($i = 0; $i < 6; $i++) {
            Project::create([
                'organization_id' => $this->organization->id,
                'name' => sprintf('Prj-%02d', $i),
                'status' => ProjectStatus::Active->value,
                'created_by' => $this->owner->id,
            ]);
        }

        Sanctum::actingAs($this->owner, ['*']);

        $this->getJson(route('api.projects.index', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 6);
    }
}
