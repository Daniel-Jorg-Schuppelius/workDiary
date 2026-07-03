<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Inventory\{StockMovementType, StockState};
use App\Models\{Article, ArticleVariant, ManufacturingOrder, ManufacturingOrderReport, Organization, StockMovement, Warehouse};
use App\Services\Inventory\InventoryLedger;
use App\Services\Manufacturing\ManufacturingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Teilrückmeldungen (Feature 047, MVP-065): Gut-/Ausschuss-/Nacharbeitsmenge
 * getrennt, kumulierte Gutmenge, Einlagerung der Gutmenge als Fertigerzeugnis.
 */
final class ManufacturingReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ManufacturingReportService $reports;
    private InventoryLedger $ledger;
    private Warehouse $warehouse;
    private ArticleVariant $productVariant;
    private ManufacturingOrder $order;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->reports = app(ManufacturingReportService::class);
        $this->ledger = app(InventoryLedger::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $product = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->productVariant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $product->id,
            'is_default' => true,
            'option_signature' => 'default',
        ]);
        $this->order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $product->id,
            'article_variant_id' => $this->productVariant->id,
            'warehouse_id' => $this->warehouse->id,
            'target_qty' => '10',
        ]);
    }

    public function test_reports_track_good_scrap_rework_separately_and_accumulate(): void {
        $this->reports->report($this->order, producedQty: '5', goodQty: '4', scrapQty: '1');
        $this->reports->report($this->order, producedQty: '6', goodQty: '6', reworkQty: '2');

        $this->assertSame('10.0000', $this->order->goodTotal());
        $this->assertSame('1.0000', $this->order->scrapTotal());
        $this->assertSame('0.0000', $this->order->openQuantity());
        $this->assertSame(2, ManufacturingOrderReport::query()->count());
    }

    public function test_good_quantity_is_received_into_stock(): void {
        $this->reports->report($this->order, producedQty: '5', goodQty: '5');

        $this->assertSame('5.0000', $this->ledger->balance($this->productVariant, $this->warehouse, StockState::Physical));
    }

    public function test_good_can_be_recorded_without_receiving_stock(): void {
        $this->reports->report($this->order, producedQty: '5', goodQty: '5', receiveGood: false);

        $this->assertSame('0.0000', $this->ledger->balance($this->productVariant, $this->warehouse, StockState::Physical));
        $this->assertSame('5.0000', $this->order->goodTotal());
    }

    public function test_scrap_is_recorded_as_stock_movement(): void {
        $this->reports->report($this->order, producedQty: '5', goodQty: '4', scrapQty: '1');

        // Ausschuss steht als eigene Journalbewegung im Zustand `scrap` …
        $this->assertSame('1.0000', $this->ledger->balance($this->productVariant, $this->warehouse, StockState::Scrap));

        // … ohne physischen oder verfügbaren Bestand zu verändern.
        $this->assertSame('4.0000', $this->ledger->balance($this->productVariant, $this->warehouse, StockState::Physical));
        $this->assertSame('4.0000', $this->ledger->available($this->productVariant, $this->warehouse));

        $movement = StockMovement::query()->where('movement_type', StockMovementType::Scrap->value)->first();
        $this->assertNotNull($movement);
        $this->assertSame($this->order->id, (int) $movement->source_id);
        $this->assertSame($this->order->getMorphClass(), $movement->source_type);
    }

    public function test_zero_scrap_creates_no_scrap_movement(): void {
        $this->reports->report($this->order, producedQty: '5', goodQty: '5');

        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovementType::Scrap->value)->count());
    }

    public function test_parent_order_is_isolated_per_organization(): void {
        $this->reports->report($this->order, producedQty: '1', goodQty: '1');
        $this->assertSame(1, ManufacturingOrderReport::query()->count());
        $this->assertSame(1, ManufacturingOrder::query()->count());

        // Auftrag ist org-gescopt; in Fremd-Org sind Auftrag (und damit seine
        // Rückmeldungen über die Relation) nicht erreichbar.
        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        $this->assertSame(0, ManufacturingOrder::query()->count());
    }
}
