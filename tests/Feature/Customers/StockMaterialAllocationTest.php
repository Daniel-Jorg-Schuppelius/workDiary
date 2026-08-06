<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockMaterialAllocationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Customers;

use App\Models\{Article, ArticleVariant, Customer, StockMovement, User, Warehouse};
use App\Services\Inventory\{CustomerStockAllocationService, InventoryLedger, ValuationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class StockMaterialAllocationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private ArticleVariant $variant;

    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config(['license.feature_overrides' => ['module.lager' => true]]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->admin->id,
            'currency' => 'EUR',
        ]);

        $article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kabel',
            'base_unit' => 'Stk',
        ]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'sku' => 'K-1',
        ]);
        $this->warehouse = Warehouse::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Hauptlager',
            'is_default' => true,
            'active' => true,
        ]);

        // Anfangsbestand: 10 Stück zu 5,00 € (gleitender Durchschnitt).
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '10', '5', 'EUR', actorUserId: $this->admin->id);
    }

    public function test_issue_from_stock_creates_allocation_and_reduces_stock(): void {
        $this->actingAs($this->admin)
            ->from(route('customers.show', $this->customer))
            ->post(route('customers.material-costs.stock.store', $this->customer), [
                'variant_id' => $this->variant->sqid,
                'warehouse_id' => $this->warehouse->sqid,
                'qty' => '3',
                'allocated_on' => now()->toDateString(),
            ])
            ->assertRedirect(route('customers.show', $this->customer));

        $allocation = $this->customer->materialCostAllocations()->firstOrFail();
        $this->assertSame(15.0, $allocation->allocated_amount?->toFloat());
        $this->assertSame(StockMovement::class, $allocation->source_type);
        $this->assertSame('7.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
    }

    public function test_deleting_stock_allocation_returns_stock(): void {
        $allocation = app(CustomerStockAllocationService::class)
            ->issueForCustomer($this->customer, $this->variant, $this->warehouse, '3', actorUserId: $this->admin->id);

        $this->assertSame('7.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));

        $this->actingAs($this->admin)
            ->from(route('customers.show', $this->customer))
            ->delete(route('customers.material-costs.destroy', [$this->customer, $allocation]))
            ->assertRedirect(route('customers.show', $this->customer));

        $this->assertSoftDeleted('material_cost_allocations', ['id' => $allocation->id]);
        // Rückbuchung: Bestand wieder auf 10.
        $this->assertSame('10.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
        // Gegenbuchung ist als Return im Journal erkennbar.
        $this->assertSame(1, StockMovement::query()
            ->where('article_variant_id', $this->variant->id)
            ->where('movement_type', \App\Enums\Inventory\StockMovementType::Return->value)
            ->count());
    }

    public function test_stock_dialog_blocked_without_module(): void {
        config(['license.feature_overrides' => ['module.lager' => false]]);

        $this->actingAs($this->admin)
            ->get(route('customers.material-costs.stock.create', $this->customer))
            ->assertNotFound();
    }

    public function test_inventory_form_issue_books_customer_cost(): void {
        $this->actingAs($this->admin)
            ->from(route('inventory.stock'))
            ->post(route('inventory.movements.store'), [
                'warehouse' => $this->warehouse->sqid,
                'variant' => $this->variant->sqid,
                'movement' => 'issue',
                'qty' => '2',
                'ownership' => 'own',
                'cost_customer' => $this->customer->sqid,
            ])
            ->assertRedirect(route('inventory.stock', ['warehouse' => $this->warehouse->sqid]));

        $allocation = $this->customer->materialCostAllocations()->firstOrFail();
        $this->assertSame(10.0, $allocation->allocated_amount?->toFloat());
        $this->assertSame(StockMovement::class, $allocation->source_type);
        $this->assertSame('8.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
    }
}
