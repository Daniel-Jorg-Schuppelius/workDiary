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

use App\Enums\Inventory\{BarcodeMatchType, ScanAction, SerialSource};
use App\Models\{Article, ArticleVariant, Warehouse};
use App\Services\Inventory\{BarcodeResolver, LabelService, LotService, ScanActionService, SerialService};
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
