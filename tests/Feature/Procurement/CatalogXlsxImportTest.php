<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogXlsxImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{SupplierCatalogImport, SupplierCatalogItem, SupplierCatalogSource};
use App\Services\Procurement\{CatalogImportDispatcher, CatalogXlsxImportService};
use CommonToolkit\Builders\XLSXDocumentBuilder;
use CommonToolkit\Generators\XLSX\XLSXGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-541: XLSX-Katalogimport — Tabellenblatt-Auswahl,
 * Zell-Normalisierung und gemeinsame Strecke mit dem CSV-Import
 * (Hash-Idempotenz, Preis-Snapshots).
 */
final class CatalogXlsxImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CatalogXlsxImportService $importer;
    private SupplierCatalogSource $source;

    /** @var array<string, string> */
    private array $mapping = [
        'external_no' => 'Offer-Key',
        'name' => 'Produkttarif',
        'purchase_price' => 'Preis pro Zahlungsintervall',
    ];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->importer = app(CatalogXlsxImportService::class);

        $supplier = \App\Models\Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $supplier->id,
            'name' => 'Distributor-Preisliste',
            'format' => 'xlsx', 'delimiter' => ';', 'decimal_separator' => '.',
            'has_header' => true, 'encoding' => 'UTF-8', 'sheet_name' => 'Preisdaten',
        ]);
    }

    /** XLSX-Fixture wie das Distributor-Preisblatt: Deckblatt + Datenblatt. */
    private function xlsx(float $priceA = 6.0, bool $withCover = true): string {
        $builder = new XLSXDocumentBuilder;
        if ($withCover) {
            $builder->sheet('Deckblatt')
                ->setHeader(['Preisliste für Reseller', ''])
                ->addRow(['Reseller', '95229']);
        }
        $builder->sheet('Preisdaten')
            ->setHeader(['Produkttarif', 'Preis pro Zahlungsintervall', 'Offer-Key'])
            ->addRow(['Microsoft Entra ID Governance', $priceA, 'CFQ7-0001-P1M-1M'])
            ->addRow(['Microsoft Entra ID P1', 6.06, 'CFQ7-0002-P1M-1M']);

        $path = tempnam(sys_get_temp_dir(), 'wd-xlsx-test-') . '.xlsx';
        XLSXGenerator::toFile($builder->build(), $path);
        $content = (string) file_get_contents($path);
        @unlink($path);

        return $content;
    }

    public function test_import_xlsx_creates_items_with_prices(): void {
        $summary = $this->importer->import($this->source, $this->xlsx(), $this->mapping);

        $this->assertSame(['rows' => 2, 'created' => 2, 'updated' => 0, 'unchanged' => 0, 'price_changed' => 0, 'discontinued' => 0], $summary);

        $item = SupplierCatalogItem::query()->where('external_no', 'CFQ7-0001-P1M-1M')->firstOrFail();
        $this->assertSame('Microsoft Entra ID Governance', $item->name);
        $this->assertSame('6.0000', $item->purchase_price?->getAmount());
        $this->assertSame('6.0600', SupplierCatalogItem::query()->where('external_no', 'CFQ7-0002-P1M-1M')->firstOrFail()->purchase_price?->getAmount());
        $this->assertSame(1, $item->prices()->count());
        $this->assertNotNull($this->source->fresh()->last_file_hash);
    }

    public function test_reimport_same_file_is_idempotent(): void {
        $this->importer->import($this->source, $this->xlsx(), $this->mapping);
        $summary = $this->importer->import($this->source, $this->xlsx(), $this->mapping);

        $this->assertSame(2, $summary['unchanged']);
        $this->assertSame(0, $summary['created']);
        $this->assertSame(0, $summary['price_changed']);
    }

    public function test_price_change_records_new_snapshot(): void {
        $this->importer->import($this->source, $this->xlsx(6.0), $this->mapping);
        $summary = $this->importer->import($this->source, $this->xlsx(5.24), $this->mapping);

        $this->assertSame(1, $summary['price_changed']);
        $item = SupplierCatalogItem::query()->where('external_no', 'CFQ7-0001-P1M-1M')->firstOrFail();
        $this->assertSame('5.2400', $item->purchase_price?->getAmount());
        $this->assertSame(2, $item->prices()->count());
    }

    public function test_first_sheet_is_used_without_sheet_name(): void {
        $this->source->forceFill(['sheet_name' => null])->save();

        $summary = $this->importer->import($this->source->fresh(), $this->xlsx(withCover: false), $this->mapping);

        $this->assertSame(2, $summary['created']);
    }

    public function test_cover_sheet_without_sheet_name_fails_preflight(): void {
        $this->source->forceFill(['sheet_name' => null])->save();

        // Erstes Blatt ist das Deckblatt — der Header-Preflight meldet die fehlende Spalte.
        $this->expectException(RuntimeException::class);
        $this->importer->import($this->source->fresh(), $this->xlsx(), $this->mapping);
    }

    public function test_unknown_sheet_name_throws(): void {
        $this->source->forceFill(['sheet_name' => 'Preise'])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Preise');
        $this->importer->import($this->source->fresh(), $this->xlsx(), $this->mapping);
    }

    public function test_invalid_content_throws(): void {
        $this->expectException(RuntimeException::class);
        $this->importer->import($this->source, 'kein-xlsx-inhalt', $this->mapping);
    }

    public function test_dispatcher_routes_xlsx_format(): void {
        $summary = app(CatalogImportDispatcher::class)->run($this->source, $this->xlsx(), $this->mapping, SupplierCatalogImport::TRIGGER_MANUAL);

        $this->assertSame(2, $summary['created']);
        $this->assertSame(1, SupplierCatalogImport::query()->where('supplier_catalog_source_id', $this->source->id)->count());
    }
}
