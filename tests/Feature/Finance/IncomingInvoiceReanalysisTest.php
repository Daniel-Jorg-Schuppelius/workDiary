<?php
/*
 * Created on   : Sun Oct 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceReanalysisTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition, InvoiceStatus};
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Models\Platform\User;
use App\Services\Document\DocumentService;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{GeneratesIncomingEInvoices, WithOrganization};
use Tests\TestCase;

/**
 * Nacherkennung im Rechnungseingang (MVP-1207): Auf dem Server fehlten
 * pdftotext, tesseract und ocrmypdf — jede Mail-Rechnung wurde ein Klärfall
 * ohne Nummer und Betrag, und die Erkennung lief nur einmal beim Eingang.
 * Befehl und Sammelaktion lesen das abgelegte Original erneut; was ein
 * Mensch erfasst, übergeben oder abgelehnt hat, bleibt stehen.
 */
class IncomingInvoiceReanalysisTest extends TestCase {
    use GeneratesIncomingEInvoices, RefreshDatabase, WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Lieferant GmbH', 'street' => 'Musterstraße 1', 'zip' => '12345', 'city' => 'Berlin', 'country' => 'DE',
            'vat_id' => 'DE123456789', 'contact_name' => 'Max Muster', 'contact_email' => 'rechnung@lieferant.example', 'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX', 'account_holder' => 'Lieferant GmbH', 'payment_terms_days' => 14,
        ]]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_a_clarification_case_is_recognised_from_its_stored_original(): void {
        $incoming = $this->clarificationCase($this->invoiceXml('ER-2026-0042'));

        $this->assertTrue(app(IncomingEInvoiceService::class)->reanalyze($incoming));

        $incoming->refresh();
        $this->assertSame(IncomingInvoiceRecognition::Structured, $incoming->recognition);
        $this->assertSame('ER-2026-0042', $incoming->invoice_number);
        $this->assertSame('238.00', $incoming->amount_gross?->getAmount());
        $this->assertSame('Lieferant GmbH', $incoming->seller_name);
        $this->assertStringContainsString('ER-2026-0042', (string) $incoming->document?->title);
        $this->assertTrue($incoming->auditLogs()->where('event', 'incoming_einvoice.reanalyzed')->exists());
    }

    public function test_captured_values_transfers_and_rejections_stay_untouched(): void {
        $xml = $this->invoiceXml('ER-2026-0043');
        $captured = $this->clarificationCase($xml, ['summary' => ['manual' => ['invoice_number' => 'HAND-1']]]);
        $transferred = $this->clarificationCase($xml, ['transferred_at' => now()]);
        $rejected = $this->clarificationCase($xml, ['status' => IncomingEInvoiceStatus::Rejected]);

        foreach ([$captured, $transferred, $rejected] as $incoming) {
            $this->assertFalse(IncomingEInvoiceService::isReanalyzable($incoming));
            $this->assertFalse(app(IncomingEInvoiceService::class)->reanalyze($incoming));
            $this->assertSame(IncomingInvoiceRecognition::None, $incoming->refresh()->recognition);
        }
    }

    public function test_bulk_action_reports_recognised_unchanged_and_skipped(): void {
        $recognisable = $this->clarificationCase($this->invoiceXml('ER-2026-0044'));
        $unreadable = $this->clarificationCase('Kein Beleg, nur eine Notiz.', name: 'notiz.txt', mime: 'text/plain');
        $captured = $this->clarificationCase($this->invoiceXml('ER-2026-0045'), ['summary' => ['manual' => ['invoice_number' => 'HAND-2']]]);

        $this->post(route('finance.incoming-invoices.bulk-reanalyze'), ['ids' => [$recognisable->sqid, $unreadable->sqid, $captured->sqid]])
            ->assertRedirect()
            ->assertSessionHas('warning', __(':recognized Eingänge erkannt, :unchanged weiterhin Klärfall, :skipped übersprungen.', [
                'recognized' => 1, 'unchanged' => 1, 'skipped' => 1,
            ]));

        $this->assertSame(IncomingInvoiceRecognition::Structured, $recognisable->refresh()->recognition);
        $this->assertSame(IncomingInvoiceRecognition::None, $unreadable->refresh()->recognition);
        $this->assertSame(IncomingInvoiceRecognition::None, $captured->refresh()->recognition);
        // Bleibt es ein Klärfall, sagt die Diagnose wenigstens, woran es lag.
        $this->assertSame('format_not_supported', data_get($unreadable->summary, 'extraction.reason'));
        $this->assertContains(
            __('Dateityp :type wird nicht als Beleg gelesen — erkannt werden PDF, Bilder und E-Rechnungs-XML.', ['type' => 'text/plain']),
            (array) data_get($unreadable->summary, 'deviations'),
        );
    }

    public function test_command_reanalyses_open_clarification_cases_per_organization(): void {
        $incoming = $this->clarificationCase($this->invoiceXml('ER-2026-0046'));

        $this->artisan('incoming-invoices:reanalyze', ['--organization' => $this->organization->id])
            ->expectsOutputToContain('1 erkannt, 0 weiterhin Klärfall, 0 übersprungen')
            ->assertSuccessful();

        $this->assertSame(IncomingInvoiceRecognition::Structured, $incoming->refresh()->recognition);
    }

    private function invoiceXml(string $number): string {
        $customer = Customer::query()->firstOrCreate(['organization_id' => $this->organization->id, 'name' => 'Muster GmbH'], [
            'currency' => 'EUR', 'email' => 'einkauf@muster.example', 'address_street' => 'Kundenweg 7',
            'address_zip' => '54321', 'address_city' => 'Hamburg', 'country' => 'DE', 'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => $number,
            'status' => InvoiceStatus::Issued, 'issued_on' => '2026-06-01', 'due_on' => '2026-06-15',
            'currency' => 'EUR', 'tax_rate' => '19.00', 'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id, 'description' => 'Wartungspauschale',
            'quantity' => '2.00', 'unit' => 'Std.', 'unit_price' => '100.00', 'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $this->incomingXml($invoice);
    }

    /**
     * Klärfall wie aus dem Postfach: Dokument mit abgelegtem Original, Eingang ohne Werte.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function clarificationCase(string $contents, array $attributes = [], string $name = 'rechnung.xml', string $mime = 'application/xml'): IncomingEInvoice {
        $document = Document::factory()->create([
            'organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice,
            'created_by_user_id' => $this->admin->id, 'title' => 'Rechnungseingang ohne erkannte Daten — ' . $name,
        ]);
        app(DocumentService::class)->addVersionFromContents($document, $this->admin, $contents, $name, $mime);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'mail', 'sender_email' => 'info@example.test', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'recognition' => IncomingInvoiceRecognition::None, 'summary' => [],
            ...$attributes,
        ]);
    }
}
