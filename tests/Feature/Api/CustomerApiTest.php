<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\{Customer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CustomerApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $regular;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->regular = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_requires_authentication(): void {
        $this->getJson(route('api.customers.index'))->assertUnauthorized();
        $this->postJson(route('api.customers.store'), ['name' => 'X', 'currency' => 'EUR'])->assertUnauthorized();
    }

    public function test_full_crud_as_admin(): void {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(route('api.customers.store'), [
            'name' => 'ACME GmbH',
            'currency' => 'EUR',
        ])->assertCreated()->assertJsonPath('data.name', 'ACME GmbH');

        $customer = Customer::firstOrFail();

        $this->getJson(route('api.customers.show', $customer))
            ->assertOk()
            ->assertJsonPath('data.id', $customer->sqid);

        $this->putJson(route('api.customers.update', $customer), [
            'name' => 'ACME AG',
            'currency' => 'EUR',
        ])->assertOk()->assertJsonPath('data.name', 'ACME AG');

        $this->deleteJson(route('api.customers.destroy', $customer))->assertNoContent();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_validation_errors_on_store(): void {
        Sanctum::actingAs($this->admin, ['*']);

        // `currency` wird in prepareForValidation auf 'EUR' defaulted und schlägt nicht fehl.
        $this->postJson(route('api.customers.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_regular_user_cannot_delete_customer(): void {
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Locked',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->regular, ['*']);

        $this->deleteJson(route('api.customers.destroy', $customer))->assertForbidden();
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_index_paginates(): void {
        for ($i = 0; $i < 8; $i++) {
            Customer::create([
                'organization_id' => $this->organization->id,
                'name' => sprintf('Cust-%02d', $i),
                'currency' => 'EUR',
                'created_by' => $this->admin->id,
            ]);
        }

        Sanctum::actingAs($this->admin, ['*']);

        $this->getJson(route('api.customers.index', ['per_page' => 3]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.total', 8);
    }

    public function test_index_filters_by_search(): void {
        Customer::create(['organization_id' => $this->organization->id, 'name' => 'Alpha', 'currency' => 'EUR', 'created_by' => $this->admin->id]);
        Customer::create(['organization_id' => $this->organization->id, 'name' => 'Beta', 'currency' => 'EUR', 'created_by' => $this->admin->id]);

        Sanctum::actingAs($this->admin, ['*']);

        $this->getJson(route('api.customers.index', ['search' => 'Alp']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha');
    }
}
