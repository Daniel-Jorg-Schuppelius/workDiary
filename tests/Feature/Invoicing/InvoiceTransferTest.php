<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Numbering\NumberScope;
use App\Enums\Sales\OrderConfirmationStatus;
use App\Mail\InvoiceMail;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{Invoice, InvoiceMailTemplate};
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillTarget;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\LexofficeOrderConfirmationService;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Invoicing\InvoiceGenerator;
use App\Services\Numbering\NumberSequenceService;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Phase 145, P4/P5 (MVP-1165–1169): Rechnung, Gutschrift und Storno an das vergebende Programm. */
final class InvoiceTransferTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->user = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Werft Nord GmbH', 'number' => 'K-1001']);
    }

    /** @param list<NumberScope> $scopes */
    private function program(string $pluginId, array $scopes = [NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation], ?string $contactId = null): void {
        PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        foreach ($scopes as $scope) {
            app(NumberSequenceService::class)->setFormat($this->org, $scope, ['source' => $pluginId]);
        }
        if ($contactId !== null) {
            ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => $pluginId, 'external_type' => ExternalReference::TYPE_CONTACT,
                'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->id, 'external_id' => $contactId]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function draft(array $attributes = []): Invoice {
        $invoice = Invoice::create($attributes + [
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => app(NumberSequenceService::class)->next($this->org, NumberScope::Invoice),
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->user->id,
        ]);
        $invoice->items()->create(['description' => 'Pumpe P-200', 'quantity' => '2.00', 'unit' => 'Stk', 'unit_price' => '500.00', 'discount_percent' => '10.00', 'position' => 1]);

        return $invoice->refresh();
    }

    /** @param array<string, mixed> $body */
    private static function reply(array $body, int $status = 200): \Closure {
        return static fn (): Psr7Response => FakePluginHttp::response($body, $status);
    }

    public function test_lexoffice_invoice_follows_the_order_confirmation(): void {
        $this->program(LexofficePlugin::ID, contactId: 'lex-contact-1');
        $quote = Quote::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $this->customer->id]);
        $confirmation = OrderConfirmation::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $this->customer->id, 'quote_id' => $quote->id, 'status' => OrderConfirmationStatus::Confirmed]);
        ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficeOrderConfirmationService::EXT_TYPE_ORDER_CONFIRMATION,
            'referenceable_type' => $confirmation->getMorphClass(), 'referenceable_id' => $confirmation->id, 'external_id' => 'lex-oc-1', 'external_number' => 'AB0001']);
        $invoice = $this->draft(['quote_id' => $quote->id]);
        $this->assertTrue(NumberSequenceService::isDraft($invoice->number));
        $this->actingAs($this->user)->get(route('invoices.show', $invoice))->assertOk()->assertSee(__('numbering.issue.invoice_pending', ['program' => 'Lexoffice']));
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices?precedingSalesVoucherId=lex-oc-1&finalize=true' => self::reply(['id' => 'lex-inv-1'], 201),
            'https://api.lexware.io/v1/invoices/lex-inv-1' => self::reply(['id' => 'lex-inv-1', 'voucherNumber' => 'RE0042']),
        ]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice))->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame([InvoiceStatus::Issued, 'RE0042'], [$invoice->status, $invoice->number]);
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            // Befund B9: Steuersatz und Rabatt je Zeile; der Satz der Rechnung gilt für Zeilen ohne eigenen.
            return $request->getMethod() === 'POST'
                && (float) data_get($body, 'lineItems.0.unitPrice.taxRatePercentage') === 19.0
                && (float) data_get($body, 'lineItems.0.discountPercentage') === 10.0
                && data_get($body, 'totalPrice.totalDiscountPercentage') !== null && (float) data_get($body, 'totalPrice.totalDiscountPercentage') === 0.0;
        });
    }

    public function test_lexoffice_rejected_predecessor_is_dropped(): void {
        $this->program(LexofficePlugin::ID, contactId: 'lex-contact-1');
        $quote = Quote::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $this->customer->id]);
        ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => 'quotation',
            'referenceable_type' => $quote->getMorphClass(), 'referenceable_id' => $quote->id, 'external_id' => 'lex-q-1', 'external_number' => 'AN0001']);
        $invoice = $this->draft(['quote_id' => $quote->id]);
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices?precedingSalesVoucherId=lex-q-1&finalize=true' => self::reply(['message' => 'not acceptable'], 406),
            'https://api.lexware.io/v1/invoices?finalize=true' => self::reply(['id' => 'lex-inv-2'], 201),
            'https://api.lexware.io/v1/invoices/lex-inv-2' => self::reply(['id' => 'lex-inv-2', 'voucherNumber' => 'RE0043']),
        ]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));

        $this->assertSame('RE0043', $invoice->refresh()->number);
        $fake->assertSentCount(3);
    }

    public function test_mail_transfers_before_the_subject_is_rendered(): void {
        Mail::fake();
        $this->program(LexofficePlugin::ID, contactId: 'lex-contact-1');
        $template = InvoiceMailTemplate::query()->create(['organization_id' => null, 'name' => 'T', 'is_default' => true,
            'subject' => 'Rechnung {{invoice_number}}', 'body_html' => '<p>x</p>', 'body_text' => 'x']);
        $invoice = $this->draft();
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices?finalize=true' => self::reply(['id' => 'lex-inv-3'], 201),
            'https://api.lexware.io/v1/invoices/lex-inv-3' => self::reply(['id' => 'lex-inv-3', 'voucherNumber' => 'RE0044']),
        ]);

        $this->actingAs($this->user)->post(route('invoices.send', $invoice), ['template_id' => $template->id, 'to' => ['kunde@example.test']])
            ->assertRedirect(route('invoices.show', $invoice));

        Mail::assertQueued(InvoiceMail::class, fn (InvoiceMail $mail): bool => str_contains($mail->renderedSubject, 'RE0044'));
    }

    public function test_easybill_invoice_and_its_cancellation(): void {
        $this->program(EasybillPlugin::ID, contactId: '501');
        $invoice = $this->draft();
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers/501' => self::reply(['id' => 501]),
            'https://api.easybill.de/rest/v1/documents/7101/done' => self::reply(['id' => 7101, 'number' => 'RE-EB-1']),
            'https://api.easybill.de/rest/v1/documents/7101/cancel' => self::reply(['id' => 7102, 'type' => 'STORNO', 'number' => 'ST-EB-1'], 201),
            'https://api.easybill.de/rest/v1/documents' => self::reply(['id' => 7101], 201),
        ]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));
        $this->assertSame('RE-EB-1', $invoice->refresh()->number);

        $this->actingAs($this->user)->post(route('invoices.cancel', $invoice))->assertRedirect();
        $cancellation = Invoice::query()->where('parent_invoice_id', $invoice->id)->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.issue', $cancellation));

        $this->assertSame(['ST-EB-1', InvoiceStatus::Issued], [$cancellation->refresh()->number, $cancellation->status]);
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST' && data_get($body, 'type') === 'INVOICE'
                && (float) data_get($body, 'items.0.vat_percent') === 19.0 && (float) data_get($body, 'items.0.discount') === 10.0;
        });
        $fake->assertSent(fn (RequestInterface $request): bool => $request->getMethod() === 'POST' && str_ends_with((string) $request->getUri(), '/documents/7101/cancel'));
        $this->assertSame(1, ExternalReference::query()->where('external_type', EasybillTarget::EXT_TYPE_INVOICE)->where('referenceable_id', $cancellation->id)->count());
    }

    public function test_sevdesk_invoice_takes_the_sequence_number_and_is_marked_sent(): void {
        $this->program(SevDeskPlugin::ID, [NumberScope::Invoice, NumberScope::Cancellation]);
        $invoice = $this->draft();
        $fake = FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Tools/bookkeepingSystemVersion*' => self::reply(['objects' => ['version' => '1.0']]),
            'https://my.sevdesk.de/api/v1/SevUser*' => self::reply(['objects' => [['id' => 7]]]),
            'https://my.sevdesk.de/api/v1/Contact?*' => self::reply(['objects' => []]),
            'https://my.sevdesk.de/api/v1/Contact' => self::reply(['objects' => ['id' => 501, 'customerNumber' => 'K-1001']], 201),
            'https://my.sevdesk.de/api/v1/SevSequence/Factory/getByType*' => self::reply(['objects' => ['nextSequence' => '1001', 'format' => 'RE-[%nr]']]),
            'https://my.sevdesk.de/api/v1/Invoice/Factory/saveInvoice' => self::reply(['objects' => ['invoice' => ['id' => 9001, 'status' => '100']]], 201),
            'https://my.sevdesk.de/api/v1/Invoice/9001/sendBy' => self::reply(['objects' => ['id' => 9001, 'status' => '200']]),
            'https://my.sevdesk.de/api/v1/Invoice/9001' => self::reply(['objects' => [['id' => 9001, 'invoiceNumber' => 'RE-1001', 'status' => '100']]]),
        ]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();

        $this->assertSame(['RE-1001', InvoiceStatus::Issued], [$invoice->refresh()->number, $invoice->status]);
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return str_ends_with((string) $request->getUri(), '/Invoice/Factory/saveInvoice')
                && data_get($body, 'invoice.invoiceNumber') === 'RE-1001'
                && data_get($body, 'invoice.status') === 100
                && (float) data_get($body, 'invoicePosSave.0.price') === 450.0;
        });
        $fake->assertSent(fn (RequestInterface $request): bool => $request->getMethod() === 'PUT' && str_ends_with((string) $request->getUri(), '/Invoice/9001/sendBy'));
    }

    public function test_sevdesk_cancellation_needs_a_transferred_invoice(): void {
        $original = $this->draft();
        app(\App\Services\Invoicing\InvoiceIssueService::class)->issue($original);
        $this->program(SevDeskPlugin::ID, [NumberScope::Cancellation]);
        $this->actingAs($this->user)->post(route('invoices.cancel', $original));
        $cancellation = Invoice::query()->where('parent_invoice_id', $original->id)->firstOrFail();
        $fake = FakePluginHttp::fake();

        $this->actingAs($this->user)->from(route('invoices.show', $cancellation))->post(route('invoices.issue', $cancellation))
            ->assertSessionHas('error');

        $this->assertSame(InvoiceStatus::Draft, $cancellation->refresh()->status);
        $fake->assertNothingSent();
    }

    public function test_down_payments_wait_for_the_program(): void {
        $this->program(LexofficePlugin::ID, [NumberScope::Invoice]);

        try {
            app(InvoiceGenerator::class)->downPaymentFor($this->customer, null, 'Abschlag 1', '1000.00');
            $this->fail('Abschlag trotz Programmvergabe angelegt.');
        } catch (ValidationException $e) {
            $this->assertSame([__('invoicing.down_payment.program_numbering', ['program' => 'Lexoffice'])], $e->errors()['invoice']);
        }
    }

    public function test_number_ranges_offer_every_program_for_invoices(): void {
        foreach ([LexofficePlugin::ID, EasybillPlugin::ID, SevDeskPlugin::ID] as $pluginId) {
            PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        }

        $response = $this->actingAs($this->user)->get(route('admin.number-formats.index'))->assertOk();
        $rows = collect((array) $response->viewData('rows'))->keyBy(fn (array $row): string => $row['scope']->value);

        $this->assertEqualsCanonicalizing(['local', LexofficePlugin::ID, EasybillPlugin::ID, SevDeskPlugin::ID], array_keys((array) $rows['invoice']['sourceOptions']));
        $this->assertEqualsCanonicalizing(['local', LexofficePlugin::ID, EasybillPlugin::ID], array_keys((array) $rows['credit_note']['sourceOptions']));
    }
}
