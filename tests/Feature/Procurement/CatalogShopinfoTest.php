<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogShopinfoTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Supplier, SupplierCatalogSource, User};
use App\Services\Procurement\ShopinfoParser;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-092: shopinfo.xml-Discovery — Mapping-Vorschläge und
 * Katalog-Eckdaten; die Download-URL wird nur angezeigt, nicht abgerufen.
 */
final class CatalogShopinfoTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function shopinfo(): string {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SHOP>
  <CATALOG>
    <URL>https://shop.example.com/catalog.csv</URL>
    <CHARSET>ISO-8859-1</CHARSET>
    <DELIMITER>;</DELIMITER>
    <COLUMNS>
      <COLUMN number="0" type="ARTICLE_NUMBER" name="ArtNr"/>
      <COLUMN number="1" type="DESCRIPTION" name="Bezeichnung"/>
      <COLUMN number="2" type="PRICE" name="EK"/>
      <COLUMN number="3" type="EAN" name="EAN"/>
    </COLUMNS>
  </CATALOG>
</SHOP>
XML;
    }

    public function test_parser_extracts_mapping_and_metadata(): void {
        $result = app(ShopinfoParser::class)->parse($this->shopinfo());

        $this->assertSame('https://shop.example.com/catalog.csv', $result['catalog_url']);
        $this->assertSame('ISO-8859-1', $result['charset']);
        $this->assertSame(';', $result['delimiter']);
        $this->assertSame([
            'external_no' => 'ArtNr',
            'name' => 'Bezeichnung',
            'purchase_price' => 'EK',
            'gtin' => 'EAN',
        ], $result['mapping']);
    }

    public function test_parser_rejects_invalid_xml(): void {
        $this->expectException(RuntimeException::class);
        app(ShopinfoParser::class)->parse('not xml <<');
    }

    public function test_discover_route_updates_source_and_prefills_mapping(): void {
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $supplier->id,
            'name' => 'Shop', 'format' => 'csv', 'delimiter' => ',',
            'decimal_separator' => ',', 'encoding' => 'UTF-8', 'has_header' => true,
        ]);

        $file = UploadedFile::fake()->createWithContent('shopinfo.xml', $this->shopinfo());

        $this->actingAs($admin)
            ->post(route('supplier-catalogs.shopinfo', $source), ['shopinfo' => $file])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('shopinfo_mapping')
            ->assertSessionHas('shopinfo_url', 'https://shop.example.com/catalog.csv');

        $fresh = $source->fresh();
        $this->assertSame(';', $fresh->delimiter);
        $this->assertSame('ISO-8859-1', $fresh->encoding);
    }
}
