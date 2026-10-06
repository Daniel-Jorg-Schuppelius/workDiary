<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Finance\BillingMode;
use App\Enums\Inventory\{StockMovementType, StockState};
use App\Enums\Manufacturing\{DeliveryFacturationStatus, DeliveryStockStatus};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Customer\Customer;
use App\Models\Inventory\{InventoryOutboxEntry, StockDelivery, StockMovement, Warehouse};
use App\Models\Platform\Organization;
use App\Services\Inventory\{InventoryLedger, LotService, LotStockReader};
use App\Services\Manufacturing\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Auslieferung + Faktura-Übergabe (Feature 047, MVP-074): Bestand der konkreten
 * Variante abbuchen, Positionssnapshot einfrieren, führendes Fakturasystem aus
 * der Kunden-Datenführerschaft ableiten und Lager-/Faktura-Status trennen.
 */
final class DeliveryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private DeliveryService $deliveries;
    private InventoryLedger $ledger;
    private Warehouse $warehouse;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->deliveries = app(DeliveryService::class);
        $this->ledger = app(InventoryLedger::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk', 'name' => 'Widget']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default',
            'name' => 'Widget rot',
            'sku' => 'WID-ROT',
            'sale_price' => '12.0000',
        ]);
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
    }

    public function test_deliver_deducts_stock_and_freezes_snapshot(): void {
        $delivery = $this->deliveries->deliver($this->variant, $this->warehouse, '3');

        $this->assertSame('7.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
        $this->assertSame('3.0000', $delivery->quantity?->getNumericValue());
        $this->assertSame(DeliveryStockStatus::Delivered, $delivery->stock_status);
        $this->assertSame(DeliveryFacturationStatus::Pending, $delivery->facturation_status);
        $this->assertSame('Widget rot', $delivery->name_snapshot);
        $this->assertSame('WID-ROT', $delivery->sku_snapshot);
        $this->assertSame('12.0000', $delivery->unit_price_snapshot?->getAmount());
        $this->assertSame('workdiary', $delivery->facturation_target);
    }

    public function test_insufficient_delivery_throws_and_creates_nothing(): void {
        try {
            $this->deliveries->deliver($this->variant, $this->warehouse, '50');
            $this->fail('Auslieferung über den Bestand muss scheitern.');
        } catch (RuntimeException) {
            // erwartet
        }

        $this->assertSame(0, StockDelivery::query()->count());
        $this->assertSame('10.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
    }

    public function test_facturation_failure_does_not_hide_stock_deduction(): void {
        $delivery = $this->deliveries->deliver($this->variant, $this->warehouse, '4');
        $this->deliveries->markFacturationResult($delivery, DeliveryFacturationStatus::Failed);

        $fresh = $delivery->fresh();
        $this->assertSame(DeliveryFacturationStatus::Failed, $fresh->facturation_status);
        $this->assertSame(DeliveryStockStatus::Delivered, $fresh->stock_status, 'Lagerbuchung bleibt sichtbar');
        $this->assertSame('6.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
    }

    /** Auslieferung je Charge (FEFO-Bewertung, externe Führung): je Charge eine bewertete, gespiegelte Bewegung. */
    public function test_delivery_issues_per_lot_and_mirrors_each_movement(): void {
        Bus::fake();
        $this->organization->update(['settings' => ['valuation_method' => 'fefo', 'inventory_mode' => 'external', 'inventory_plugin_id' => 'jtl_wawi']]);
        $lots = app(LotService::class);
        $late = $lots->register($this->variant, 'D-LATE', '2027-01-01');
        $soon = $lots->register($this->variant, 'D-SOON', '2026-11-01');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '2', '3', $late);
        $lots->receiveIntoLot($this->variant, $this->warehouse, '2', '4', $soon);

        $this->deliveries->deliver($this->variant, $this->warehouse, '3');

        $issued = StockMovement::query()->where('movement_type', StockMovementType::Issue->value)->orderBy('id')->get();
        $this->assertSame(
            [[$soon->id, '-2.0000', '8.0000'], [$late->id, '-1.0000', '3.0000']],
            $issued->map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->qty_base, $m->cost_total?->getAmount()])->all(),
        );
        $this->assertSame(['0.0000', '1.0000'], [app(LotStockReader::class)->balanceOf($soon), app(LotStockReader::class)->balanceOf($late)]);
        $this->assertEqualsCanonicalizing($issued->pluck('id')->all(), InventoryOutboxEntry::query()->where('operation', 'issue')->pluck('stock_movement_id')->all());
        $this->assertSame(1, StockDelivery::query()->count());
    }

    public function test_facturation_target_follows_customer_billing_mode(): void {
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'billing_mode' => BillingMode::Lexoffice->value,
        ]);

        $delivery = $this->deliveries->deliver($this->variant, $this->warehouse, '1', customer: $customer);
        $this->assertSame('lexoffice', $delivery->facturation_target);
    }

    public function test_deliveries_are_isolated_per_organization(): void {
        $this->deliveries->deliver($this->variant, $this->warehouse, '1');
        $this->assertSame(1, StockDelivery::query()->count());

        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        $this->assertSame(0, StockDelivery::query()->count());
    }
}
