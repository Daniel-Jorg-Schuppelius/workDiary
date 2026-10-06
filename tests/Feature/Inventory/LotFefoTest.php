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
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockLot, StockValuationLayer, Warehouse};
use App\Models\Platform\User;
use App\Services\Inventory\{FefoValuationService, InventoryValuationManager, LotService, LotStockReader};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Chargen + FEFO (Feature 047/048, E2/E3): Chargen sind je Variante eindeutig,
 * die Entnahme räumt zuerst das früheste Verfallsdatum, und die MHD-Überwachung
 * meldet bald verfallende Restbestände. Abgänge tragen ihre Charge; jeder Teil
 * verbraucht zuerst die Schichten seiner Charge.
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

        $issue = $this->fefo->issue($this->variant, $this->warehouse, '5');

        // FEFO entnimmt aus SOON (3,00) trotz späteren Zugangs: 5 × 3 = 15.
        $this->assertSame('15.0000', $issue->costTotal());
        $this->assertSame([$soon->id], array_map(fn (StockLot $lot): int => $lot->id, $issue->lots()));
        $this->assertSame('5.0000', $this->layerQty($soon));
        $this->assertSame('10.0000', $this->layerQty($late));
    }

    /** Gewählte Charge: ihre Schichten zuerst, auch gegen die FEFO-Folge; nur ihr Buchbestand sinkt. */
    public function test_chosen_lot_consumes_its_own_layers_first(): void {
        $late = $this->lots->register($this->variant, 'LATE', '2026-12-31');
        $soon = $this->lots->register($this->variant, 'SOON', '2026-07-01');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '10', '2', $late);
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '10', '3', $soon);

        $issue = $this->fefo->issue($this->variant, $this->warehouse, '4', lot: $late);

        $this->assertCount(1, $issue);
        $this->assertSame([$late->id, '-4.0000', '8.0000', '2.0000'], [$issue->first()->stock_lot_id, $issue->first()->qty_base, $issue->costTotal(), $issue->costUnit()]);
        $this->assertSame(['6.0000', '10.0000'], [$this->layerQty($late), $this->layerQty($soon)]);
        $this->assertSame(['6.0000', '10.0000'], [$this->stock()->balanceOf($late), $this->stock()->balanceOf($soon)]);
    }

    /** Über zwei Chargen: eine Bewegung je Charge, Kosten je Teil aus ihrer Charge, Summe für den Abgang. */
    public function test_fefo_issue_across_two_lots_books_one_movement_per_lot(): void {
        $late = $this->lots->register($this->variant, 'LATE', '2026-12-31');
        $soon = $this->lots->register($this->variant, 'SOON', '2026-07-01');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '10', '2', $late);
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '10', '3', $soon);

        $issue = $this->fefo->issue($this->variant, $this->warehouse, '15');

        $this->assertSame(
            [[$soon->id, '-10.0000', '30.0000'], [$late->id, '-5.0000', '10.0000']],
            array_map(fn ($m): array => [$m->stock_lot_id, $m->qty_base, $m->cost_total?->getAmount()], $issue->movements),
        );
        $this->assertSame(['15.0000', '40.0000'], [$issue->qty(), $issue->costTotal()]);
        $this->assertSame(['0.0000', '5.0000'], [$this->stock()->balanceOf($soon), $this->stock()->balanceOf($late)]);
        $this->assertSame(['0.0000', '5.0000'], [$this->layerQty($soon), $this->layerQty($late)]);
    }

    /** Der Teil ohne Charge verbraucht zuerst chargenlose Schichten — nicht die einer gesperrten Charge. */
    public function test_unlotted_part_consumes_unlotted_layers_first(): void {
        $held = $this->lots->register($this->variant, 'HELD', '2026-07-01');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '5', '3', $held);
        $this->fefo->receipt($this->variant, $this->warehouse, '5', '7');
        $this->lots->block($held, 'Reklamation', User::factory()->create(['organization_id' => $this->organization->id]));

        $issue = $this->fefo->issue($this->variant, $this->warehouse, '2');

        $this->assertSame([null, '14.0000'], [$issue->first()->stock_lot_id, $issue->costTotal()]);
        $this->assertSame('5.0000', $this->layerQty($held));
        $this->assertSame('5.0000', $this->stock()->balanceOf($held));
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

    /** Der Chargenbestand ist der Buchsaldo: er sinkt, weil der Abgang die Charge trägt. */
    public function test_receipt_tags_movement_with_lot_and_onhand_tracks_remaining(): void {
        $lot = $this->lots->register($this->variant, 'TAG', '2026-09-01');
        $movement = $this->lots->receiveIntoLot($this->variant, $this->warehouse, '8', '2', $lot);

        $this->assertSame($lot->id, $movement->stock_lot_id);
        $this->assertSame('8.0000', $this->stock()->balanceOf($lot));

        $issue = $this->fefo->issue($this->variant, $this->warehouse, '3');
        $this->assertSame($lot->id, $issue->first()->stock_lot_id);
        $this->assertSame('5.0000', $this->stock()->balanceOf($lot));
        $this->assertSame('5.0000', $this->stock()->balanceOf($lot, $this->warehouse));
    }

    public function test_manager_resolves_fefo(): void {
        $this->organization->update(['settings' => ['valuation_method' => 'fefo']]);

        $strategy = app(InventoryValuationManager::class)->for($this->organization->fresh());

        $this->assertInstanceOf(FefoValuationService::class, $strategy);
        $this->assertSame(ValuationMethod::Fefo, $strategy->method());
    }

    private function stock(): LotStockReader {
        return app(LotStockReader::class);
    }

    private function layerQty(StockLot $lot): string {
        return (string) StockValuationLayer::query()->where('stock_lot_id', $lot->id)->value('qty_remaining');
    }
}
