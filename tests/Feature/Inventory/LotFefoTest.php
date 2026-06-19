<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotFefoTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\ValuationMethod;
use App\Models\{Article, ArticleVariant, StockLot, StockValuationLayer, Warehouse};
use App\Services\Inventory\{FefoValuationService, InventoryValuationManager, LotService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Chargen + FEFO (Feature 047/048, E2/E3): Chargen sind je Variante eindeutig,
 * die Entnahme räumt zuerst das früheste Verfallsdatum, und die MHD-Überwachung
 * meldet bald verfallende Restbestände.
 */
final class LotFefoTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LotService $lots;
    private FefoValuationService $fefo;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->lots = app(LotService::class);
        $this->fefo = app(FefoValuationService::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'batch_required' => true]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
    }

    public function test_register_is_unique_and_rejects_empty(): void {
        $a = $this->lots->register($this->variant, 'L1', '2026-12-31');
        $b = $this->lots->register($this->variant, 'L1');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, StockLot::query()->count());

        $this->expectException(RuntimeException::class);
        $this->lots->register($this->variant, '   ');
    }

    public function test_fefo_consumes_earliest_expiry_first(): void {
        $late = $this->lots->register($this->variant, 'LATE', '2026-12-31');
        $soon = $this->lots->register($this->variant, 'SOON', '2026-07-01');

        // Zuerst die spät verfallende Charge erhalten, danach die bald verfallende.
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '10', '2', $late);
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '10', '3', $soon);

        $movement = $this->fefo->issue($this->variant, $this->warehouse, '5');

        // FEFO entnimmt aus SOON (3,00) trotz späteren Zugangs: 5 × 3 = 15.
        $this->assertSame('15.0000', $movement->cost_total);
        $this->assertSame('5.0000', $this->layerQty($soon));
        $this->assertSame('10.0000', $this->layerQty($late));
    }

    public function test_expiring_until_reports_only_due_layers(): void {
        $late = $this->lots->register($this->variant, 'LATE', '2026-12-31');
        $soon = $this->lots->register($this->variant, 'SOON', '2026-07-01');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '10', '2', $late);
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '5', '3', $soon);

        $due = $this->lots->expiringUntil(Carbon::parse('2026-08-01'));

        $this->assertCount(1, $due);
        $this->assertSame($soon->id, $due->first()?->stock_lot_id);
    }

    public function test_receipt_tags_movement_with_lot_and_onhand_tracks_remaining(): void {
        $lot = $this->lots->register($this->variant, 'TAG', '2026-09-01');
        $movement = $this->lots->receiveIntoLot($this->variant, $this->warehouse, '8', '2', $lot);

        $this->assertSame($lot->id, $movement->stock_lot_id);
        $this->assertSame('8.0000', $this->lots->onHand($lot));

        $this->fefo->issue($this->variant, $this->warehouse, '3');
        $this->assertSame('5.0000', $this->lots->onHand($lot));
    }

    public function test_manager_resolves_fefo(): void {
        $this->organization->update(['settings' => ['valuation_method' => 'fefo']]);

        $strategy = app(InventoryValuationManager::class)->for($this->organization->fresh());

        $this->assertInstanceOf(FefoValuationService::class, $strategy);
        $this->assertSame(ValuationMethod::Fefo, $strategy->method());
    }

    private function layerQty(StockLot $lot): string {
        return (string) StockValuationLayer::query()->where('stock_lot_id', $lot->id)->value('qty_remaining');
    }
}
