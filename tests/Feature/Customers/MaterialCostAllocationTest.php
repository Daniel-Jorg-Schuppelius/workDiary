<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialCostAllocationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Customers;

use App\Models\{Customer, LexofficeVoucher, MaterialCostAllocation, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class MaterialCostAllocationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $user;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
            'currency' => 'EUR',
        ]);
    }

    public function test_store_manual_allocation_creates_row(): void {
        $this->actingAs($this->admin)
            ->from(route('customers.show', $this->customer))
            ->post(route('customers.material-costs.store', $this->customer), [
                'allocated_amount' => '150.00',
                'allocated_on' => now()->toDateString(),
                'description' => 'Kabel & Kleinmaterial',
            ])
            ->assertRedirect(route('customers.show', $this->customer));

        $allocation = $this->customer->materialCostAllocations()->firstOrFail();
        $this->assertSame(150.0, $allocation->allocated_amount?->toFloat());
        $this->assertNull($allocation->source_type);
        $this->assertSame($this->organization->id, $allocation->organization_id);
    }

    public function test_manual_allocation_requires_description(): void {
        $this->actingAs($this->admin)
            ->from(route('customers.show', $this->customer))
            ->post(route('customers.material-costs.store', $this->customer), [
                'allocated_amount' => '20.00',
                'allocated_on' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('description');

        $this->assertSame(0, $this->customer->materialCostAllocations()->count());
    }

    public function test_store_links_purchase_voucher_as_source(): void {
        $voucher = $this->purchaseVoucher(500.0);

        $this->actingAs($this->admin)
            ->from(route('customers.show', $this->customer))
            ->post(route('customers.material-costs.store', $this->customer), [
                'voucher_id' => $voucher->sqid,
                'allocated_amount' => '200.00',
                'allocated_on' => now()->toDateString(),
            ])
            ->assertRedirect(route('customers.show', $this->customer));

        $allocation = $this->customer->materialCostAllocations()->firstOrFail();
        $this->assertSame(LexofficeVoucher::class, $allocation->source_type);
        $this->assertSame($voucher->id, (int) $allocation->source_id);
        $this->assertSame(200.0, $allocation->allocated_amount?->toFloat());
    }

    public function test_allocation_cannot_exceed_voucher_total(): void {
        $voucher = $this->purchaseVoucher(100.0);

        $this->actingAs($this->admin)
            ->from(route('customers.show', $this->customer))
            ->post(route('customers.material-costs.store', $this->customer), [
                'voucher_id' => $voucher->sqid,
                'allocated_amount' => '150.00',
                'allocated_on' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('allocated_amount');

        $this->assertSame(0, $this->customer->materialCostAllocations()->count());
    }

    public function test_project_must_belong_to_customer(): void {
        $otherCustomer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
        ]);
        $foreignProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $this->actingAs($this->admin)
            ->from(route('customers.show', $this->customer))
            ->post(route('customers.material-costs.store', $this->customer), [
                'allocated_amount' => '10.00',
                'allocated_on' => now()->toDateString(),
                'description' => 'Test',
                'project_id' => $foreignProject->sqid,
            ])
            ->assertSessionHasErrors('project_id');
    }

    public function test_destroy_removes_allocation(): void {
        $allocation = MaterialCostAllocation::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->from(route('customers.show', $this->customer))
            ->delete(route('customers.material-costs.destroy', [$this->customer, $allocation]))
            ->assertRedirect(route('customers.show', $this->customer));

        $this->assertSoftDeleted('material_cost_allocations', ['id' => $allocation->id]);
    }

    public function test_regular_user_cannot_allocate(): void {
        $this->actingAs($this->user)
            ->post(route('customers.material-costs.store', $this->customer), [
                'allocated_amount' => '10.00',
                'allocated_on' => now()->toDateString(),
                'description' => 'Test',
            ])
            ->assertForbidden();

        $this->assertSame(0, $this->customer->materialCostAllocations()->count());
    }

    public function test_show_page_renders_material_panel_with_profit(): void {
        MaterialCostAllocation::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'allocated_amount' => '42.00',
            'allocated_on' => now()->toDateString(),
            'description' => 'Materialposten X',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSeeText('Materialkosten & Gewinn')
            ->assertSeeText('Materialposten X');
    }

    private function purchaseVoucher(float $total): LexofficeVoucher {
        return LexofficeVoucher::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'ext-' . uniqid(),
            'voucher_type' => 'purchaseinvoice',
            'voucher_status' => 'open',
            'voucher_number' => 'EK-1',
            'voucher_date' => now()->toDateString(),
            'total_amount' => number_format($total, 2, '.', ''),
            'currency' => 'EUR',
            'archived' => false,
        ]);
    }
}
