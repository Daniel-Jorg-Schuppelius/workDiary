<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerProjectTaskApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CustomerProjectTaskApiTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_customer_crud(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('api.customers.store'), [
            'name' => 'ACME GmbH',
            'currency' => 'EUR',
        ])->assertCreated()->assertJsonPath('data.name', 'ACME GmbH');

        $customer = Customer::firstOrFail();

        $this->getJson(route('api.customers.index'))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(route('api.customers.show', $customer))->assertOk()->assertJsonPath('data.id', $customer->id);

        $this->putJson(route('api.customers.update', $customer), [
            'name' => 'ACME AG',
            'currency' => 'EUR',
        ])->assertOk()->assertJsonPath('data.name', 'ACME AG');
    }

    public function test_project_index_and_create(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('api.projects.store'), [
            'name' => 'Webshop',
            'status' => Project::STATUS_ACTIVE,
        ])->assertCreated()->assertJsonPath('data.name', 'Webshop');

        $this->getJson(route('api.projects.index'))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_task_crud_under_project(): void
    {
        Sanctum::actingAs($this->user);

        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'P1',
            'status' => Project::STATUS_ACTIVE,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('api.tasks.store', $project), [
            'title' => 'T1',
            'status' => Task::STATUS_OPEN,
            'priority' => Task::PRIORITY_MEDIUM,
        ])->assertCreated()->assertJsonPath('data.title', 'T1');

        $this->getJson(route('api.tasks.index', ['project' => $project->id]))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_unauthenticated_rejected(): void
    {
        $this->getJson(route('api.customers.index'))->assertUnauthorized();
        $this->getJson(route('api.projects.index'))->assertUnauthorized();
        $this->getJson(route('api.tasks.index'))->assertUnauthorized();
    }
}
