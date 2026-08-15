<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCatalogControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Supplier, SupplierCatalogItem, SupplierCatalogSource, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-091/092: Katalogquellen-Verwaltung und CSV-Import über HTTP.
 */
final class SupplierCatalogControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function source(): SupplierCatalogSource {
        return SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Großhandel', 'format' => 'csv', 'delimiter' => ';',
            'decimal_separator' => ',', 'encoding' => 'UTF-8', 'has_header' => true,
        ]);
    }

    public function test_index_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('supplier-catalogs.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('supplier-catalogs.index'))->assertOk();
    }

    public function test_store_creates_source(): void {
        $this->actingAs($this->admin)->post(route('supplier-catalogs.store'), [
            'supplier' => $this->supplier->sqid,
            'name' => 'Großhandel Süd',
            'format' => 'csv', 'delimiter' => ';', 'decimal_separator' => ',',
            'encoding' => 'UTF-8', 'has_header' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('supplier_catalog_sources', [
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Großhandel Süd',
        ]);
    }

    public function test_import_csv_creates_items(): void {
        $source = $this->source();
        $csv = "ArtNr;Bezeichnung;EK;EAN\nA-1;Schraube;1,50;4001234567890\nA-2;Mutter;0,80;4009876543210";
        $file = UploadedFile::fake()->createWithContent('katalog.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.import', $source), [
                'catalog_csv' => $file,
                'mapping' => ['external_no' => 'ArtNr', 'name' => 'Bezeichnung', 'purchase_price' => 'EK', 'gtin' => 'EAN'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, SupplierCatalogItem::query()->where('supplier_catalog_source_id', $source->id)->count());
        $this->assertSame('1.5000', SupplierCatalogItem::query()->where('external_no', 'A-1')->firstOrFail()->purchase_price?->getAmount());
    }

    public function test_store_creates_xlsx_source_with_sheet_name(): void {
        $this->actingAs($this->admin)->post(route('supplier-catalogs.store'), $this->validPayload([
            'name' => 'Distributor', 'format' => 'xlsx', 'decimal_separator' => '.', 'sheet_name' => 'Preisdaten',
        ]))->assertRedirect();

        $this->assertDatabaseHas('supplier_catalog_sources', [
            'organization_id' => $this->organization->id,
            'name' => 'Distributor', 'format' => 'xlsx', 'sheet_name' => 'Preisdaten',
        ]);
    }

    public function test_import_xlsx_creates_items_and_persists_mapping(): void {
        $source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Distributor', 'format' => 'xlsx', 'delimiter' => ';',
            'decimal_separator' => '.', 'encoding' => 'UTF-8', 'has_header' => true,
            'sheet_name' => 'Preisdaten',
        ]);

        $builder = (new \CommonToolkit\Builders\XLSXDocumentBuilder)->sheet('Preisdaten')
            ->setHeader(['Produkttarif', 'Preis', 'Offer-Key'])
            ->addRow(['Microsoft Entra ID P1', 6.06, 'CFQ7-0002-P1M-1M']);
        $path = tempnam(sys_get_temp_dir(), 'wd-xlsx-test-') . '.xlsx';
        \CommonToolkit\Generators\XLSX\XLSXGenerator::toFile($builder->build(), $path);
        $mapping = ['external_no' => 'Offer-Key', 'name' => 'Produkttarif', 'purchase_price' => 'Preis'];

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.import', $source), [
                'catalog_csv' => new UploadedFile($path, 'preisliste.xlsx', \App\Support\XlsxExport::MIME, null, true),
                'mapping' => $mapping,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('6.0600', SupplierCatalogItem::query()->where('external_no', 'CFQ7-0002-P1M-1M')->firstOrFail()->purchase_price?->getAmount());
        // Mapping wird an der Quelle gemerkt (wie beim CSV-Format).
        $this->assertSame($mapping, $source->fresh()->mapping);
    }

    public function test_import_requires_post_permission(): void {
        $source = $this->source();
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $file = UploadedFile::fake()->createWithContent('katalog.csv', "ArtNr;Bezeichnung\nA-1;Schraube");

        $this->actingAs($stranger)
            ->post(route('supplier-catalogs.import', $source), [
                'catalog_csv' => $file,
                'mapping' => ['external_no' => 'ArtNr', 'name' => 'Bezeichnung'],
            ])
            ->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array {
        return array_merge([
            'supplier' => $this->supplier->sqid,
            'name' => 'Katalog', 'format' => 'csv', 'source_type' => 'upload',
            'delimiter' => ';', 'decimal_separator' => ',', 'encoding' => 'UTF-8', 'has_header' => '1',
        ], $overrides);
    }

    public function test_update_changes_source(): void {
        $source = $this->source();

        $this->actingAs($this->admin)->put(route('supplier-catalogs.update', $source), $this->validPayload([
            'name' => 'Neuer Name', 'format' => 'datanorm', 'source_type' => 'http',
            'remote_url' => 'https://feed.example.com/c.001',
        ]))->assertRedirect()->assertSessionHas('success');

        $fresh = $source->fresh();
        $this->assertSame('Neuer Name', $fresh->name);
        $this->assertSame('http', $fresh->source_type);
        $this->assertSame('https://feed.example.com/c.001', $fresh->remote_url);
    }

    public function test_toggle_active(): void {
        $source = $this->source();

        $this->actingAs($this->admin)->post(route('supplier-catalogs.toggle', $source))->assertRedirect();
        $this->assertFalse($source->fresh()->active);
    }

    public function test_destroy_source(): void {
        $source = $this->source();

        $this->actingAs($this->admin)->delete(route('supplier-catalogs.destroy', $source))->assertRedirect();
        $this->assertDatabaseMissing('supplier_catalog_sources', ['id' => $source->id]);
    }

    public function test_update_requires_permission(): void {
        $source = $this->source();
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->put(route('supplier-catalogs.update', $source), $this->validPayload())->assertForbidden();
    }
}
