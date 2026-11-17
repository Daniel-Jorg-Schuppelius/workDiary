<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotReceiptValuationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockMovement, StockValuation, StockValuationLayer, Warehouse};
use App\Models\Supplier\Supplier;
use App\Services\Inventory\{InventoryLedger, InventoryValuationManager, LotService, ValuationService};
use App\Services\Procurement\{GoodsReceiptService, PurchaseOrderService, SupplierScorecardService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1735: Der Eingang in eine Charge folgt dem Bewertungsverfahren der
 * Variante — beim gleitenden Durchschnitt ins Durchschnittsbuch (vorher nur
 * eine FEFO-Schicht, Abgänge drückten das Buch unter den Bestand), bei
 * FIFO/FEFO als Schicht mit Charge und MHD. Der Wareneingang der Bestellung
 * trägt die Bestellzeile als Beleg auch mit Charge.
 */
final class LotReceiptValuationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Article $article;

    private ArticleVariant $variant;

    private Warehouse $warehouse;

    private LotService $lots;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk', 'currency' => 'EUR', 'batch_required' => true]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default',
            'sku' => 'DICHTUNG-8',
            'currency' => 'EUR',
        ]);
        $this->lots = app(LotService::class);
    }

    private function book(): ?StockValuation {
        return StockValuation::query()->where('article_variant_id', $this->variant->id)->where('warehouse_id', $this->warehouse->id)->first();
    }

    /** @return numeric-string Bestandswert nach dem wirksamen Verfahren */
    private function stockValue(): string {
        $summary = app(InventoryValuationManager::class)->summariesForWarehouse($this->warehouse, $this->organization)[$this->variant->id] ?? null;

        return $summary['value'] ?? '0';
    }

    public function test_average_lot_receipt_goes_into_the_average_book(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '2', '4.00');
        $this->assertSame('8.0000', $this->stockValue());
        $lot = $this->lots->register($this->variant, 'CH-A', '2027-03-31');

        $movement = $this->lots->receiveIntoLot($this->variant, $this->warehouse, '6', '8.00', $lot);

        $this->assertSame([$lot->id, '8.0000', '48.0000'], [$movement->stock_lot_id, $movement->cost_unit?->getAmount(), $movement->cost_total?->getAmount()]);
        $this->assertFalse(StockValuationLayer::query()->where('article_variant_id', $this->variant->id)->exists(), 'Der Durchschnitt legt keine Chargenschicht an.');
        // (2 × 4 + 6 × 8) / 8 = 7
        $this->assertSame(['8.0000', '7.0000'], [bcadd($this->book()->qty_on_hand ?? '0', '0', 4), $this->book()?->avg_cost?->getAmount()]);
        $this->assertSame('56.0000', $this->stockValue());

        // Gegenprobe: der Abgang aus der Charge drückt das Buch nicht unter den Bestand.
        app(ValuationService::class)->issue($this->variant, $this->warehouse, '6', lot: $lot);
        $this->assertSame(
            app(InventoryLedger::class)->balance($this->variant, $this->warehouse, StockState::Physical),
            bcadd($this->book()->qty_on_hand ?? '0', '0', 4),
        );
        $this->assertSame('14.0000', $this->stockValue());
    }

    public function test_fifo_lot_receipt_adds_a_layer_with_lot_and_best_before(): void {
        $this->article->update(['valuation_method' => 'fifo']);
        $lot = $this->lots->register($this->variant, 'CH-F', '2027-05-31');

        $movement = $this->lots->receiveIntoLot($this->variant, $this->warehouse, '3', '5.00', $lot);

        $layer = StockValuationLayer::query()->where('source_movement_id', $movement->id)->sole();
        $this->assertSame(
            [$lot->id, '3.0000', '5.0000', '2027-05-31'],
            [(int) $layer->stock_lot_id, bcadd((string) $layer->qty_remaining, '0', 4), $layer->unit_cost?->getAmount(), $layer->best_before?->toDateString()],
        );
        $this->assertNull($this->book(), 'Das Durchschnittsbuch bleibt bei FIFO still.');
        $this->assertSame('15.0000', $this->stockValue());
    }

    public function test_goods_receipt_into_a_lot_carries_the_order_line_and_values_by_method(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $orders = app(PurchaseOrderService::class);
        $order = $orders->createDraft($this->organization, $supplier, $this->warehouse);
        $line = $orders->addLine($order, $this->article, '4', ['variant' => $this->variant, 'unit_price' => '2.50']);
        $orders->submit($order);

        app(GoodsReceiptService::class)->receive($line, '4', lotNo: 'CH-PO', bestBefore: '2027-01-31');

        $movement = StockMovement::query()->where('movement_type', StockMovementType::Receipt->value)->sole();
        $this->assertSame([$line->getMorphClass(), $line->id], [$movement->source_type, (int) $movement->source_id]);
        $this->assertNotNull($movement->stock_lot_id);
        $this->assertSame(['4.0000', '2.5000'], [bcadd($this->book()->qty_on_hand ?? '0', '0', 4), $this->book()?->avg_cost?->getAmount()]);
        // Die Lieferantenbewertung liest Wareneingänge über die Bestellzeile — vorher fehlte der Chargen-Eingang dort.
        $this->assertArrayHasKey($order->id, app(SupplierScorecardService::class)->deliveredAtByOrder($supplier));
    }

    public function test_without_organization_the_lot_receipt_books_only_the_quantity(): void {
        $lot = $this->lots->register($this->variant, 'CH-O', '2027-02-28');
        $this->warehouse->setRelation('organization', null);

        $movement = $this->lots->receiveIntoLot($this->variant, $this->warehouse, '2', '5.00', $lot);

        $this->assertSame([StockMovementType::Receipt, $lot->id, null], [$movement->movement_type, $movement->stock_lot_id, $movement->cost_total]);
        $this->assertNull($this->book());
        $this->assertFalse(StockValuationLayer::query()->where('article_variant_id', $this->variant->id)->exists());
    }
}
