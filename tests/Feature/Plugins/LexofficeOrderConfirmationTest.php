<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeOrderConfirmationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\Article\{Article, ArticleVariant};
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\{LexofficeOrderConfirmationService, LexofficeQuotationService};
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Lexoffice-Auftragsbestätigung aus einem kundenbezogenen Fertigungsauftrag.
 */
final class LexofficeOrderConfirmationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    /** Lexware vergibt UUIDs; isValid() der Belegadresse prüft darauf. */
    private const CONTACT_ID = 'e5b7a6c4-1f2d-4c3b-9a8e-7d6c5b4a3f21';

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret('lexoffice', ['base_url' => 'https://api.lexware.io/v1', 'default_vat_rate' => 19.0, 'default_currency' => 'EUR', 'api_key' => 'test-key']);

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id, 'email' => 'kunde@example.com',
        ]);
    }

    private function makeOrder(): ManufacturingOrder {
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id, 'name' => 'Spezialteil', 'base_unit' => 'Stk',
        ]);
        $variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'is_default' => true, 'option_signature' => 'default', 'sale_price' => '200.0000',
        ]);

        return ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'article_variant_id' => $variant->id,
            'customer_id' => $this->customer->id,
            'target_qty' => '5',
            'unit' => 'Stk',
        ]);
    }

    private function seedContactReference(): void {
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(),
            'referenceable_id' => $this->customer->getKey(),
            'external_id' => self::CONTACT_ID, 'synced_at' => now(),
        ]);
    }

    /**
     * Die AB des Fertigungsauftrags trägt keine `shippingConditions` — laut
     * Lexware-Referenz Pflicht beim Anlegen. isValid() der SDK-Entität bricht
     * vor dem Senden ab; der Controller zeigt die RuntimeException.
     */
    /** Lexware verlangt Versandbedingungen an der AB; der Fertigungsauftrag hat keine, also „none“. */
    public function test_order_confirmation_is_sent_with_shipping_type_none(): void {
        $this->seedContactReference();
        $order = $this->makeOrder();
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/order-confirmations*' => FakePluginHttp::response(['id' => 'lex-oc-1'], 201),
        ]);

        $reference = app(LexofficeOrderConfirmationService::class)->push($order);

        $this->assertSame('lex-oc-1', $reference->external_id);
        $fake->assertSentCount(1);
        $request = $fake->recorded()[0]['request'];
        $this->assertSame('https://api.lexware.io/v1/order-confirmations', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame(['shippingType' => 'none'], $body['shippingConditions']);
        $this->assertSame(['contactId' => self::CONTACT_ID], $body['address']);
    }

    /** QuotationsEndpoint::create(): POST /quotations ohne Query, Body inhaltlich gleich dem Payload der App. */
    public function test_quotation_push_creates_quotation(): void {
        $this->seedContactReference();
        $order = $this->makeOrder();

        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/quotations*' => FakePluginHttp::response(['id' => 'lex-q-1', 'resourceUri' => 'https://api.lexware.io/v1/quotations/lex-q-1', 'version' => 1], 201),
        ]);

        $reference = app(LexofficeQuotationService::class)->push($order);

        $this->assertSame(LexofficeQuotationService::EXT_TYPE_QUOTATION, $reference->external_type);
        $this->assertSame('lex-q-1', $reference->external_id);
        $this->assertSame('https://api.lexware.io/v1/quotations/lex-q-1', $reference->payload['resourceUri'] ?? null);

        $fake->assertSentCount(1);
        $request = $fake->recorded()[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.lexware.io/v1/quotations', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertEquals($reference->payload['_request'], $body);
        $this->assertSame(['contactId' => self::CONTACT_ID], $body['address']);
        $this->assertEquals(['currency' => 'EUR', 'netAmount' => 200.0, 'taxRatePercentage' => 19.0], $body['lineItems'][0]['unitPrice']);
        $this->assertEquals(5.0, $body['lineItems'][0]['quantity']);
        $this->assertStringContainsString('Spezialteil', $body['lineItems'][0]['name']);
    }

    /** Lexware verlangt beim Angebot ein Ablaufdatum; der Fertigungsauftrag hat keins, also die Bindefrist der Organisation. */
    public function test_quotation_expires_after_the_organizations_validity(): void {
        $this->travelTo('2030-03-01 10:00:00');
        Setting::set('invoicing.quote_validity_days', 21, SettingScope::Organization, $this->organization);
        $this->seedContactReference();
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/quotations*' => FakePluginHttp::response(['id' => 'lex-q-7'], 201),
        ]);

        $reference = app(LexofficeQuotationService::class)->push($this->makeOrder());

        $body = json_decode((string) $fake->recorded()[0]['request']->getBody(), true);
        $this->assertSame('2030-03-22T23:59:59.999+00:00', $body['expirationDate']);
        $this->assertSame($body['expirationDate'], $reference->payload['_request']['expirationDate']);
    }

    public function test_second_push_returns_the_linked_document_without_creating_another(): void {
        $this->seedContactReference();
        $order = $this->makeOrder();
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/quotations*' => FakePluginHttp::response(['id' => 'lex-q-1'], 201),
        ]);

        $first = app(LexofficeQuotationService::class)->push($order);
        $second = app(LexofficeQuotationService::class)->push($order->fresh());

        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, collect($fake->recorded())->filter(fn (array $entry): bool => $entry['request']->getMethod() === 'POST')->count());
    }

    public function test_push_without_customer_throws(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);
        $order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'customer_id' => null, 'target_qty' => '1', 'unit' => 'Stk',
        ]);

        $this->expectException(RuntimeException::class);
        app(LexofficeOrderConfirmationService::class)->push($order);
    }

    /** Zurücklesen über QuotationsEndpoint/OrderConfirmationsEndpoint::get(): Pfad je Belegart, Inhalt in Array-Form. */
    public function test_pull_reads_the_linked_document_through_the_sdk(): void {
        $order = $this->makeOrder();
        foreach ([LexofficeOrderConfirmationService::class => 'oc-9', LexofficeQuotationService::class => 'qu-9'] as $service => $externalId) {
            ExternalReference::create([
                'organization_id' => $this->organization->id,
                'plugin_id' => LexofficePlugin::ID,
                'external_type' => app($service)->extType(),
                'referenceable_type' => $order->getMorphClass(),
                'referenceable_id' => $order->getKey(),
                'external_id' => $externalId,
                'synced_at' => now(),
            ]);
        }
        $document = static fn (string $id, string $number): array => [
            'id' => $id, 'voucherStatus' => 'open', 'voucherNumber' => $number, 'voucherDate' => '2026-10-01T00:00:00.000+02:00',
            'address' => ['contactId' => self::CONTACT_ID, 'name' => 'Kunde', 'countryCode' => 'DE'],
            'lineItems' => [['type' => 'custom', 'name' => 'Spezialteil', 'quantity' => 5, 'unitName' => 'Stk', 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 200.0, 'grossAmount' => 238.0, 'taxRatePercentage' => 19]]],
            'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => 1000.0, 'totalGrossAmount' => 1190.0, 'totalTaxAmount' => 190.0],
            'taxConditions' => ['taxType' => 'net'],
        ];
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/order-confirmations/oc-9' => FakePluginHttp::response($document('oc-9', 'AB0009')),
            'https://api.lexware.io/v1/quotations/qu-9' => FakePluginHttp::response($document('qu-9', 'AN0009')),
        ]);

        $confirmation = app(LexofficeOrderConfirmationService::class)->pull($order);
        $quotation = app(LexofficeQuotationService::class)->pull($order);

        $this->assertSame(['oc-9', 'AB0009', 'open'], [$confirmation['id'], $confirmation['voucherNumber'], $confirmation['voucherStatus']]);
        $this->assertSame(['qu-9', 'AN0009'], [$quotation['id'], $quotation['voucherNumber']]);
        $this->assertEquals(1190.0, $quotation['totalPrice']['totalGrossAmount']);
        $this->assertSame(
            ['GET https://api.lexware.io/v1/order-confirmations/oc-9', 'GET https://api.lexware.io/v1/quotations/qu-9'],
            array_map(static fn (array $entry): string => $entry['request']->getMethod() . ' ' . $entry['request']->getUri(), $fake->recorded()),
        );
    }

    public function test_pull_reports_the_http_status(): void {
        $order = $this->makeOrder();
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => app(LexofficeQuotationService::class)->extType(),
            'referenceable_type' => $order->getMorphClass(),
            'referenceable_id' => $order->getKey(),
            'external_id' => 'qu-404',
            'synced_at' => now(),
        ]);
        FakePluginHttp::fake(['https://api.lexware.io/v1/quotations/qu-404' => FakePluginHttp::response(['message' => 'not found'], 404)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lexoffice quotations fetch failed: HTTP 404');
        app(LexofficeQuotationService::class)->pull($order);
    }
}
