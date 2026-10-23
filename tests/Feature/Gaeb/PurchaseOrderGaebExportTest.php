<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderGaebExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\Warehouse;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Gaeb\GaebOrderExportService;
use App\Services\Procurement\PurchaseOrderService;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Entities\Gaeb\{GaebBoq, GaebOrder, GaebOrderItem};
use ERechnungToolkit\Enums\GaebPhase;
use ERechnungToolkit\Generators\GaebDaXmlGenerator;
use ERechnungToolkit\Validators\GaebSchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Bestellungen als GAEB-Handelsdatei (X96): der dritte Bestellweg neben
 * OCI-Punchout und openTRANS — und der, den Baustoffhändler tatsächlich fahren.
 */
final class PurchaseOrderGaebExportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private Article $article;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        // Die eigene Anschrift stammt aus den E-Rechnungs-Stammdaten; ohne sie
        // weist die Handelsdatei den Besteller nicht aus.
        $this->organization->update(['settings' => array_replace((array) $this->organization->settings, [
            'einvoice' => ['seller_name' => 'Bau GmbH', 'street' => 'Schillerplatz 1', 'zip' => '99423', 'city' => 'Weimar'],
        ])]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Baustoff Meier',
            'address_street' => 'Coudraystr. 4',
            'address_zip' => '99423',
            'address_city' => 'Weimar',
            'tax_number' => '205/5711/0041',
        ]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'purchasable' => true,
            'base_unit' => 'Stk',
        ]);
        ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default',
        ]);
    }

    private function orderWithLine(): \App\Models\Procurement\PurchaseOrder {
        $order = app(PurchaseOrderService::class)->createDraft($this->organization, $this->supplier, $this->warehouse);

        $this->actingAs($this->admin)->post(route('purchase-orders.lines.add', $order), [
            'article' => $this->article->sqid,
            'qty' => '12',
            'unit_price' => '98.50',
        ])->assertRedirect();

        return $order->fresh(['lines', 'supplier', 'organization']);
    }

    /** Die erzeugte Datei ist schemavalide und trägt die Handelsangaben. */
    public function test_export_produces_a_valid_trade_document(): void {
        $result = app(GaebOrderExportService::class)->export($this->orderWithLine());

        $this->assertSame([], (new GaebSchemaValidator)->validate($result['content']));
        $this->assertStringContainsString('<Order>', $result['content']);
        $this->assertStringContainsString('<DP>96</DP>', $result['content']);
        $this->assertStringContainsString('<TaxNo>205/5711/0041</TaxNo>', $result['content']);
        $this->assertStringEndsWith('.x96', $result['filename']);
        // Bestellnummer als PartNo; die Bestätigungsnummer vergibt der Händler.
        $this->assertStringContainsString('<PartNo>', $result['content']);
        $this->assertStringNotContainsString('<OrderConfNo>', $result['content']);
    }

    /**
     * Der Lieferant führt keine Handelsregisternummer — Einzelunternehmer haben
     * ohnehin keine. Das Element steht trotzdem da, nur leer.
     */
    public function test_missing_register_number_stays_valid(): void {
        $result = app(GaebOrderExportService::class)->export($this->orderWithLine());

        $this->assertStringContainsString('<RegNo/>', $result['content']);
        $this->assertSame([], (new GaebSchemaValidator)->validate($result['content']));
    }

    /**
     * Wertobjekt-Audit 2026-09-19: (string) auf unit_price (Money) ergab
     * „98.5000 EUR", Money::of() machte daraus still 0 — jede Bestellung ging
     * mit Nullpreisen raus.
     */
    public function test_order_carries_the_real_unit_price_and_quantity(): void {
        $result = app(GaebOrderExportService::class)->export($this->orderWithLine());

        $this->assertMatchesRegularExpression('/>98\.50?</', $result['content']);
        $this->assertStringContainsString('<Qty>12</Qty>', $result['content']);
        $this->assertDoesNotMatchRegularExpression('/<(UP|NetPrice)>0(\.0+)?</', $result['content']);
    }

    /** Die Preisanfrage fragt ohne Preis; erst das Angebot des Händlers nennt einen. */
    public function test_price_inquiry_carries_no_prices(): void {
        $result = app(GaebOrderExportService::class)->export($this->orderWithLine(), GaebPhase::PriceInquiry);

        $this->assertSame([], (new GaebSchemaValidator)->validate($result['content']));
        $this->assertStringNotContainsString('<NetPrice>', $result['content']);
    }

    /**
     * Fehlt die Artikelnummer des Lieferanten, findet dessen Warenwirtschaft
     * die Ware nicht — das wird benannt, nicht verschwiegen.
     */
    public function test_missing_supplier_article_number_is_reported(): void {
        $order = $this->orderWithLine();
        $order->lines->first()?->update(['supplier_sku' => null]);

        $result = app(GaebOrderExportService::class)->export($order->fresh(['lines', 'supplier', 'organization']));

        $this->assertNotSame([], $result['losses']);
        $this->assertStringContainsString('Artikelnummer', implode(' ', $result['losses']));
    }

    /** Antwort des Händlers als GAEB-Handelsbeleg (MVP-1300). */
    private function reply(GaebPhase $phase, GaebOrder $order): UploadedFile {
        // Echte Uploads erkennt der Server am Inhalt (text/xml); die Fake-Datei nur an der Endung.
        return UploadedFile::fake()->createWithContent('antwort.x' . $phase->value, (new GaebDaXmlGenerator)->generate(new GaebBoq, $phase, 'EUR', '2026-10-01', order: $order))->mimeType('text/xml');
    }

    public function test_order_confirmation_x97_is_read_like_an_order_response(): void {
        $order = $this->orderWithLine();
        $order->lines->first()?->update(['supplier_sku' => 'ZE-100']);
        $confirmation = new GaebOrder(
            deliveryDate: '2026-10-08',
            items: [new GaebOrderItem('ZE-100', 'Zement', '10', 'Stk', Money::of('99.00', CurrencyCode::Euro), supplierArticleNo: 'ZE-100')],
            orderConfirmationNo: 'AB-77',
            orderNo: (string) $order->number,
        );

        $this->actingAs($this->admin)->post(route('purchase-orders.confirmation.import', $order), ['confirmation_xml' => $this->reply(GaebPhase::OrderConfirmation, $confirmation)])
            ->assertSessionHas('success')->assertSessionHas('warning');

        $order->refresh();
        $this->assertSame('AB-77', $order->supplier_order_ref);
        $line = $order->lines()->sole();
        $this->assertSame('10.0000', (string) $line->confirmed_qty);
        $this->assertSame('99.0000', (string) $line->confirmed_unit_price);
        $this->assertSame('2026-10-08', $line->confirmed_delivery_on?->toDateString());
    }

    public function test_price_offer_x94_sets_the_prices_of_the_draft(): void {
        $order = $this->orderWithLine();
        $order->lines->first()?->update(['supplier_sku' => 'ZE-100']);
        $offer = new GaebOrder(
            deliveryDate: '2026-10-08',
            items: [new GaebOrderItem('ZE-100', 'Zement', '12', 'Stk', Money::of('87.50', CurrencyCode::Euro), supplierArticleNo: 'ZE-100')],
            inquiryNo: (string) $order->number,
            offerNo: 'ANG-5',
        );

        $this->actingAs($this->admin)->post(route('purchase-orders.confirmation.import', $order), ['confirmation_xml' => $this->reply(GaebPhase::PriceOffer, $offer)])
            ->assertSessionHas('success', __('procurement.offer.applied', ['count' => 1]));
        $this->assertTrue(Money::of('87.50', CurrencyCode::Euro)->compareTo($order->lines()->sole()->unit_price ?? Money::zero(CurrencyCode::Euro)) === 0);

        // Die eigene Bestellung (X96) ist keine Antwort des Händlers.
        $this->actingAs($this->admin)->post(route('purchase-orders.confirmation.import', $order), ['confirmation_xml' => UploadedFile::fake()->createWithContent('eigen.x96', app(GaebOrderExportService::class)->export($order->fresh(['lines', 'supplier', 'organization']))['content'])->mimeType('text/xml')])
            ->assertSessionHas('error', __('procurement.confirmation.error.gaeb_phase', ['phase' => 'X96']));
    }

    /** Der Entwurf bietet die Preisanfrage (X93) und den Upload des Angebots (X94) an (MVP-1300). */
    public function test_draft_offers_price_inquiry_and_offer_upload(): void {
        $order = $this->orderWithLine();

        $this->actingAs($this->admin)->get(route('purchase-orders.show', $order))->assertOk()
            ->assertSee(route('purchase-orders.order-xml', ['purchaseOrder' => $order, 'format' => 'x93']), false)
            ->assertSee('id="offer_xml"', false)
            ->assertSeeText('Preisangebot des Händlers einlesen (GAEB X94)');

        $inquiry = $this->actingAs($this->admin)->get(route('purchase-orders.order-xml', ['purchaseOrder' => $order, 'format' => 'x93']))->assertOk();
        $this->assertStringContainsString('<DP>93</DP>', (string) $inquiry->getContent());
        $this->assertStringEndsWith('.x93"', (string) $inquiry->headers->get('Content-Disposition'));
    }
}
