<?php
/*
 * Created on   : Mon Nov 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTransferTaxCaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\{InvoiceStatus, SalesTaxCase};
use App\Enums\Numbering\NumberScope;
use App\Exceptions\UnmappableTaxCaseException;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\OrgaMax\Services\OrgaMaxDocumentIssuer;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Numbering\NumberSequenceService;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-1712: Rechnungen, die ein Programm beim Ausstellen übernimmt, tragen
 * dort die Steuerart ihres Steuerfalls (Lexware `taxConditions`, sevDesk
 * `taxRule`, easybill `vat_option`); orgaMAX führt keine Steuerart und
 * übernimmt nur den Standardfall und § 19.
 */
final class InvoiceTransferTaxCaseTest extends TestCase {
    use RefreshDatabase;

    private const string LEXOFFICE_CONTACT = 'e5b7a6c4-1f2d-4c3b-9a8e-7d6c5b4a3f21';

    private Organization $org;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 10:00:00');
        $this->org = Organization::factory()->create(['settings' => ['einvoice' => ['country' => 'DE']]]);
        app()->instance('currentOrganization', $this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->user = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    public function test_lexoffice_reverse_charge_invoice_goes_as_external_service_13b(): void {
        $customer = $this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627']);
        $this->program($customer, LexofficePlugin::ID, self::LEXOFFICE_CONTACT);
        $invoice = $this->draft($customer, ['is_reverse_charge' => true, 'tax_rate' => '0.00', 'notes' => config('taxation.notes.reverse_charge') . "\n\nVielen Dank für den Auftrag."]);
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices?*' => self::reply(['id' => 'lex-inv-1'], 201),
            'https://api.lexware.io/v1/invoices/lex-inv-1' => self::reply(['id' => 'lex-inv-1', 'voucherNumber' => 'RE0042']),
        ]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();

        $this->assertSame(InvoiceStatus::Issued, $invoice->refresh()->status);
        $body = self::body($fake, '/invoices');
        $this->assertSame('externalService13b', $body['taxConditions']['taxType']);
        $note = (string) $body['taxConditions']['taxTypeNote'];
        $this->assertStringContainsString('Reverse Charge', $note);
        $this->assertEquals(0, $body['lineItems'][0]['unitPrice']['taxRatePercentage']);
        // Der Hinweis steht als taxTypeNote auf dem Beleg, die Bemerkung trägt ihn nicht ein zweites Mal.
        $this->assertStringNotContainsString($note, (string) ($body['remark'] ?? ''));
        $this->assertStringContainsString('Vielen Dank für den Auftrag.', (string) ($body['remark'] ?? ''));
    }

    /** Steuerfreie Arten außer vatfree gehen bei Lexware nur mit angelegtem Kontakt. */
    public function test_lexoffice_needs_a_contact_for_reverse_charge(): void {
        $customer = $this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627']);
        $this->program($customer, LexofficePlugin::ID);
        $invoice = $this->draft($customer, ['is_reverse_charge' => true, 'tax_rate' => '0.00']);
        $fake = FakePluginHttp::fake([]);

        $this->actingAs($this->user)->from(route('invoices.show', $invoice))->post(route('invoices.issue', $invoice))->assertSessionHas('error');

        $this->assertSame(InvoiceStatus::Draft, $invoice->refresh()->status);
        $fake->assertNothingSent();
    }

    /** Gegenprobe Inland: Voreinstellung ohne Hinweis, Satz der Rechnung. */
    public function test_lexoffice_domestic_invoice_keeps_net(): void {
        $customer = $this->customer(['country' => 'DE']);
        $this->program($customer, LexofficePlugin::ID, self::LEXOFFICE_CONTACT);
        $invoice = $this->draft($customer);
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices?*' => self::reply(['id' => 'lex-inv-2'], 201),
            'https://api.lexware.io/v1/invoices/lex-inv-2' => self::reply(['id' => 'lex-inv-2', 'voucherNumber' => 'RE0043']),
        ]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();

