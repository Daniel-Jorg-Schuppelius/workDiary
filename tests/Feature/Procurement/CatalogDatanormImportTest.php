<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogDatanormImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Supplier, SupplierCatalogItem, SupplierCatalogSource, User};
use App\Services\Procurement\DatanormImportService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050 (strukturierte Katalogformate): DATANORM-Import über die geteilte
 * Upsert-Pipeline — Artikelhauptsätze (A), Preisberechnung je Preiseinheit,
 * Idempotenz und Ignorieren von Nicht-Artikelsätzen.
 */
final class CatalogDatanormImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private SupplierCatalogSource $source;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'DATANORM', 'format' => 'datanorm', 'delimiter' => ';',
            'decimal_separator' => ',', 'encoding' => 'UTF-8', 'has_header' => true,
        ]);
    }

    private function datanorm(): string {
        return implode("\n", [
            'V;180101;EUR;DATANORM 5;Lieferant Muster;',
            'A;N;100123;0;Kupferrohr 15x1;Hartgeloetet;0;1;m;850;01;100;',
            'A;N;100124;0;T-Stueck 15mm;;0;100;Stk;12500;01;100;',
            'B;N;100123;MATCH;;;;4001234567890',
        ]);
    }

    public function test_datanorm_import_creates_items_with_unit_price(): void {
        $summary = app(DatanormImportService::class)->import($this->source, $this->datanorm());

        $this->assertSame(2, $summary['created']);
        $this->assertSame(2, $summary['rows']);

        $rohr = SupplierCatalogItem::query()->where('external_no', '100123')->firstOrFail();
        $this->assertSame('8.5000', $rohr->purchase_price);       // 850 Cent / 1
        $this->assertStringContainsString('Kupferrohr', (string) $rohr->name);
        $this->assertSame('100', $rohr->category);

        // Preis 12500 Cent bei Preiseinheit 100 → 1,25 je Stück.
        $this->assertSame('1.2500', SupplierCatalogItem::query()->where('external_no', '100124')->firstOrFail()->purchase_price);
    }

    public function test_datanorm_ignores_non_article_records(): void {
        app(DatanormImportService::class)->import($this->source, $this->datanorm());

        // Nur die beiden A-Sätze — Vorlauf (V) und Zusatz (B) erzeugen nichts.
        $this->assertSame(2, SupplierCatalogItem::query()->where('supplier_catalog_source_id', $this->source->id)->count());
    }

    public function test_datanorm_reimport_is_idempotent(): void {
        app(DatanormImportService::class)->import($this->source, $this->datanorm());
        $summary = app(DatanormImportService::class)->import($this->source, $this->datanorm());

        $this->assertSame(0, $summary['created']);
        $this->assertSame(2, $summary['unchanged']);
    }

    public function test_datanorm_upload_route(): void {
        $file = UploadedFile::fake()->createWithContent('DATANORM.001', $this->datanorm());

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.import', $this->source), ['catalog_csv' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, SupplierCatalogItem::query()->where('supplier_catalog_source_id', $this->source->id)->count());
    }
}
