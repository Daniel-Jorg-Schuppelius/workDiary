<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainHttpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Domain;

use App\Enums\Domain\DomainConnectionStatus;
use App\Enums\User\Permission;
use App\Models\{Customer, ForeignCustomer, Organization, User};
use App\Models\Domain\{DomainProjection, DomainProviderConnection, DomainResellerAccount};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeDomainResellingTransport;
use Tests\TestCase;

/**
 * HTTP-Schicht des Domain-Moduls (Feature 083): Rechte, Verbindungs-Anlage,
 * Portfolio-Ansicht, Organisations-Scoping und Plan-Modul-Gate (free → 423).
 */
class DomainHttpTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(); // Factory-Default: enterprise (module.domain enthalten)
        $this->admin = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin->givePermissionTo([
            Permission::DomainProviderView->value,
            Permission::DomainProviderManage->value,
            Permission::DomainViewAny->value,
            Permission::DomainView->value,
        ]);
    }

    public function test_connection_index_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('admin.domain-provider.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.domain-provider.index'))->assertOk();
    }

    public function test_store_creates_and_activates_connection(): void {
        FakeDomainResellingTransport::fake(['StatusUser' => "code=200\ndescription=ok\nEOF\n"]);

        $this->actingAs($this->admin)->post(route('admin.domain-provider.store'), [
            'name' => 'DR Test',
            'environment' => 'ote',
            'login' => 'reseller1',
            'password' => 'secret-pw',
        ])->assertRedirect(route('admin.domain-provider.index'));

        $connection = DomainProviderConnection::query()->where('login', 'reseller1')->firstOrFail();
        $this->assertSame(DomainConnectionStatus::Active, $connection->status);
    }

    public function test_portfolio_index_and_detail_render(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'meine.de',
            'domain_hash' => DomainProjection::hashFor('meine.de'),
        ]);

        $this->actingAs($this->admin)->get(route('domains.index'))->assertOk()->assertSee('meine.de');
        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()
            ->assertSee('meine.de')
            // Rechnungs-Reiter erklärt die API-Grenze (Blocked-State).
            ->assertSee('keine', false);
    }

    public function test_other_org_domain_is_not_visible(): void {
        $otherOrg = Organization::factory()->create();
        $otherConnection = DomainProviderConnection::factory()->create(['organization_id' => $otherOrg->id]);
        $foreign = DomainProjection::factory()->create([
            'organization_id' => $otherOrg->id,
            'connection_id' => $otherConnection->id,
            'external_domain' => 'fremd.de',
            'domain_hash' => DomainProjection::hashFor('fremd.de'),
        ]);

        $this->actingAs($this->admin)->get(route('domains.show', $foreign))->assertNotFound();
    }

    /** Vollaudit 2026-07 (M34): Kundenakte zeigt zugeordnete Domains (Feature 083, MVP-394). */
    public function test_customer_file_shows_assigned_domains(): void {
        $customer = \App\Models\Customer::factory()->create(['organization_id' => $this->organization->id]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'customer_id' => $customer->id,
            'external_domain' => 'kundendomain.de',
            'domain_hash' => DomainProjection::hashFor('kundendomain.de'),
        ]);
        DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'andere.de',
            'domain_hash' => DomainProjection::hashFor('andere.de'),
        ]);

        $this->actingAs($this->admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('kundendomain.de')
            ->assertDontSee('andere.de');

        // Ohne domain.viewAny bleibt der Reiter unsichtbar.
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($stranger)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee('kundendomain.de');
    }

    public function test_customer_and_foreign_customer_assignment_via_http(): void {
        $this->admin->givePermissionTo(Permission::DomainCustomerAssign->value);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'zuweisung.de',
            'domain_hash' => DomainProjection::hashFor('zuweisung.de'),
        ]);

        // Endkunde eines ANDEREN Kunden wird abgelehnt.
        $this->actingAs($this->admin)->post(route('domains.customer', $domain), [
            'customer' => $other->sqid,
            'foreign_customer' => $foreign->sqid,
        ])->assertSessionHas('error');
        $this->assertNull($domain->refresh()->customer_id);

        // Passender Endkunde wird mit dem Kunden gespeichert.
        $this->actingAs($this->admin)->post(route('domains.customer', $domain), [
            'customer' => $customer->sqid,
            'foreign_customer' => $foreign->sqid,
        ])->assertSessionHas('success');
        $domain->refresh();
        $this->assertSame($customer->id, $domain->customer_id);
        $this->assertSame($foreign->id, $domain->foreign_customer_id);
    }

    public function test_own_holding_toggle_clears_customer_mapping(): void {
        $this->admin->givePermissionTo(Permission::DomainCustomerAssign->value);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'customer_id' => $customer->id,
            'external_domain' => 'eigene.de',
            'domain_hash' => DomainProjection::hashFor('eigene.de'),
        ]);

        $this->actingAs($this->admin)->post(route('domains.customer', $domain), ['own' => '1'])->assertSessionHas('success');
        $domain->refresh();
        $this->assertTrue($domain->is_own_holding);
        $this->assertNull($domain->customer_id);

        $this->actingAs($this->admin)->post(route('domains.customer', $domain), ['own' => '0']);
        $this->assertFalse($domain->refresh()->is_own_holding);
    }

    public function test_reseller_assignment_groups_domains_in_customer_file(): void {
        $this->admin->givePermissionTo(Permission::DomainCustomerAssign->value);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $reseller = DomainResellerAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_user' => 'lds-systems',
        ]);
        DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'reseller_account_id' => $reseller->id,
            'external_domain' => 'lds-kunde.de',
            'domain_hash' => DomainProjection::hashFor('lds-kunde.de'),
        ]);

        $this->actingAs($this->admin)
            ->post(route('domain-reseller.customer', $reseller), ['customer' => $customer->sqid])
            ->assertSessionHas('success');
        $this->assertSame($customer->id, $reseller->refresh()->customer_id);

        // Kundenakte gruppiert die Subuser-Domain ohne Einzelzuordnung.
        $this->actingAs($this->admin)->get(route('customers.show', $customer))->assertOk()->assertSee('lds-kunde.de');
    }

    public function test_show_renders_customer_suggestions(): void {
        $this->admin->givePermissionTo(Permission::DomainCustomerAssign->value);
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Vorschlag GmbH',
            'email' => 'info@vorschlag.de',
        ]);
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $domain = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_domain' => 'vorschlag.de',
            'domain_hash' => DomainProjection::hashFor('vorschlag.de'),
        ]);

        $this->actingAs($this->admin)->get(route('domains.show', $domain))->assertOk()->assertSee('Vorschlag GmbH');
    }

    public function test_free_plan_blocks_domain_module(): void {
        $freeOrg = Organization::factory()->free()->create();
        app()->instance('currentOrganization', $freeOrg);
        $user = User::factory()->user()->create(['organization_id' => $freeOrg->id]);
        $user->givePermissionTo(Permission::DomainViewAny->value);

        $this->actingAs($user)->get(route('domains.index'))->assertStatus(423);
    }
}