        $body = self::body($fake, '/invoices');
        $this->assertSame(['taxType' => 'net'], $body['taxConditions']);
        $this->assertEquals(19, $body['lineItems'][0]['unitPrice']['taxRatePercentage']);
    }

    public function test_sevdesk_domestic_reverse_charge_invoice_uses_rule_5(): void {
        $customer = $this->customer(['country' => 'DE', 'is_reverse_charge_recipient' => true]);
        $this->program($customer, SevDeskPlugin::ID);
        $invoice = $this->draft($customer, ['is_reverse_charge' => true, 'tax_rate' => '0.00']);
        $fake = FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Tools/bookkeepingSystemVersion*' => self::reply(['objects' => ['version' => '2.0']]),
            'https://my.sevdesk.de/api/v1/SevUser*' => self::reply(['objects' => [['id' => 7]]]),
            'https://my.sevdesk.de/api/v1/Contact?*' => self::reply(['objects' => []]),
            'https://my.sevdesk.de/api/v1/Contact' => self::reply(['objects' => ['id' => 501, 'customerNumber' => 'K-1001']], 201),
            'https://my.sevdesk.de/api/v1/SevSequence/Factory/getByType*' => self::reply(['objects' => ['nextSequence' => '1001', 'format' => 'RE-[%nr]']]),
            'https://my.sevdesk.de/api/v1/Invoice/Factory/saveInvoice' => self::reply(['objects' => ['invoice' => ['id' => 9001, 'status' => '100']]], 201),
            'https://my.sevdesk.de/api/v1/Invoice/9001/sendBy' => self::reply(['objects' => ['id' => 9001, 'status' => '200']]),
            'https://my.sevdesk.de/api/v1/Invoice/9001' => self::reply(['objects' => [['id' => 9001, 'invoiceNumber' => 'RE-1001', 'status' => '100']]]),
        ]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();

        $body = self::body($fake, '/Invoice/Factory/saveInvoice');
        $this->assertEquals(['id' => 5, 'objectName' => 'TaxRule'], $body['invoice']['taxRule']);
        $this->assertEquals(0, $body['invoicePosSave'][0]['taxRate']);
    }

    public function test_easybill_material_invoice_to_a_third_country_is_an_export(): void {
        $customer = $this->customer(['country' => 'CH']);
        $this->program($customer, EasybillPlugin::ID, '501');
        $invoice = $this->draft($customer, ['category' => Invoice::CATEGORY_MATERIAL, 'tax_rate' => '0.00']);
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers/501' => self::reply(['id' => 501]),
            'https://api.easybill.de/rest/v1/documents/7101/done' => self::reply(['id' => 7101, 'number' => 'RE-EB-1']),
            'https://api.easybill.de/rest/v1/documents' => self::reply(['id' => 7101], 201),
        ]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();

        $body = self::body($fake, '/rest/v1/documents');
        $this->assertSame('AL', $body['vat_option']);
        $this->assertEquals(0, $body['items'][0]['vat_percent']);
    }

    /** Gegenprobe Inland: easybill bekommt kein vat_option. */
    public function test_easybill_domestic_invoice_sends_no_vat_option(): void {
        $customer = $this->customer(['country' => 'DE']);
        $this->program($customer, EasybillPlugin::ID, '501');
        $invoice = $this->draft($customer);
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers/501' => self::reply(['id' => 501]),
            'https://api.easybill.de/rest/v1/documents/7101/done' => self::reply(['id' => 7101, 'number' => 'RE-EB-2']),
            'https://api.easybill.de/rest/v1/documents' => self::reply(['id' => 7101], 201),
        ]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('vat_option', self::body($fake, '/rest/v1/documents'));
    }

    /** orgaMAX führt keine Steuerart: die Rechnung mit Reverse Charge entsteht dort nicht. */
    public function test_orgamax_refuses_a_reverse_charge_invoice(): void {
        $customer = $this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627']);
        $invoice = $this->draft($customer, ['is_reverse_charge' => true, 'tax_rate' => '0.00']);
        $fake = FakePluginHttp::fake([]);

        try {
            app(OrgaMaxDocumentIssuer::class)->issue($invoice, NumberScope::Invoice);
            $this->fail('orgaMAX-Rechnung trotz Reverse Charge angelegt.');
        } catch (UnmappableTaxCaseException $e) {
            $this->assertSame((string) __('orgamax::orgamax.tax_case.unmappable', ['case' => SalesTaxCase::EuReverseCharge->label()]), $e->getMessage());
        }

        $fake->assertNothingSent();
        $this->assertSame(0, ExternalReference::query()->where('plugin_id', 'orgamax')->count());
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes): Customer {
        return Customer::factory()->create($attributes + ['organization_id' => $this->org->id, 'name' => 'Werft Nord GmbH', 'number' => 'K-1001', 'vat_id' => null]);
    }

    /** Programm vergibt die Rechnungsnummer; mit `$contactId` ist der Kunde dort schon verknüpft. */
    private function program(Customer $customer, string $pluginId, ?string $contactId = null): void {
        PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        app(NumberSequenceService::class)->setFormat($this->org, NumberScope::Invoice, ['source' => $pluginId]);
        if ($contactId !== null) {
            ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => $pluginId, 'external_type' => ExternalReference::TYPE_CONTACT,
                'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id, 'external_id' => $contactId]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function draft(Customer $customer, array $attributes = []): Invoice {
        $invoice = Invoice::create($attributes + [
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => app(NumberSequenceService::class)->next($this->org, NumberScope::Invoice),
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->user->id,
        ]);
        $invoice->items()->create(['description' => 'Pumpe P-200', 'quantity' => '2.00', 'unit' => 'Stk', 'unit_price' => '500.00', 'position' => 1]);

        return $invoice->refresh();
    }

    /** @param array<string, mixed> $body */
    private static function reply(array $body, int $status = 200): \Closure {
        return static fn (): Psr7Response => FakePluginHttp::response($body, $status);
    }

    /** @return array<string, mixed> Rumpf der ersten POST-Anfrage, deren Pfad auf $path endet */
    private static function body(FakePluginHttp $fake, string $path): array {
        foreach ($fake->recorded() as $entry) {
            /** @var RequestInterface $request */
            $request = $entry['request'];
            if ($request->getMethod() === 'POST' && str_ends_with($request->getUri()->getPath(), $path)) {
                return (array) json_decode((string) $request->getBody(), true);
            }
        }
        self::fail('Keine Anfrage POST ' . $path . ' gesendet.');
    }
}
