<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Article, ArticleVariant, Supplier, Warehouse};
use App\Services\Procurement\{PurchaseOrderExportService, PurchaseOrderService};
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Beschaffung (E4): Export einer Bestellung als elektronische Bestellung
 * (XBestellung / Order-X) über das php-erechnung-toolkit. Käufer = eigene Org,
 * Verkäufer = Lieferant, Positionen mit Lieferanten-SKU und GTIN.
 */
final class PurchaseOrderExportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const ORDER_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Order-2';
    private const CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private Supplier $supplier;
    private Article $article;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        // Käufer-Stammdaten der eigenen Organisation (wie für die E-Rechnung).
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Meine Firma GmbH',
            'street' => 'Firmenweg 1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE111111111',
            'contact_email' => 'einkauf@meinefirma.de',
        ]]]);

        $this->supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'company' => 'Lieferant GmbH',
            'vat_id' => 'DE222222222',
            'address_street' => 'Lieferweg 2',
            'address_zip' => '54321',
            'address_city' => 'Lieferstadt',
            'country' => 'DE',
            'email' => 'vertrieb@lieferant.de',
        ]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Bürostuhl',
            'number' => 'ART-100',
            'gtin' => '4012345678901',
            'base_unit' => 'Stk',
        ]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default',
        ]);
    }

    private function makeOrder(): \App\Models\PurchaseOrder {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $orders->addLine($po, $this->article, '10', [
            'variant' => $this->variant,
            'unit_price' => '120',
            'supplier_sku' => 'SUP-1',
        ]);
        $orders->submit($po);

        return $po->fresh(['supplier', 'lines.article', 'lines.variant', 'organization']);
    }

    private function xpath(string $xml): DOMXPath {
        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ubl', self::ORDER_NS);
        $xpath->registerNamespace('cac', self::CAC_NS);
        $xpath->registerNamespace('cbc', self::CBC_NS);

        return $xpath;
    }

    public function test_service_is_available(): void {
        $this->assertTrue(app(PurchaseOrderExportService::class)->available());
    }

    public function test_exports_xbestellung_with_buyer_seller_and_line(): void {
        $po = $this->makeOrder();
        $xml = app(PurchaseOrderExportService::class)->toXBestellung($po);

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'XBestellung XML should be well-formed');
        $this->assertSame('Order', $dom->documentElement->localName);

        $xpath = $this->xpath($xml);

        $this->assertSame($po->number, $xpath->query('/ubl:Order/cbc:ID')->item(0)->textContent);
        $this->assertSame(
            'urn:fdc:peppol.eu:poacc:trns:order:3',
            $xpath->query('/ubl:Order/cbc:CustomizationID')->item(0)->textContent,
        );

        // Käufer = eigene Organisation
        $this->assertSame(
            'Meine Firma GmbH',
            $xpath->query('/ubl:Order/cac:BuyerCustomerParty/cac:Party/cac:PartyName/cbc:Name')->item(0)->textContent,
        );
        // Verkäufer = Lieferant
        $this->assertSame(
            'Lieferant GmbH',
            $xpath->query('/ubl:Order/cac:SellerSupplierParty/cac:Party/cac:PartyName/cbc:Name')->item(0)->textContent,
        );

        // Position: Menge, Artikel, Lieferanten-SKU, GTIN
        $qty = $xpath->query('/ubl:Order/cac:OrderLine/cac:LineItem/cbc:Quantity')->item(0);
        $this->assertSame('10.00', $qty->textContent);
        $this->assertSame('H87', $qty->getAttribute('unitCode'));
        $this->assertSame(
            'Bürostuhl',
            $xpath->query('/ubl:Order/cac:OrderLine/cac:LineItem/cac:Item/cbc:Name')->item(0)->textContent,
        );
        $this->assertSame(
            'SUP-1',
            $xpath->query('/ubl:Order/cac:OrderLine/cac:LineItem/cac:Item/cac:SellersItemIdentification/cbc:ID')->item(0)->textContent,
        );
        $std = $xpath->query('/ubl:Order/cac:OrderLine/cac:LineItem/cac:Item/cac:StandardItemIdentification/cbc:ID')->item(0);
        $this->assertSame('4012345678901', $std->textContent);
        $this->assertSame('0160', $std->getAttribute('schemeID'));
    }

    public function test_exports_order_x_cii(): void {
        $po = $this->makeOrder();
        $xml = app(PurchaseOrderExportService::class)->toOrderX($po);

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'Order-X XML should be well-formed');
        $this->assertSame('SCRDMCCBDACIOMessageStructure', $dom->documentElement->localName);
        $this->assertStringContainsString('urn:order-x.eu:1p0:comfort', $xml);
        $this->assertStringContainsString('Lieferant GmbH', $xml);
        $this->assertStringContainsString('Bürostuhl', $xml);
    }

    public function test_exports_opentrans_order(): void {
        $po = $this->makeOrder();
        $xml = app(PurchaseOrderExportService::class)->toOpenTrans($po);

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'openTRANS ORDER XML should be well-formed');
        $this->assertSame('ORDER', $dom->documentElement->localName);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ot', 'http://www.opentrans.org/XMLSchema/2.1');
        $xpath->registerNamespace('bmecat', 'http://www.bmecat.org/bmecat/2005');
        $info = '/ot:ORDER/ot:ORDER_HEADER/ot:ORDER_INFO';

        $this->assertSame($po->number, $xpath->query("{$info}/ot:ORDER_ID")->item(0)->textContent);
        $this->assertSame(
            'Meine Firma GmbH',
            $xpath->query("{$info}/ot:PARTIES/ot:PARTY[ot:PARTY_ROLE='buyer']/ot:ADDRESS/bmecat:NAME")->item(0)->textContent,
        );
        $this->assertSame(
            'Lieferant GmbH',
            $xpath->query("{$info}/ot:PARTIES/ot:PARTY[ot:PARTY_ROLE='supplier']/ot:ADDRESS/bmecat:NAME")->item(0)->textContent,
        );

        $item = '/ot:ORDER/ot:ORDER_ITEM_LIST/ot:ORDER_ITEM[1]';
        $this->assertSame('10', $xpath->query("{$item}/ot:QUANTITY")->item(0)->textContent);
        $this->assertSame('Bürostuhl', $xpath->query("{$item}/ot:PRODUCT_ID/bmecat:DESCRIPTION_SHORT")->item(0)->textContent);
        $this->assertSame('SUP-1', $xpath->query("{$item}/ot:PRODUCT_ID/bmecat:SUPPLIER_PID")->item(0)->textContent);
        $this->assertSame('4012345678901', $xpath->query("{$item}/ot:PRODUCT_ID/bmecat:INTERNATIONAL_PID")->item(0)->textContent);
        $this->assertSame('120.00', $xpath->query("{$item}/ot:PRODUCT_PRICE_FIX/bmecat:PRICE_AMOUNT")->item(0)->textContent);
    }

    public function test_exports_ugl_order(): void {
        $po = $this->makeOrder();
        $ugl = app(PurchaseOrderExportService::class)->toUgl($po);

        $records = array_values(array_filter(preg_split('/\r\n/', $ugl) ?: [], fn (string $r) => $r !== ''));

        // KOP + ADR (Lager als Lieferadresse) + 1 POA + END, jeder Satz 350 Bytes.
        $this->assertCount(4, $records);
        $this->assertSame('KOP', substr($records[0], 0, 3));
        $this->assertSame('ADR', substr($records[1], 0, 3));
        $this->assertSame('POA', substr($records[2], 0, 3));
        $this->assertSame('END', substr($records[3], 0, 3));
        foreach ($records as $record) {
            $this->assertSame(350, strlen($record));
        }

        // KOP: Anfrageart BE, Bestellnummer, Währung, Version.
        $this->assertSame('BE', substr($records[0], 23, 2));
        $this->assertSame($po->number, rtrim(substr($records[0], 25, 15)));
        $this->assertSame('EUR', substr($records[0], 113, 3));
        $this->assertSame('05.00', substr($records[0], 116, 5));

        // POA (records[2]): Lieferanten-SKU, Menge 10 (11,3), Netto-Positionswert 1200,00 (11,2).
        $this->assertSame('SUP-1', rtrim(substr($records[2], 23, 15)));
        $this->assertSame('00000010000', substr($records[2], 38, 11));
        $this->assertSame('00000012000', substr($records[2], 130 - 1, 11)); // Brutto je PE 120,00
        $this->assertSame('00000120000', substr($records[2], 142 - 1, 11)); // Netto-Positionswert 1200,00 (10 × 120)
    }

    public function test_ugl_emits_pot_and_poz_from_note_and_freight(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $orders->addLine($po, $this->article, '10', [
            'variant' => $this->variant, 'unit_price' => '120', 'supplier_sku' => 'SUP-1',
            'note' => 'Bitte vormontiert liefern',
        ]);
        $po->update(['freight_cost' => '25']);
        $orders->submit($po);
        $po = $po->fresh(['supplier', 'lines.article', 'lines.variant', 'organization', 'warehouse']);

        $ugl = app(PurchaseOrderExportService::class)->toUgl($po);
        $records = array_values(array_filter(preg_split('/\r\n/', $ugl) ?: [], fn (string $r) => $r !== ''));
        $field = fn (string $rec, int $from, int $to): string => rtrim((string) iconv('ISO-8859-1', 'UTF-8', substr($rec, $from - 1, $to - $from + 1)));

        $pot = collect($records)->first(fn (string $r) => str_starts_with($r, 'POT'));
        $poz = collect($records)->first(fn (string $r) => str_starts_with($r, 'POZ'));
        $this->assertNotNull($pot, 'Positionsnotiz muss einen POT-Satz erzeugen');
        $this->assertNotNull($poz, 'Frachtkosten müssen einen POZ-Satz erzeugen');

        // POT: Positionstext + Textanfang-Kennzeichen.
        $this->assertSame('Bitte vormontiert liefern', $field($pot, 24, 63));
        $this->assertSame('T', $field($pot, 162, 162));

        // POZ: Fracht (Typ 07), Wert 25,00.
        $this->assertSame('07', $field($poz, 24, 25));
        $this->assertSame('00000002500', substr($poz, 116, 11));
    }

    public function test_exports_ugl_with_warehouse_delivery_address(): void {
        $this->warehouse->update(['name' => 'Zentrallager Nord', 'location_note' => 'Rampe 2']);
        $po = $this->makeOrder();
        $ugl = app(PurchaseOrderExportService::class)->toUgl($po);

        $records = array_values(array_filter(preg_split('/\r\n/', $ugl) ?: [], fn (string $r) => $r !== ''));
        $field = fn (string $rec, int $from, int $to): string => rtrim((string) iconv('ISO-8859-1', 'UTF-8', substr($rec, $from - 1, $to - $from + 1)));

        // Reihenfolge KOP, ADR, POA, END.
        $this->assertSame('KOP', substr($records[0], 0, 3));
        $this->assertSame('ADR', substr($records[1], 0, 3));
        $this->assertSame('POA', substr($records[2], 0, 3));

        $adr = $records[1];
        $this->assertSame('Zentrallager Nord', $field($adr, 4, 33));  // Lager als Lieferadress-Name
        $this->assertSame('Firmenweg 1', $field($adr, 94, 123));      // Org-Straße
        $this->assertSame('10115', $field($adr, 127, 132));           // PLZ
        $this->assertSame('Berlin', $field($adr, 133, 162));          // Ort
        $this->assertSame('Rampe 2', $field($adr, 295, 344));         // Lieferhinweis
    }
}
