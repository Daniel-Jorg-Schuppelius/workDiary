<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingInventoryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Inventory\StockState;
use App\Models\{Article, ArticleVariant, ManufacturingOrder, ProcedureMaterialRequirement, ProcedureTemplateVersion, Warehouse};
use App\Services\Inventory\InventoryLedger;
use App\Services\Manufacturing\{ManufacturingInventoryService, ManufacturingOrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fertigung ↔ Lager (Feature 047/048, MVP-071): Materialbedarf reservieren,
 * Ist-Verbrauch buchen, Fertigerzeugnis einlagern.
 */
final class ManufacturingInventoryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ManufacturingInventoryService $link;
    private ManufacturingOrderService $orders;
    private InventoryLedger $ledger;
    private Warehouse $warehouse;
    private ArticleVariant $materialVariant;
    private ArticleVariant $productVariant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->link = app(ManufacturingInventoryService::class);
        $this->orders = app(ManufacturingOrderService::class);
        $this->ledger = app(InventoryLedger::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $material = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->materialVariant = $this->variantFor($material);

        $version = ProcedureTemplateVersion::factory()->create();
        ProcedureMaterialRequirement::factory()->perUnit('2')->create([
            'procedure_template_version_id' => $version->id,
            'article_id' => $material->id,
        ]);

        $product = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'default_procedure_template_version_id' => $version->id,
        ]);
        $this->productVariant = $this->variantFor($product);
        $this->version = $version;
        $this->product = $product;
    }

    private ProcedureTemplateVersion $version;
    private Article $product;

    public function test_reserve_materials_reserves_against_stock(): void {
        $this->ledger->receipt($this->materialVariant, $this->warehouse, '100');
        $order = $this->releasedOrder('10'); // Bedarf 2 × 10 = 20

        $this->link->reserveMaterials($order);

        $material = $order->materials()->first();
        $this->assertSame('20.0000', $material->reserved_qty);
        $this->assertNotNull($material->stock_reservation_id);
        $this->assertSame('80.0000', $this->ledger->available($this->materialVariant, $this->warehouse));
    }

    public function test_reserve_is_partial_when_insufficient(): void {
        $this->ledger->receipt($this->materialVariant, $this->warehouse, '5'); // < 20 Bedarf
        $order = $this->releasedOrder('10');

        $this->link->reserveMaterials($order);

        $this->assertSame('5.0000', $order->materials()->first()->reserved_qty);
        $this->assertSame('0.0000', $this->ledger->available($this->materialVariant, $this->warehouse));
    }

    public function test_consume_books_actual_usage_via_reservation(): void {
        $this->ledger->receipt($this->materialVariant, $this->warehouse, '100');
        $order = $this->releasedOrder('10');
        $this->link->reserveMaterials($order);

        $this->link->consume($order->materials()->first(), '8');

        $this->assertSame('8.0000', $order->materials()->first()->consumed_qty);
        $this->assertSame('92.0000', $this->ledger->balance($this->materialVariant, $this->warehouse, StockState::Physical));
        $this->assertSame('12.0000', $this->ledger->balance($this->materialVariant, $this->warehouse, StockState::Reserved));
    }

    public function test_receive_finished_good_increases_product_stock(): void {
        $order = $this->releasedOrder('10');

        $this->link->receiveFinishedGood($order, '10');

        $this->assertSame('10.0000', $this->ledger->balance($this->productVariant, $this->warehouse, StockState::Physical));
    }

    public function test_full_cycle_reserve_consume_receive(): void {
        // Durchgehende Kette (MVP-071): Freigabe → Verfügbarkeit → Reservieren →
        // Verbrauchen → Fertigerzeugnis einlagern, mit Bestandsprüfung je Phase.
        $this->ledger->receipt($this->materialVariant, $this->warehouse, '100');
        $order = $this->releasedOrder('10'); // Materialbedarf 2 × 10 = 20

        $material = $order->materials()->first();
        $this->assertSame('20.0000', $material->target_qty); // eingefrorener Bedarf

        // Reservieren: 20 gesperrt, 80 verfügbar.
        $this->link->reserveMaterials($order);
        $this->assertSame('20.0000', $order->materials()->first()->reserved_qty);
        $this->assertSame('80.0000', $this->ledger->available($this->materialVariant, $this->warehouse));

        // Vollverbrauch: physischer Bestand 80, Reservierung aufgelöst.
        $this->link->consume($order->materials()->first(), '20');
        $this->assertSame('20.0000', $order->materials()->first()->consumed_qty);
        $this->assertSame('80.0000', $this->ledger->balance($this->materialVariant, $this->warehouse, StockState::Physical));
        $this->assertSame('0.0000', $this->ledger->balance($this->materialVariant, $this->warehouse, StockState::Reserved));

        // Fertigerzeugnis einlagern.
        $this->link->receiveFinishedGood($order, '10');
        $this->assertSame('10.0000', $this->ledger->balance($this->productVariant, $this->warehouse, StockState::Physical));
    }

    public function test_release_remaining_reservations_frees_unused_stock(): void {
        // Teilverbrauch lässt eine Restreservierung offen; deren Freigabe gibt den
        // gesperrten Bestand zurück in die Verfügbarkeit (MVP-071).
        $this->ledger->receipt($this->materialVariant, $this->warehouse, '100');
        $order = $this->releasedOrder('10');
        $this->link->reserveMaterials($order);

        $this->link->consume($order->materials()->first(), '12'); // 8 bleiben reserviert
        $this->assertSame('8.0000', $this->ledger->balance($this->materialVariant, $this->warehouse, StockState::Reserved));

        $released = $this->link->releaseRemainingReservations($order);

        $this->assertSame('8.0000', $released);
        $this->assertSame('0.0000', $this->ledger->balance($this->materialVariant, $this->warehouse, StockState::Reserved));
        $this->assertSame('88.0000', $this->ledger->available($this->materialVariant, $this->warehouse));
    }

    public function test_reserve_without_warehouse_throws(): void {
        $order = $this->orders->createDraft($this->organization, $this->product, $this->productVariant, '10', 'Stk');
        $this->orders->release($order);

        $this->expectException(RuntimeException::class);
        $this->link->reserveMaterials($order->fresh());
    }

    private function releasedOrder(string $targetQty): ManufacturingOrder {
        $order = $this->orders->createDraft(
            $this->organization, $this->product, $this->productVariant, $targetQty, 'Stk',
            ['warehouse_id' => $this->warehouse->id],
        );

        return $this->orders->release($order);
    }

    private function variantFor(Article $article): ArticleVariant {
        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default-' . $article->id,
        ]);
    }
}
