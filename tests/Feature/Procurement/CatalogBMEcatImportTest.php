<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogBMEcatImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Supplier, SupplierCatalogItem, SupplierCatalogSource, User};
use App\Services\Procurement\BMEcatImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050 (strukturierte Katalogformate): BMEcat-Import (XML 1.2 und 2005)
 * über die geteilte Upsert-Pipeline.
 */
final class CatalogBMEcatImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private SupplierCatalogSource $source;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'BMEcat', 'format' => 'bmecat', 'delimiter' => ';',
            'decimal_separator' => '.', 'encoding' => 'UTF-8', 'has_header' => true,
        ]);
    }

    private function bmecat12(): string {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<BMECAT version="1.2">
  <T_NEW_CATALOG>
    <ARTICLE>
      <SUPPLIER_AID>BM-1</SUPPLIER_AID>
      <ARTICLE_DETAILS>
        <DESCRIPTION_SHORT>Kabel NYM-J 3x1,5</DESCRIPTION_SHORT>
        <EAN>4011111111111</EAN>
        <MANUFACTURER_AID>MFR-9</MANUFACTURER_AID>
      </ARTICLE_DETAILS>
      <ARTICLE_PRICE_DETAILS>
        <ARTICLE_PRICE price_type="net_list">
          <PRICE_AMOUNT>1.25</PRICE_AMOUNT>
          <PRICE_CURRENCY>EUR</PRICE_CURRENCY>
        </ARTICLE_PRICE>
      </ARTICLE_PRICE_DETAILS>
    </ARTICLE>
    <ARTICLE>
      <SUPPLIER_AID>BM-2</SUPPLIER_AID>
      <ARTICLE_DETAILS><DESCRIPTION_SHORT>Schalter</DESCRIPTION_SHORT></ARTICLE_DETAILS>
      <ARTICLE_PRICE_DETAILS><ARTICLE_PRICE><PRICE_AMOUNT>3.40</PRICE_AMOUNT></ARTICLE_PRICE></ARTICLE_PRICE_DETAILS>
    </ARTICLE>
  </T_NEW_CATALOG>
</BMECAT>
XML;
    }

    public function test_bmecat_import_creates_items(): void {
        $summary = app(BMEcatImportService::class)->import($this->source, $this->bmecat12());

        $this->assertSame(2, $summary['created']);
        $item = SupplierCatalogItem::query()->where('external_no', 'BM-1')->firstOrFail();
        $this->assertSame('1.2500', $item->purchase_price);
        $this->assertSame('4011111111111', $item->gtin);
        $this->assertSame('MFR-9', $item->manufacturer_no);
        $this->assertStringContainsString('Kabel', (string) $item->name);
    }

    public function test_bmecat_2005_product_elements(): void {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<BMECAT version="2005">
  <T_NEW_CATALOG>
    <PRODUCT>
      <SUPPLIER_PID>P-1</SUPPLIER_PID>
      <PRODUCT_DETAILS><DESCRIPTION_SHORT>Rohr</DESCRIPTION_SHORT></PRODUCT_DETAILS>
      <PRODUCT_PRICE_DETAILS><PRODUCT_PRICE><PRICE_AMOUNT>9.90</PRICE_AMOUNT></PRODUCT_PRICE></PRODUCT_PRICE_DETAILS>
    </PRODUCT>
  </T_NEW_CATALOG>
</BMECAT>
XML;
        $summary = app(BMEcatImportService::class)->import($this->source, $xml);

        $this->assertSame(1, $summary['created']);
        $this->assertSame('9.9000', SupplierCatalogItem::query()->where('external_no', 'P-1')->firstOrFail()->purchase_price);
    }

    public function test_invalid_xml_throws(): void {
        $this->expectException(RuntimeException::class);
        app(BMEcatImportService::class)->import($this->source, 'kein xml <<<');
    }

    public function test_bmecat_extracts_classification_and_media(): void {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<BMECAT version="1.2">
  <T_NEW_CATALOG>
    <ARTICLE>
      <SUPPLIER_AID>CM-1</SUPPLIER_AID>
      <ARTICLE_DETAILS><DESCRIPTION_SHORT>Sensor</DESCRIPTION_SHORT></ARTICLE_DETAILS>
      <ARTICLE_FEATURES>
        <REFERENCE_FEATURE_SYSTEM_NAME>ECLASS-9.0</REFERENCE_FEATURE_SYSTEM_NAME>
        <REFERENCE_FEATURE_GROUP_ID>27-27-90-01</REFERENCE_FEATURE_GROUP_ID>
      </ARTICLE_FEATURES>
      <MIME_INFO>
        <MIME><MIME_TYPE>image/jpeg</MIME_TYPE><MIME_SOURCE>https://x/img.jpg</MIME_SOURCE><MIME_PURPOSE>normal</MIME_PURPOSE></MIME>
        <MIME><MIME_TYPE>application/pdf</MIME_TYPE><MIME_SOURCE>https://x/ds.pdf</MIME_SOURCE><MIME_PURPOSE>data_sheet</MIME_PURPOSE></MIME>
      </MIME_INFO>
      <ARTICLE_PRICE_DETAILS><ARTICLE_PRICE><PRICE_AMOUNT>5.00</PRICE_AMOUNT></ARTICLE_PRICE></ARTICLE_PRICE_DETAILS>
    </ARTICLE>
  </T_NEW_CATALOG>
</BMECAT>
XML;
        app(BMEcatImportService::class)->import($this->source, $xml);

        $item = SupplierCatalogItem::query()->where('external_no', 'CM-1')->firstOrFail();
        $this->assertSame('ECLASS-9.0', $item->classification_system);
        $this->assertSame('27-27-90-01', $item->classification_code);
        $this->assertSame('https://x/img.jpg', $item->image_url);
        $this->assertSame('https://x/ds.pdf', $item->datasheet_url);
    }

    private function bmecatWithTiers(string $tier10 = '1.80'): string {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<BMECAT version="1.2">
  <T_NEW_CATALOG>
    <ARTICLE>
      <SUPPLIER_AID>TR-1</SUPPLIER_AID>
      <ARTICLE_DETAILS><DESCRIPTION_SHORT>Kabel</DESCRIPTION_SHORT></ARTICLE_DETAILS>
      <ARTICLE_PRICE_DETAILS>
        <ARTICLE_PRICE><PRICE_AMOUNT>2.00</PRICE_AMOUNT><LOWER_BOUND>1</LOWER_BOUND></ARTICLE_PRICE>
        <ARTICLE_PRICE><PRICE_AMOUNT>{$tier10}</PRICE_AMOUNT><LOWER_BOUND>10</LOWER_BOUND></ARTICLE_PRICE>
        <ARTICLE_PRICE><PRICE_AMOUNT>1.50</PRICE_AMOUNT><LOWER_BOUND>100</LOWER_BOUND></ARTICLE_PRICE>
      </ARTICLE_PRICE_DETAILS>
    </ARTICLE>
  </T_NEW_CATALOG>
</BMECAT>
XML;
    }

    public function test_bmecat_extracts_price_tiers(): void {
        app(BMEcatImportService::class)->import($this->source, $this->bmecatWithTiers());

        $item = SupplierCatalogItem::query()->where('external_no', 'TR-1')->firstOrFail();
        $this->assertSame('2.0000', $item->purchase_price);       // Basispreis (Bound 1)
        $this->assertSame(2, $item->priceTiers()->count());       // Bounds 10 + 100
        $this->assertSame('1.8000', $item->priceTiers()->where('min_qty', '10.0000')->firstOrFail()->unit_price);
    }

    public function test_bmecat_tier_change_resyncs_without_duplicates(): void {
        $svc = app(BMEcatImportService::class);
        $svc->import($this->source, $this->bmecatWithTiers('1.80'));
        $svc->import($this->source, $this->bmecatWithTiers('1.70')); // Staffel 10 geändert

        $item = SupplierCatalogItem::query()->where('external_no', 'TR-1')->firstOrFail();
        $this->assertSame(2, $item->priceTiers()->count()); // keine Duplikate
        $this->assertSame('1.7000', $item->priceTiers()->where('min_qty', '10.0000')->firstOrFail()->unit_price);
    }

    public function test_bmecat_upload_route(): void {
        $file = UploadedFile::fake()->createWithContent('catalog.xml', $this->bmecat12());

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.import', $this->source), ['catalog_csv' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, SupplierCatalogItem::query()->where('supplier_catalog_source_id', $this->source->id)->count());
    }
}
