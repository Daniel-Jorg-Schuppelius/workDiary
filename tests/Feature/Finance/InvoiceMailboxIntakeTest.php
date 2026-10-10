<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceMailboxIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition, InvoiceStatus};
use App\Models\Customer\Customer;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Models\Mail\EmailConnection;
use App\Models\Platform\User;
use App\Services\Billing\Sepa\PaymentProposalService;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Mail\{MailAttachment, MailIntakeService, ParsedMessage};
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\{GeneratesIncomingEInvoices, WithOrganization};
use Tests\TestCase;

/** Feature 163, MVP-1107: Rechnungspostfach — ein Eingang je Rechnung, Klärfall, Richtung. */
final class InvoiceMailboxIntakeTest extends TestCase {
    use GeneratesIncomingEInvoices;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Lieferant GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE123456789',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@lieferant.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Lieferant GmbH',
            'payment_terms_days' => 14,
        ]]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_xml_with_visual_pdf_and_terms_becomes_one_incoming_with_companions(): void {
        $xml = $this->incomingXml($this->localInvoice('ER-2026-0042'));
        $visual = $this->pdf('<h1>Rechnung ER-2026-0042</h1><p>Gesamtbetrag 238,00 EUR</p>');
        $terms = $this->pdf('<h1>Allgemeine Geschäftsbedingungen</h1><p>Es gilt deutsches Recht.</p>');

        $result = $this->intake([
            new MailAttachment('ER-2026-0042.xml', 'application/xml', $xml),
            new MailAttachment('ER-2026-0042.pdf', 'application/pdf', $visual),
            new MailAttachment('agb.pdf', 'application/pdf', $terms),
        ]);

        $this->assertSame('einvoice', $result);
        $incoming = IncomingEInvoice::query()->sole();
        $this->assertSame(IncomingInvoiceRecognition::Structured, $incoming->recognition);
        $this->assertSame(DocumentDirection::Incoming, $incoming->direction);
        $this->assertSame(DocumentKind::Invoice, $incoming->kind);
        $this->assertSame('ER-2026-0042', $incoming->invoice_number);
        $this->assertSame('lieferant@example.test', $incoming->sender_email);
        $this->assertSame('<m1@example.test>', $incoming->source_reference);
        $this->assertEqualsCanonicalizing(['ER-2026-0042.pdf', 'agb.pdf'], $incoming->attachments->pluck('original_name')->all());
        $this->assertNotContains(
            __('Rechnungsnummer :number dieses Ausstellers wurde bereits erfasst (möglicher Doppel-Eingang mit anderem Dateiinhalt).', ['number' => 'ER-2026-0042']),
            (array) data_get($incoming->summary, 'deviations'),
        );

        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $incoming->document))
            ->assertOk()->assertSee('agb.pdf')->assertSee('lieferant@example.test');
    }

    public function test_two_invoice_pdfs_in_one_mail_become_two_incomings(): void {
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => self::BUYER_VAT_ID]]]);

        $this->intake([
            new MailAttachment('rechnung-1.pdf', 'application/pdf', $this->invoicePdf('MP-4711', '1.190,00')),
            new MailAttachment('rechnung-2.pdf', 'application/pdf', $this->invoicePdf('MP-4712', '595,00')),
        ]);

        $incomings = IncomingEInvoice::query()->orderBy('invoice_number')->get();
        $this->assertSame(['MP-4711', 'MP-4712'], $incomings->pluck('invoice_number')->all());
        $this->assertTrue($incomings->every(static fn (IncomingEInvoice $i): bool => $i->recognition === IncomingInvoiceRecognition::Extracted));
        // PDF statt E-Rechnung über 250 €: Hinweis, keine Sperre.
        $this->assertNotSame([], (array) data_get($incomings->firstOrFail()->summary, 'notices'));
    }

    public function test_unrecognisable_scan_becomes_a_clarification_case_instead_of_being_dropped(): void {
        $result = $this->intake([
            new MailAttachment('scan.pdf', 'application/pdf', $this->pdf('<p>Nur Text ohne Rechnung.</p>')),
        ]);

        $this->assertSame('einvoice', $result);
        $incoming = IncomingEInvoice::query()->sole();
        $this->assertSame(IncomingInvoiceRecognition::None, $incoming->recognition);
        $this->assertSame(IncomingEInvoiceStatus::Received, $incoming->status);
        $this->assertNull($incoming->invoice_number);
        $this->assertNull($incoming->amount_gross);
        $this->assertNotNull($incoming->document?->currentVersion);
        $this->assertContains(__('Keine Rechnungsdaten erkannt — Original prüfen und die Werte erfassen.'), (array) data_get($incoming->summary, 'deviations'));
        $this->assertSame(0, IntegrationInboxItem::query()->count());

        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $incoming->document))
            ->assertOk()->assertSee(__('Keine Rechnungsdaten erkannt (Klärfall).'));
    }

    public function test_inline_and_small_images_are_no_invoices_and_the_mail_falls_through(): void {
        $result = $this->intake([
            new MailAttachment('logo.png', 'image/png', str_repeat('x', 30_000), isInline: true),
            new MailAttachment('signatur.png', 'image/png', str_repeat('x', 4_000)),
        ]);

        $this->assertSame('created', $result);
        $this->assertSame(0, IncomingEInvoice::query()->count());
        $item = IntegrationInboxItem::query()->sole();
        $this->assertTrue((bool) data_get($item->remote_snapshot, 'invoice_mailbox'));
    }

    public function test_copy_of_an_own_outgoing_invoice_is_outgoing_and_never_payable(): void {
        // Ohne Rollenwechsel: die Organisation ist Verkäuferin.
        $invoice = $this->localInvoice('RE-2026-0100');
        $xml = app(XRechnungGenerator::class)->generate($invoice->refresh()->load(['items', 'customer']));

        $this->intake([new MailAttachment('RE-2026-0100.xml', 'application/xml', $xml)]);

        $incoming = IncomingEInvoice::query()->sole();
        $this->assertSame(DocumentDirection::Outgoing, $incoming->direction);
        $this->assertSame('ACME GmbH', $incoming->buyer_name);
        $this->assertContains(
            __('Kopie der eigenen Rechnung :number — die Rechnung ist bereits erfasst.', ['number' => 'RE-2026-0100']),
            (array) data_get($incoming->summary, 'deviations'),
        );
        $this->assertSame(0, IncomingEInvoice::query()->purchases()->count());

        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.decide', $incoming), ['decision' => 'approved'])->assertRedirect();
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.decide', $incoming), ['decision' => 'payment_released'])
            ->assertSessionHas('error', __('Ausgangsbelege werden nicht zur Zahlung freigegeben.'));
        $incoming->forceFill(['status' => IncomingEInvoiceStatus::PaymentReleased])->save();
        $this->actingAs($this->admin);
        $this->assertCount(0, app(PaymentProposalService::class)->proposals());
    }

    public function test_buyer_with_a_foreign_vat_id_is_flagged_as_not_addressed_to_us(): void {
        $xml = $this->incomingXml($this->localInvoice('ER-2026-0077', 'DE277777770'));

        $this->intake([new MailAttachment('ER-2026-0077.xml', 'application/xml', $xml)]);

        $incoming = IncomingEInvoice::query()->sole();
        $this->assertSame(DocumentDirection::Incoming, $incoming->direction);
        $this->assertContains(
            __('Nicht an uns adressiert: Der Käufer trägt die USt-IdNr. :vat.', ['vat' => 'DE277777770']),
            (array) data_get($incoming->summary, 'deviations'),
        );
    }

    /** @param  list<MailAttachment>  $attachments */
    private function intake(array $attachments): string {
        $connection = EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Rechnungen',
            'host' => 'imap.example.test',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'rechnung@example.test',
            'password' => 'geheim',
            'folder' => 'INBOX',
            'active' => true,
            'einvoice_intake' => true,
            'created_by' => $this->admin->id,
        ]);

        return app(MailIntakeService::class)->intake($this->organization, $connection, new ParsedMessage(
            messageId: '<m1@example.test>',
            uid: 1,
            fromEmail: 'Lieferant@Example.test',
            fromName: 'Lieferant GmbH',
            subject: 'Ihre Rechnung',
            body: 'Anbei die Rechnung.',
            receivedAt: Carbon::now(),
            attachmentCount: count($attachments),
            attachments: $attachments,
        ));
    }

    private function localInvoice(string $number, ?string $customerVat = null): Invoice {
        $customer = Customer::query()->firstOrCreate([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
        ], [
            'currency' => 'EUR',
            'email' => 'buchhaltung@acme.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
            'buyer_reference' => '991-12345-67',
            'vat_id' => $customerVat,
            'created_by' => $this->admin->id,
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => $number,
            'status' => InvoiceStatus::Issued,
            'issued_on' => '2026-06-01',
            'due_on' => '2026-06-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Wartungspauschale',
            'quantity' => '2.00',
            'unit' => 'Std.',
            'unit_price' => '100.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice;
    }

    private function invoicePdf(string $number, string $gross): string {
        return $this->pdf(<<<HTML
            <p>Rechnung an: Muster GmbH, USt-IdNr. DE811907980</p>
            <h1>Rechnung</h1>
            <p>Rechnungsnummer: {$number}</p>
            <p>Rechnungsdatum: 14.09.2026</p>
            <p>Gesamtbetrag {$gross} EUR</p>
            <p>Malerbetrieb Pinsel · USt-IdNr. DE987654321</p>
            HTML);
    }

    private function pdf(string $bodyHtml): string {
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<!doctype html><html lang="de"><body>' . $bodyHtml . '</body></html>');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
