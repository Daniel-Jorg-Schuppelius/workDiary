<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeSdkRequestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\Exceptions\LexofficeApiException;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\{LexofficeContactLookup, LexofficeDraftInvoiceService, LexofficeInvoiceService};
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lexoffice\API\Client;
use RuntimeException;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Befund tk-09: Rechnungsentwurf, Kontaktsuche sowie Abruf und PDF von
 * Rechnung und Gutschrift laufen über die Endpunkte des Lexoffice-SDK —
 * mit denselben Requests wie vorher roh.
 */
final class LexofficeSdkRequestTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const CONTACT_ID = '7c6b5a49-3827-4e16-9f05-e4d3c2b1a098';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key']);
    }

    /** @return list<string> */
    private static function sent(FakePluginHttp $fake): array {
        return array_map(
            static fn (array $entry): string => $entry['request']->getMethod() . ' ' . $entry['request']->getUri(),
            $fake->recorded(),
        );
    }

    private function draftService(): LexofficeDraftInvoiceService {
        return new LexofficeDraftInvoiceService($this->organization->id);
    }

    /** InvoicesEndpoint::create() ohne finalize: POST /invoices, Body Feld für Feld wie der bisherige Payload. */
    public function test_draft_invoice_is_posted_through_the_invoices_endpoint(): void {
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices' => FakePluginHttp::response(['id' => 'draft-1', 'resourceUri' => 'https://api.lexware.io/v1/invoices/draft-1', 'version' => 1], 201),
        ]);

        $id = $this->draftService()->createDraft(
            self::CONTACT_ID,
            [['name' => 'Microsoft 365 Business Basic', 'description' => 'Steuerbüro Kaik, 09.04.2025 – 08.04.2026', 'quantity' => 12.0, 'unit_name' => 'Monat', 'unit_net' => 5.6]],
            'Rechnung',
            'Einleitung',
            '',
            19.0,
            'EUR',
            'net',
            CarbonImmutable::parse('2026-10-22'),
        );

        $this->assertSame('draft-1', $id);
        $this->assertSame(['POST https://api.lexware.io/v1/invoices'], self::sent($fake));
        $this->assertEquals([
            'voucherDate' => '2026-10-22T00:00:00.000+01:00',
            'address' => ['contactId' => self::CONTACT_ID],
            'lineItems' => [[
                'type' => 'custom',
                'name' => 'Microsoft 365 Business Basic',
                'description' => 'Steuerbüro Kaik, 09.04.2025 – 08.04.2026',
                'quantity' => 12,
                'unitName' => 'Monat',
                'unitPrice' => ['currency' => 'EUR', 'netAmount' => 5.6, 'taxRatePercentage' => 19],
            ]],
            'totalPrice' => ['currency' => 'EUR'],
            'taxConditions' => ['taxType' => 'net'],
            'shippingConditions' => ['shippingDate' => '2026-10-22T00:00:00.000+01:00', 'shippingType' => 'service'],
            'title' => 'Rechnung',
            'introduction' => 'Einleitung',
        ], json_decode((string) $fake->recorded()[0]['request']->getBody(), true));
    }

    public function test_draft_invoice_errors_keep_the_lexoffice_message(): void {
        FakePluginHttp::fake(['https://api.lexware.io/v1/invoices' => FakePluginHttp::response(['message' => 'forbidden'], 403)]);

        try {
            $this->draftService()->createDraft(self::CONTACT_ID, [['name' => 'A', 'description' => '', 'quantity' => 1.0, 'unit_name' => 'Stk', 'unit_net' => 1.0]], 'R', '', '', 19.0);
            $this->fail('403 muss durchschlagen.');
        } catch (LexofficeApiException $e) {
            $this->assertStringContainsString('(403)', $e->getMessage());
            $this->assertStringContainsString('Rechnungen anlegen', $e->getMessage());
        }
    }

    /** isValid() der Rechnungs-Entität: eine Kontakt-ID ohne UUID-Form geht gar nicht erst hinaus. */
    public function test_draft_invoice_with_an_invalid_contact_is_not_sent(): void {
        $fake = FakePluginHttp::fake();

        try {
            $this->draftService()->createDraft('kein-uuid', [['name' => 'A', 'description' => '', 'quantity' => 1.0, 'unit_name' => 'Stk', 'unit_net' => 1.0]], 'R', '', '', 19.0);
            $this->fail('Ungültige Belegadresse darf nicht gesendet werden.');
        } catch (RuntimeException $e) {
            $this->assertSame('Rechnungsentwurf ungültig: Invoice data is not valid', $e->getMessage());
        }
        $fake->assertNothingSent();
    }

    /** ContactsEndpoint::search(): eine gescheiterte Suche heißt „kein Kontakt“, nicht Fehler. */
    public function test_contact_lookup_treats_a_failed_search_as_no_contact(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde+lager@example.com']);
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/contacts*' => [
                FakePluginHttp::response(['message' => 'boom'], 500),
                FakePluginHttp::response(['content' => []]),
            ],
        ]);
        $lookup = app(LexofficeContactLookup::class);
        $client = $this->sdkClient();

        $this->assertNull($lookup->find($customer, $client));
        $this->assertNull($lookup->find($customer, $client));
        $this->assertSame('GET https://api.lexware.io/v1/contacts?email=kunde%2Blager%40example.com&page=0&size=1', self::sent($fake)[0]);
        $this->assertSame(0, ExternalReference::query()->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)->count());
    }

    /** InvoicesEndpoint/CreditNotesEndpoint::get() und ::downloadContent() je Ressource der Verknüpfung. */
    public function test_fetch_and_pdf_use_the_endpoint_of_the_resource(): void {
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/credit-notes/cn-1/file' => new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-cn'),
            'https://api.lexware.io/v1/credit-notes/cn-1' => FakePluginHttp::response(['id' => 'cn-1', 'voucherNumber' => 'GS0001', 'voucherStatus' => 'open', 'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => 100.0]]),
            'https://api.lexware.io/v1/invoices/inv-404' => FakePluginHttp::response(['message' => 'not found'], 404),
        ]);
        $service = new LexofficeInvoiceService($this->organization->id);

        $fetched = $service->fetchInvoice('cn-1', LexofficeInvoiceService::RESOURCE_CREDIT_NOTES);
        $this->assertSame(['cn-1', 'GS0001', 'open'], [$fetched['id'], $fetched['voucherNumber'], $fetched['voucherStatus']]);
        $this->assertEquals(100.0, $fetched['totalPrice']['totalNetAmount']);
        $this->assertSame('%PDF-cn', $service->downloadPdf('cn-1', LexofficeInvoiceService::RESOURCE_CREDIT_NOTES));
        $this->assertSame('application/pdf', $fake->recorded()[1]['request']->getHeaderLine('Accept'));

        try {
            $service->fetchInvoice('inv-404');
            $this->fail('404 muss durchschlagen.');
        } catch (RuntimeException $e) {
            $this->assertSame('Lexoffice invoice fetch failed: 404 {"message":"not found"}', $e->getMessage());
        }

        $this->assertSame([
            'GET https://api.lexware.io/v1/credit-notes/cn-1',
            'GET https://api.lexware.io/v1/credit-notes/cn-1/file',
            'GET https://api.lexware.io/v1/invoices/inv-404',
        ], self::sent($fake));
    }

    private function sdkClient(): Client {
        return app(LexofficeClientFactory::class)->for($this->organization->id);
    }
}
