<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartyPermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Customers;

use App\Enums\User\{Permission, UserRole};
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Supplier\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Fremdkunde, Kunde und Lieferant prüfen ihre Rechte (lk-06): Rollen mit dem
 * Recht dürfen, ohne nicht, Admins immer; Ersteller pflegen ihre Anlage.
 */
class PartyPermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_foreign_customers_follow_their_rights(): void {
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);
        $worker = $this->userWithRole(UserRole::User->value);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $admin = $this->orgAdmin();
        $foreign = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id])->id,
            'created_by' => $worker->id,
        ]);

        // Kunden-Factory legt über created_by eine Fremd-Organisation an und verstellt den Team-Kontext.
        $this->actAsTeam($this->organization);
        $this->assertTrue(Gate::forUser($worker)->allows('create', ForeignCustomer::class));
        $this->assertFalse(Gate::forUser($lead)->allows('create', ForeignCustomer::class));

        $this->assertTrue(Gate::forUser($worker)->allows('update', $foreign), 'Ersteller');
        $this->assertTrue(Gate::forUser($this->actorIn($this->organization, [Permission::ForeignCustomerUpdate]))->allows('update', $foreign));
        $this->assertFalse(Gate::forUser($lead)->allows('update', $foreign));

        $this->assertTrue(Gate::forUser($accounting)->allows('promote', $foreign));
        $this->assertFalse(Gate::forUser($worker)->allows('promote', $foreign));
        $this->assertTrue(Gate::forUser($admin)->allows('promote', $foreign));

        $this->assertTrue(Gate::forUser($accounting)->allows('delete', $foreign));
        $this->assertFalse(Gate::forUser($worker)->allows('delete', $foreign));

        $this->assertTrue(Gate::forUser($lead)->allows('view', $foreign));
        $this->assertFalse(Gate::forUser($this->userWithRole(UserRole::Personalverwaltung->value))->allows('view', $foreign));
    }

    public function test_customer_delete_follows_customer_delete(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);

        $this->assertTrue(Gate::forUser($this->userWithRole(UserRole::Buchhaltung->value))->allows('delete', $customer));
        $this->assertTrue(Gate::forUser($this->actorIn($this->organization, [Permission::CustomerDelete]))->allows('delete', $customer));
        $this->assertFalse(Gate::forUser($this->userWithRole(UserRole::User->value))->allows('delete', $customer));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('delete', $customer));
    }

    public function test_suppliers_follow_their_rights(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);

        $this->assertTrue(Gate::forUser($this->userWithRole(UserRole::User->value))->allows('create', Supplier::class));
        $this->assertFalse(Gate::forUser($lead)->allows('create', Supplier::class));
        $this->assertTrue(Gate::forUser($accounting)->allows('delete', $supplier));
        $this->assertFalse(Gate::forUser($lead)->allows('delete', $supplier));

        $this->actingAs($accounting)->get(route('suppliers.export'))->assertOk();
        $this->actingAs($lead)->get(route('suppliers.export'))->assertForbidden();
        $this->actingAs($this->orgAdmin())->get(route('suppliers.export'))->assertOk();
    }
}
