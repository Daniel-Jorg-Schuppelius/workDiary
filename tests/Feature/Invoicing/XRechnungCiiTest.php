<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XRechnungCiiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\{InvoiceDeliveryFormat, XRechnungSyntax};
use App\Mail\InvoiceMail;
use App\Models\{Customer, DocumentDispatch, Invoice, InvoiceMailTemplate, User};
use App\Services\Invoicing\EInvoice\{EInvoiceValidationService, XRechnungGenerator};
use ERechnungToolkit\Parsers\ERechnungParser;
use ERechnungToolkit\Validators\{CiiSchemaValidator, KositValidator, UblSchemaValidator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * XRechnung in CII-Syntax und für Kleinunternehmer (MVP-805, erechnung-toolkit
 * v0.14): CII nur, wenn das Zustellformat es verlangt; Peppol bleibt UBL. Ohne
 * USt-IdNr. trägt die Steuernummer die Verkäuferkennung BT-29 — sonst lehnt
 * die Prüfung beim Empfänger die Rechnung nach BR-CO-26 ab.
 */
final class XRechnungCiiTest extends TestCase {
    private static int $invoiceNo = 0;

    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'Kleinbetrieb Schmidt']);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Stadtverwaltung Musterstadt',
            'currency' => 'EUR',
            'email' => 'rechnungseingang@musterstadt.example',
            'address_street' => 'Rathausplatz 1',
            'address_zip' => '54321',
            'address_city' => 'Musterstadt',
            'country' => 'DE',
            'buyer_reference' => '04011000-12345-67',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_small_business_without_vat_id_is_valid_in_both_syntaxes(): void {
        $this->seller(smallBusiness: true);
        $invoice = $this->invoice(taxRate: '0.00');
        $generator = app(XRechnungGenerator::class);

        $this->assertSame([], $generator->preflight($invoice)['errors']);

        $documents = [
            'ubl' => [$generator->generate($invoice, XRechnungSyntax::Ubl), new UblSchemaValidator],
            'cii' => [$generator->generate($invoice, XRechnungSyntax::Cii), new CiiSchemaValidator],
        ];
        foreach ($documents as $syntax => [$xml, $schema]) {
            $this->assertSame([], $schema->validate($xml), $syntax);

            $seller = (new ERechnungParser)->parse($xml)->getSeller();
            $this->assertNull($seller->getVatId(), $syntax);
            $this->assertSame('201/987/65432', $seller->getTaxRegistrationId(), $syntax);
            // BT-29: ohne sie weist KoSIT die Rechnung nach BR-CO-26 ab.
            $this->assertSame('201/987/65432', $seller->getLegalEntityId(), $syntax);

            $kosit = new KositValidator;
            if ($kosit->isAvailable()) {
                $result = $kosit->validate($xml);
                $this->assertTrue($result->isAccepted(), $syntax . ': ' . implode('; ', array_map('strval', $result->getErrors())));
            }
        }
    }

    public function test_seller_with_vat_id_gets_no_additional_identifier(): void {
        $this->seller(vatId: 'DE123456789');

        $seller = (new ERechnungParser)->parse(app(XRechnungGenerator::class)->generate($this->invoice()))->getSeller();

        $this->assertSame('DE123456789', $seller->getVatId());
        $this->assertNull($seller->getLegalEntityId());
    }

    public function test_download_follows_the_cii_delivery_format(): void {
        $this->seller(vatId: 'DE123456789');
        $invoice = $this->invoice(format: InvoiceDeliveryFormat::XRechnungCii);

        $response = $this->actingAs($this->admin)->get(route('invoices.einvoice', $invoice))->assertOk();

        $xml = (string) $response->getContent();
        $this->assertStringContainsString('CrossIndustryInvoice', $xml);
        $this->assertSame([], (new CiiSchemaValidator)->validate($xml));
        $this->assertSame('xrechnung_cii', DocumentDispatch::query()->where('invoice_id', $invoice->id)->value('format'));
    }

    public function test_ubl_stays_the_default_without_a_cii_request(): void {
        $this->seller(vatId: 'DE123456789');
        $invoice = $this->invoice(format: InvoiceDeliveryFormat::XRechnung);

        $xml = (string) $this->actingAs($this->admin)->get(route('invoices.einvoice', $invoice))->assertOk()->getContent();

        $this->assertStringContainsString('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"', $xml);
        $this->assertSame(XRechnungSyntax::Ubl, InvoiceDeliveryFormat::PdfAndXRechnung->xrechnungSyntax());
    }

    public function test_mail_in_cii_format_attaches_cii_and_records_it(): void {
        Mail::fake();
        $this->seller(vatId: 'DE123456789');
        $template = InvoiceMailTemplate::query()->create([
            'organization_id' => null,
            'name' => 'E-Rechnung CII',
            'is_default' => true,
            'subject' => 'Rechnung {{invoice_number}}',
            'body_html' => '<p>{{invoice_number}}</p>',
            'body_text' => '{{invoice_number}}',
        ]);
        $invoice = $this->invoice(status: Invoice::STATUS_DRAFT);

        $this->actingAs($this->admin)->post(route('invoices.send', $invoice), [
            'template_id' => $template->id,
            'to' => ['rechnungseingang@musterstadt.example'],
            'delivery_format' => InvoiceDeliveryFormat::XRechnungCii->value,
        ])->assertRedirect(route('invoices.show', $invoice));

        $this->assertSame('xrechnung_cii', DocumentDispatch::query()->where('invoice_id', $invoice->id)->value('format'));
        Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $mail): bool {
            $xml = collect($mail->attachments())
                ->map(static fn (Attachment $attachment): string => (string) $attachment->attachWith(static fn (): string => '', static fn (\Closure $data): string => (string) $data()))
                ->first(static fn (string $content): bool => str_starts_with(ltrim($content), '<?xml'));

            return $xml !== null && str_contains($xml, 'CrossIndustryInvoice') && (new CiiSchemaValidator)->validate($xml) === [];
        });
    }

    public function test_validation_report_checks_the_schema_of_the_delivered_syntax(): void {
        $this->seller(vatId: 'DE123456789');
        $invoice = $this->invoice(format: InvoiceDeliveryFormat::XRechnungCii);

        $report = app(EInvoiceValidationService::class)->validate($invoice);

        $this->assertSame('cii', $report['syntax']);
        $this->assertTrue($report['xml_generated']);
        $this->assertSame([], $report['schema_errors']);
        $this->assertSame([], $report['consistency']['errors']);
    }

    private function seller(string $vatId = '', bool $smallBusiness = false): void {
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Kleinbetrieb Schmidt',
            'street' => 'Werkstattweg 3',
            'zip' => '50667',
            'city' => 'Köln',
            'country' => 'DE',
            'vat_id' => $vatId,
            'tax_number' => '201/987/65432',
            'contact_name' => 'Anna Schmidt',
            'contact_email' => 'anna@kleinbetrieb.example',
            'contact_phone' => '+49 221 55555',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Anna Schmidt',
            'payment_terms_days' => 14,
            'small_business' => $smallBusiness ? '1' : '0',
        ]]]);
    }

    private function invoice(string $status = Invoice::STATUS_ISSUED, string $taxRate = '19.00', InvoiceDeliveryFormat $format = InvoiceDeliveryFormat::Pdf): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'KB-2026-' . str_pad((string) ++self::$invoiceNo, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'issued_on' => '2026-09-01',
            'due_on' => '2026-09-15',
            'currency' => 'EUR',
            'tax_rate' => $taxRate,
            'delivery_format' => $format,
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Reparatur Heizungssteuerung',
            'quantity' => '2.00',
            'unit' => 'Std.',
            'unit_price' => '90.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh(['items', 'customer']);
    }
}
