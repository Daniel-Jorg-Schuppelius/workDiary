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
use App\Enums\Inventory\StockState;
use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Models\{Article, ArticleVariant, Customer, Organization, StockDelivery, Warehouse};
use App\Services\Inventory\InventoryLedger;
use App\Services\Manufacturing\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame('3.0000', $delivery->quantity);
        $this->assertSame('delivered', $delivery->stock_status);
        $this->assertSame(DeliveryFacturationStatus::Pending, $delivery->facturation_status);
        $this->assertSame('Widget rot', $delivery->name_snapshot);
        $this->assertSame('WID-ROT', $delivery->sku_snapshot);
        $this->assertSame('12.0000', $delivery->unit_price_snapshot);
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
        $this->assertSame('delivered', $fresh->stock_status, 'Lagerbuchung bleibt sichtbar');
        $this->assertSame('6.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
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
