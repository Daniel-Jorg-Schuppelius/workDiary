<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BarcodeScanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{BarcodeMatchType, ScanAction, SerialSource, StockMovementType};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockLot, StockMovement, Warehouse};
use App\Services\Inventory\{BarcodeResolver, InventoryLedger, LabelService, LotService, LotStockReader, PickListBuilder, ScanActionService, SerialService, StockIssue};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Scanner-/Barcode-Workflow (Feature 048, E5): Code-Auflösung (Serie/Charge/
 * Variante/Artikel), mobile Buchung per Scan und Etikettendaten.
 */
final class BarcodeScanTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Article $article;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'gtin' => 'AGT-1', 'base_unit' => 'Stk']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default', 'sku' => 'SKU-1', 'gtin' => 'GTV-1',
        ]);
    }

    public function test_resolves_code_by_specificity(): void {
        $resolver = app(BarcodeResolver::class);
        app(SerialService::class)->register($this->variant, 'SER-1', SerialSource::Manufactured, $this->warehouse);
        app(LotService::class)->register($this->variant, 'LOT-1', '2026-12-31');

        $this->assertSame(BarcodeMatchType::Serial, $resolver->resolve('SER-1')->type);
        $this->assertSame(BarcodeMatchType::Lot, $resolver->resolve('LOT-1')->type);
        $this->assertSame(BarcodeMatchType::Variant, $resolver->resolve('SKU-1')->type);
        $this->assertSame(BarcodeMatchType::Variant, $resolver->resolve('GTV-1')->type);

        $articleMatch = $resolver->resolve('AGT-1');
        $this->assertSame(BarcodeMatchType::Article, $articleMatch->type);
        $this->assertSame($this->variant->id, $articleMatch->variant?->id);

        $unknown = $resolver->resolve('NOPE');
        $this->assertSame(BarcodeMatchType::Unknown, $unknown->type);
        $this->assertFalse($unknown->found());
    }

    public function test_scan_books_receipt_issue_and_transfer(): void {
        $scan = app(ScanActionService::class);
        $ledger = app(\App\Services\Inventory\InventoryLedger::class);
        $target = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $scan->book('SKU-1', ScanAction::Receipt, $this->warehouse, '10');
        $this->assertSame('10.0000', $ledger->available($this->variant, $this->warehouse));

        $scan->book('SKU-1', ScanAction::Issue, $this->warehouse, '3');
        $this->assertSame('7.0000', $ledger->available($this->variant, $this->warehouse));

        $scan->book('SKU-1', ScanAction::Transfer, $this->warehouse, '2', ['target' => $target]);
        $this->assertSame('5.0000', $ledger->available($this->variant, $this->warehouse));
        $this->assertSame('2.0000', $ledger->available($this->variant, $target));
    }

    /** Umlagerung je Charge (E4): FEFO-Zuteilung, je Teil Abgang und Zugang mit derselben Charge — im Ziel-Lager pickbar. */
    public function test_transfer_keeps_the_lot_and_the_target_suggests_it_fefo(): void {
        $lots = app(LotService::class);
        $stock = app(LotStockReader::class);
        $target = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $early = $lots->register($this->variant, 'L-EARLY', '2026-05-01');
        $late = $lots->register($this->variant, 'L-LATE', '2026-09-01');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '4', '2', $early);
        $lots->receiveIntoLot($this->variant, $this->warehouse, '3', '2', $late);
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '2');

        $transfer = app(ScanActionService::class)->book('SKU-1', ScanAction::Transfer, $this->warehouse, '6', ['target' => $target]);

        $this->assertInstanceOf(StockIssue::class, $transfer);
        $this->assertSame(['L-EARLY', 'L-LATE'], array_map(fn (StockLot $lot): string => $lot->lot_no, $transfer->lots()));
        $this->assertSame(
            [[$early->id, '4.0000'], [$late->id, '2.0000']],
            StockMovement::query()->where('movement_type', StockMovementType::TransferIn->value)->orderBy('id')->get()
                ->map(fn (StockMovement $m): array => [$m->stock_lot_id, $m->qty_base])->all(),
        );
        $this->assertSame(['0.0000', '1.0000'], [$stock->balanceOf($early, $this->warehouse), $stock->balanceOf($late, $this->warehouse)]);
        $this->assertSame(['4.0000', '2.0000'], [$stock->balanceOf($early, $target), $stock->balanceOf($late, $target)]);

        $list = app(PickListBuilder::class)->fromLines([['variant' => $this->variant, 'warehouse' => $target, 'qty' => '5.0000']]);
        $this->assertSame(
            [['L-EARLY', '4.0000'], ['L-LATE', '1.0000']],
            array_map(fn ($line): array => [$line->lot?->lot_no, $line->qty], $list->lines),
        );
    }

    /** Ein gescannter Chargencode lagert genau diese Charge um, auch wenn eine andere früher verfällt. */
    public function test_transfer_of_a_scanned_lot_moves_exactly_that_lot(): void {
        $lots = app(LotService::class);
        $target = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $early = $lots->register($this->variant, 'L-EARLY', '2026-05-01');
        $late = $lots->register($this->variant, 'L-LATE', '2026-09-01');
        $lots->receiveIntoLot($this->variant, $this->warehouse, '4', '2', $early);
        $lots->receiveIntoLot($this->variant, $this->warehouse, '3', '2', $late);

        app(ScanActionService::class)->book('L-LATE', ScanAction::Transfer, $this->warehouse, '2', ['target' => $target]);

        $this->assertSame(['0.0000', '2.0000'], [app(LotStockReader::class)->balanceOf($early, $target), app(LotStockReader::class)->balanceOf($late, $target)]);
    }

    public function test_scan_unknown_code_throws(): void {
        $this->expectException(\RuntimeException::class);
        app(ScanActionService::class)->book('NOPE', ScanAction::Receipt, $this->warehouse, '1');
    }

    public function test_label_data_for_variant_serial_lot(): void {
        $labels = app(LabelService::class);
        $serial = app(SerialService::class)->register($this->variant, 'SER-9', SerialSource::Manufactured, $this->warehouse);
        $lot = app(LotService::class)->register($this->variant, 'LOT-9', '2027-01-31');

        $variantLabel = $labels->forVariant($this->variant);
        $this->assertSame('GTV-1', $variantLabel['code']);
        $this->assertSame('gtin', $variantLabel['code_type']);

        $this->assertSame('SER-9', $labels->forSerial($serial)['code']);
        $this->assertSame('serial', $labels->forSerial($serial)['code_type']);

        $lotLabel = $labels->forLot($lot);
        $this->assertSame('LOT-9', $lotLabel['code']);
        $this->assertSame('lot', $lotLabel['code_type']);
    }
}
