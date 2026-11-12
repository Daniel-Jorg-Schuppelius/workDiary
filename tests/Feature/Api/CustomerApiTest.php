<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
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

    /** MVP-1591: Die API sperrt wie die Oberfläche, solange externe Referenzen am Kunden hängen. */
    public function test_delete_is_refused_while_external_references_exist(): void {
        Sanctum::actingAs($this->admin, ['*']);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        \App\Models\Integration\ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => 'lexoffice', 'external_type' => 'contact', 'external_id' => 'c-1',
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);

        $this->deleteJson(route('api.customers.destroy', $customer))->assertStatus(409);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    /** MVP-1591: Löschen über die API löscht das KI-Gedächtnis auditiert und nimmt das Standardprojekt mit. */
    public function test_delete_removes_ai_memory_with_audit_and_the_default_project(): void {
        Sanctum::actingAs($this->admin, ['*']);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $entry = \App\Models\Ai\AiMemoryEntry::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $defaultProjects = $customer->projects()->where('is_default', true)->pluck('id')->all();

        $this->deleteJson(route('api.customers.destroy', $customer))->assertNoContent();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('ai_memory_entries', ['id' => $entry->id]);
        // Einzel-Audit je Eintrag statt stiller FK-Kaskade.
        $this->assertTrue(\App\Models\Audit\AuditLog::query()
            ->where('auditable_type', \App\Support\MorphMap::stableKey(\App\Models\Ai\AiMemoryEntry::class))
            ->where('auditable_id', $entry->id)->where('event', 'deleted')->exists());
        $this->assertNotSame([], $defaultProjects);
        $this->assertSame(0, \App\Models\Project\Project::query()->whereIn('id', $defaultProjects)->count());
    }
}
