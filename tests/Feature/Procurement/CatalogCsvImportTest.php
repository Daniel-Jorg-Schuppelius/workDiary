<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogCsvImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\CatalogItemStatus;
use App\Models\{SupplierCatalogItem, SupplierCatalogSource};
use App\Services\Procurement\CatalogCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-092/094: CSV-Katalogimport mit Mapping, Hash-basierter
 * Änderungserkennung, historisierten Einkaufspreisen und Abkündigung.
 */
final class CatalogCsvImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CatalogCsvImportService $importer;
    private SupplierCatalogSource $source;

    /** @var array<string, string> */
    private array $mapping = [
        'external_no' => 'ArtNr',
        'name' => 'Bezeichnung',
        'purchase_price' => 'EK',
        'gtin' => 'EAN',
    ];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->importer = app(CatalogCsvImportService::class);

        $supplier = \App\Models\Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $supplier->id,
            'name' => 'Test-Katalog',
            'format' => 'csv', 'delimiter' => ';', 'decimal_separator' => ',',
            'has_header' => true, 'encoding' => 'UTF-8',
        ]);
    }

    private function csv(string $ekA = '1,50'): string {
        return "ArtNr;Bezeichnung;EK;EAN\n"
            . "A-1;Schraube M4;{$ekA};4001234567890\n"
            . 'A-2;Mutter M4;0,80;4009876543210';
    }

    public function test_first_import_creates_items_and_price_snapshots(): void {
        $summary = $this->importer->import($this->source, $this->csv(), $this->mapping);

        $this->assertSame(['rows' => 2, 'created' => 2, 'updated' => 0, 'unchanged' => 0, 'price_changed' => 0, 'discontinued' => 0], $summary);

        $item = SupplierCatalogItem::query()->where('external_no', 'A-1')->firstOrFail();
        $this->assertSame('1.5000', $item->purchase_price);
        $this->assertSame('4001234567890', $item->gtin);
        $this->assertSame(CatalogItemStatus::New, $item->status);
        $this->assertSame(1, $item->prices()->count());
        $this->assertNotNull($this->source->fresh()->last_file_hash);
    }

    public function test_reimport_same_file_is_idempotent(): void {
        $this->importer->import($this->source, $this->csv(), $this->mapping);
        $summary = $this->importer->import($this->source, $this->csv(), $this->mapping);

        $this->assertSame(2, $summary['unchanged']);
        $this->assertSame(0, $summary['created']);
        $this->assertSame(0, $summary['price_changed']);
        // Keine zusätzlichen Preis-Snapshots.
        $this->assertSame(1, SupplierCatalogItem::query()->where('external_no', 'A-1')->firstOrFail()->prices()->count());
    }

    public function test_price_change_records_new_snapshot(): void {
        $this->importer->import($this->source, $this->csv('1,50'), $this->mapping);
        $summary = $this->importer->import($this->source, $this->csv('1,80'), $this->mapping);

        $this->assertSame(1, $summary['price_changed']);
        $this->assertSame(1, $summary['updated']);
        $this->assertSame(1, $summary['unchanged']);

        $item = SupplierCatalogItem::query()->where('external_no', 'A-1')->firstOrFail();
        $this->assertSame('1.8000', $item->purchase_price);
        $this->assertSame(2, $item->prices()->count());
    }

    public function test_missing_item_is_discontinued(): void {
        $this->importer->import($this->source, $this->csv(), $this->mapping);

        $reduced = "ArtNr;Bezeichnung;EK;EAN\nA-1;Schraube M4;1,50;4001234567890";
        $summary = $this->importer->import($this->source, $reduced, $this->mapping);

        $this->assertSame(1, $summary['discontinued']);
        $this->assertSame(
            CatalogItemStatus::Discontinued,
            SupplierCatalogItem::query()->where('external_no', 'A-2')->firstOrFail()->status,
        );
    }

    public function test_missing_required_mapping_throws(): void {
        $this->expectException(RuntimeException::class);
        $this->importer->import($this->source, $this->csv(), ['external_no' => 'ArtNr']); // 'name' fehlt
    }

    public function test_header_mismatch_throws(): void {
        $this->expectException(RuntimeException::class);
        $this->importer->import($this->source, $this->csv(), [
            'external_no' => 'Nichtvorhanden', 'name' => 'Bezeichnung',
        ]);
    }
}
