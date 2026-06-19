<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Manufacturing\ProcurementStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\{Article, ArticleSupply, ArticleVariant, ProcurementRequest, Supplier, Warehouse};
use App\Services\Inventory\{InventoryLedger, StockLevelService};
use App\Services\Procurement\{GoodsReceiptService, ProcurementSuggestionService, PurchaseOrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Beschaffung (Feature 048, E4): Bestellung anlegen/bestellen, Wareneingang gegen
 * die Bestellzeile (Teil-/Überlieferung) und automatische Bestellvorschläge aus
 * Meldebestand + Anforderungen mit bevorzugter Bezugsquelle und MOQ/Verpackung.
 */
final class PurchaseOrderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Supplier $supplier;
    private Article $article;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
    }

    public function test_create_draft_and_add_line(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $line = $orders->addLine($po, $this->article, '10');

        $this->assertSame(PurchaseOrderStatus::Draft, $po->status);
        $this->assertStringStartsWith('BE-', $po->number);
        $this->assertSame('10.0000', $line->ordered_qty);
        $this->assertSame('Stk', $line->unit);
    }

    public function test_receipt_against_line_tracks_partial_then_full(): void {
        $orders = app(PurchaseOrderService::class);
        $receipts = app(GoodsReceiptService::class);
        $ledger = app(InventoryLedger::class);

        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $line = $orders->addLine($po, $this->article, '10', ['variant' => $this->variant, 'unit_price' => '2']);
        $orders->submit($po);
        $this->assertSame(PurchaseOrderStatus::Ordered, $po->fresh()->status);

        $receipts->receive($line, '4');
        $this->assertSame('4.0000', $line->fresh()->received_qty);
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $po->fresh()->status);
        $this->assertSame('4.0000', $ledger->available($this->variant, $this->warehouse));

        $receipts->receive($line, '6');
        $this->assertSame(PurchaseOrderStatus::Received, $po->fresh()->status);
        $this->assertSame('10.0000', $ledger->available($this->variant, $this->warehouse));
    }

    public function test_receipt_movement_references_purchase_order_line(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $line = $orders->addLine($po, $this->article, '5', ['variant' => $this->variant, 'unit_price' => '2']);
        $orders->submit($po);

        app(GoodsReceiptService::class)->receive($line, '5');

        $movement = \App\Models\StockMovement::query()
            ->where('article_variant_id', $this->variant->id)
            ->where('movement_type', 'receipt')
            ->latest('id')->firstOrFail();
        $this->assertSame($line->getMorphClass(), $movement->source_type);
        $this->assertSame($line->id, $movement->source_id);
    }

    public function test_overdelivery_completes_order(): void {
        $orders = app(PurchaseOrderService::class);
        $receipts = app(GoodsReceiptService::class);

        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $line = $orders->addLine($po, $this->article, '5', ['variant' => $this->variant]);
        $orders->submit($po);
        $receipts->receive($line, '7');

        $this->assertSame('7.0000', $line->fresh()->received_qty);
        $this->assertSame(PurchaseOrderStatus::Received, $po->fresh()->status);
    }

    public function test_receipt_blocked_before_ordering(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $line = $orders->addLine($po, $this->article, '5', ['variant' => $this->variant]);

        $this->expectException(\RuntimeException::class);
        app(GoodsReceiptService::class)->receive($line, '1');
    }

    public function test_suggestion_uses_preferred_supplier_and_rounds_to_moq_and_pack(): void {
        // Meldebestand 10, Bestand 0 → Bedarf 10.
        app(StockLevelService::class)->setLevels($this->variant, $this->warehouse, '5', '10');
        ArticleSupply::query()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'supplier_id' => $this->supplier->id, 'supplier_sku' => 'S-1',
            'moq' => '4', 'pack_size' => '6', 'purchase_price' => '2', 'is_preferred' => true,
        ]);

        $suggestions = app(ProcurementSuggestionService::class)->suggest($this->warehouse);

        $this->assertCount(1, $suggestions);
        $this->assertSame($this->supplier->id, $suggestions[0]['supplier_id']);
        $this->assertSame('10.0000', $suggestions[0]['needed']);
        $this->assertSame('12.0000', $suggestions[0]['suggested']); // max(10,MOQ4)=10 → auf 2×6 aufgerundet
    }

    public function test_create_orders_groups_by_supplier_and_marks_requests(): void {
        ArticleSupply::query()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'supplier_id' => $this->supplier->id, 'moq' => '1', 'pack_size' => '1',
            'purchase_price' => '3', 'is_preferred' => true,
        ]);
        $request = ProcurementRequest::query()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'quantity' => '7', 'status' => ProcurementStatus::Open->value,
        ]);

        $orders = app(ProcurementSuggestionService::class)->createOrders($this->warehouse, $this->organization);

        $this->assertCount(1, $orders);
        $this->assertSame($this->supplier->id, $orders[0]->supplier_id);
        $this->assertSame('7.0000', $orders[0]->lines()->firstOrFail()->ordered_qty);
        $this->assertSame(ProcurementStatus::Ordered, $request->fresh()->status);
    }
}
