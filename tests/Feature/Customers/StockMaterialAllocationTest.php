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

use App\Enums\Inventory\{StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockMovement, Warehouse};
use App\Models\Platform\User;
use App\Services\Inventory\{CustomerStockAllocationService, InventoryLedger, LotService, LotStockReader, ValuationService};
use App\Support\MorphMap;
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
        $this->assertSame(MorphMap::alias(StockMovement::class), $allocation->source_type);
        $this->assertSame('7.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
    }

    /** Toolkit-Audit 2026-09: rtrim machte aus der Menge 10 eine 1. */
    public function test_allocation_description_keeps_whole_quantities(): void {
        $this->actingAs($this->admin)
            ->post(route('customers.material-costs.stock.store', $this->customer), [
                'variant_id' => $this->variant->sqid,
                'warehouse_id' => $this->warehouse->sqid,
                'qty' => '10',
                'allocated_on' => now()->toDateString(),
            ]);

        $allocation = $this->customer->materialCostAllocations()->firstOrFail();
        $this->assertMatchesRegularExpression('/\(10\b/', (string) $allocation->description);
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

    /** Über Charge und Rest ohne Charge: eine Zuordnung mit der Summe; die Rückbuchung trifft jede Bewegung in ihrer Charge. */
    public function test_allocation_across_lot_and_unlotted_stock_returns_per_lot(): void {
        $lots = app(LotService::class);
        $lot = $lots->register($this->variant, 'K-CH1', '2026-12-01');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '2', '5', $lot);
        $service = app(CustomerStockAllocationService::class);

        $allocation = $service->issueForCustomer($this->customer, $this->variant, $this->warehouse, '4', actorUserId: $this->admin->id);

        $issued = StockMovement::query()->where('movement_type', StockMovementType::Issue->value)->orderBy('id')->get();
        $this->assertSame([[$lot->id, '-2.0000'], [null, '-2.0000']], $issued->map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->qty_base])->all());
        $this->assertSame(20.0, $allocation->allocated_amount?->toFloat());
        $this->assertSame([MorphMap::alias(StockMovement::class), $issued[0]->id], [$allocation->source_type, $allocation->source_id]);
        $this->assertSame(1, $this->customer->materialCostAllocations()->count());
        $this->assertSame('0.0000', app(LotStockReader::class)->balanceOf($lot));

        $service->reverse($allocation);

        $returned = StockMovement::query()->where('movement_type', StockMovementType::Return->value)->orderBy('id')->get();
        $this->assertSame([[$lot->id, '2.0000', $issued[0]->id], [null, '2.0000', $issued[1]->id]], $returned->map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->qty_base, $m->source_id])->all());
        $this->assertSame('2.0000', app(LotStockReader::class)->balanceOf($lot));
        $this->assertSame('12.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
    }

    /** Gewählte Charge: die Zuordnung entnimmt genau sie, auch wenn eine andere früher verfällt. */
    public function test_allocation_with_a_chosen_lot_books_that_lot(): void {
        $lots = app(LotService::class);
        $early = $lots->register($this->variant, 'K-EARLY', '2026-11-01');
        $late = $lots->register($this->variant, 'K-LATE', '2027-03-01');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '3', '5', $early);
        $lots->receiveIntoLot($this->variant, $this->warehouse, '3', '5', $late);

        app(CustomerStockAllocationService::class)->issueForCustomer($this->customer, $this->variant, $this->warehouse, '2', actorUserId: $this->admin->id, lot: $late);

        $this->assertSame(['3.0000', '1.0000'], [app(LotStockReader::class)->balanceOf($early), app(LotStockReader::class)->balanceOf($late)]);
    }

    /** Die Rückbuchung in eine inzwischen gesperrte Charge scheitert nicht: die Menge wird mitgesperrt (D5). */
    public function test_reverse_into_a_blocked_lot_keeps_the_returned_quantity_blocked(): void {
        $ledger = app(InventoryLedger::class);
        $lots = app(LotService::class);
        $lot = $lots->register($this->variant, 'K-HOLD', '2026-12-01');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '4', '5', $lot);
        $service = app(CustomerStockAllocationService::class);
        $allocation = $service->issueForCustomer($this->customer, $this->variant, $this->warehouse, '3', actorUserId: $this->admin->id, lot: $lot);
        $lots->block($lot, 'Rückruf', $this->admin);
        $this->assertSame('10.0000', $ledger->available($this->variant, $this->warehouse));

        $service->reverse($allocation);

        $return = StockMovement::query()->where('movement_type', StockMovementType::Return->value)->sole();
        $hold = StockMovement::query()->where('idempotency_key', 'lot-block:' . $lot->id . ':arrival:' . $return->id)->sole();
        $this->assertSame([StockMovementType::LotBlock, StockState::Blocked, '3.0000', $lot->id], [$hold->movement_type, $hold->stock_state, $hold->qty_base, $hold->stock_lot_id]);
        $this->assertSame(['4.0000', '4.0000'], [app(LotStockReader::class)->balanceOf($lot), $ledger->balance($this->variant, $this->warehouse, StockState::Blocked)]);
        $this->assertSame('10.0000', $ledger->available($this->variant, $this->warehouse));

        // Die Freigabe gibt auch die mitgesperrte Menge frei.
        $lots->unblock($lot, 'Prüfung ohne Befund', $this->admin);
        $this->assertSame(['0.0000', '14.0000'], [$ledger->balance($this->variant, $this->warehouse, StockState::Blocked), $ledger->available($this->variant, $this->warehouse)]);
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
        $this->assertSame(MorphMap::alias(StockMovement::class), $allocation->source_type);
        $this->assertSame('8.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
    }
}
