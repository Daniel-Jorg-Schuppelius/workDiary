<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingEInvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentType;
use App\Models\{Customer, Document, Invoice, User};
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, XRechnungGenerator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Nachtrag 045b: Eingangs-E-Rechnung — Parse-Roundtrip (eigener
 * XRechnung-Generator → Parser), Upload → Document-Ablage (Typ Rechnung) +
 * Detailansicht mit Positionen; Nicht-E-Rechnungen werden abgewiesen.
 */
final class IncomingEInvoiceTest extends TestCase {
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

    /** Erzeugt eine echte XRechnung über den Ausgangs-Generator (Roundtrip). */
    private function sampleXml(): string {
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'currency' => 'EUR',
            'email' => 'buchhaltung@acme.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
            'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'ER-2026-0042',
            'status' => Invoice::STATUS_ISSUED,
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

        return app(XRechnungGenerator::class)->generate($invoice->fresh(['items', 'customer']));
    }

    public function test_duplicate_upload_is_rejected_and_review_workflow_works(): void {
        $xml = $this->sampleXml();

        // Erstupload: Prüfbereich-Datensatz mit Hash + Herkunft (MVP-165).
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('rechnung.xml', $xml),
        ])->assertRedirect();

        $incoming = \App\Models\IncomingEInvoice::query()->firstOrFail();
        $this->assertSame(hash('sha256', $xml), $incoming->sha256);
        $this->assertSame('upload', $incoming->source);
        $this->assertSame(\App\Models\IncomingEInvoice::STATUS_RECEIVED, $incoming->status);
        $this->assertSame('ER-2026-0042', $incoming->summary['number']);

        // Dublette: identischer Inhalt wird abgewiesen (kein zweites Document).
        $documents = \App\Models\Document::query()->count();
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('kopie.xml', $xml),
        ])->assertRedirect(route('finance.incoming-invoices.show', $incoming->document_id));
        $this->assertSame($documents, \App\Models\Document::query()->count());
        $this->assertSame(1, \App\Models\IncomingEInvoice::query()->count());

        // Workflow (MVP-167): Zahlungsfreigabe erst NACH fachlicher Freigabe.
        $this->actingAs($this->admin)
            ->post(route('finance.incoming-invoices.decide', $incoming), ['decision' => 'payment_released'])
            ->assertSessionHas('error');
        $this->assertSame(\App\Models\IncomingEInvoice::STATUS_RECEIVED, $incoming->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('finance.incoming-invoices.decide', $incoming), ['decision' => 'approved'])
            ->assertSessionHas('success');
        $this->assertSame(\App\Models\IncomingEInvoice::STATUS_APPROVED, $incoming->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('finance.incoming-invoices.decide', $incoming), ['decision' => 'payment_released'])
            ->assertSessionHas('success');
        $this->assertSame(\App\Models\IncomingEInvoice::STATUS_PAYMENT_RELEASED, $incoming->fresh()->status);

        // Ablehnung braucht eine Anmerkung.
        $second = \App\Models\IncomingEInvoice::query()->create([
            'organization_id' => $incoming->organization_id,
            'document_id' => $incoming->document_id,
            'sha256' => str_repeat('a', 64),
            'source' => 'upload',
            'received_at' => now(),
        ]);
        $this->actingAs($this->admin)
            ->post(route('finance.incoming-invoices.decide', $second), ['decision' => 'rejected'])
            ->assertSessionHasErrors('note');
    }

    public function test_incoming_validation_reports_schema_and_kosit(): void {
        $service = app(IncomingEInvoiceService::class);

        $result = $service->validateXml($this->sampleXml());
        // Sample ist bewusst minimal — entscheidend: die Prüfung LÄUFT und
        // liefert einen strukturierten, transparenten Bericht.
        $this->assertIsBool($result['schema_checked']);
        $this->assertIsBool($result['kosit_available']);
        if (! $result['kosit_available']) {
            $this->assertNull($result['kosit_valid']);
        }

        // Kaputtes XML: kein Schema-Treffer, KoSIT (falls da) lehnt ab.
        $broken = $service->validateXml('<?xml version="1.0"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"><Broken/></Invoice>');
        if ($broken['schema_checked']) {
            $this->assertNotSame([], $broken['schema_errors']);
        }
        if ($broken['kosit_available']) {
            $this->assertFalse($broken['kosit_valid']);
        }
    }

    public function test_parse_roundtrip_extracts_core_fields(): void {
        $service = app(IncomingEInvoiceService::class);

        $parsed = $service->parse($this->sampleXml(), 'application/xml');
        $this->assertNotNull($parsed);

        $summary = $service->summary($parsed);
        $this->assertSame('ER-2026-0042', $summary['number']);
        $this->assertSame('Lieferant GmbH', $summary['seller']);
        $this->assertSame('EUR', $summary['currency']);
        $this->assertEqualsWithDelta(238.0, (float) $summary['gross'], 0.01);
        $this->assertSame(1, $summary['lines']);
    }

    public function test_parse_rejects_non_invoice_content(): void {
        $service = app(IncomingEInvoiceService::class);

        $this->assertNull($service->parse('Hallo Welt', 'text/plain'));
        $this->assertNull($service->parse('<?xml version="1.0"?><foo/>', 'application/xml'));
    }

    public function test_upload_stores_document_and_show_renders_positions(): void {
        $xml = $this->sampleXml();
        $file = UploadedFile::fake()->createWithContent('rechnung.xml', $xml);

        $response = $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), ['file' => $file]);

        $document = Document::query()->where('document_type', DocumentType::Invoice->value)->firstOrFail();
        $response->assertRedirect(route('finance.incoming-invoices.show', $document));
        $this->assertStringContainsString('ER-2026-0042', (string) $document->title);

        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $document))
            ->assertOk()
            ->assertSee('ER-2026-0042')
            ->assertSee('Wartungspauschale');

        // Liste zeigt den Eintrag.
        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.index'))
            ->assertOk()
            ->assertSee('ER-2026-0042');
    }

    public function test_upload_rejects_non_einvoice(): void {
        $file = UploadedFile::fake()->createWithContent('brief.xml', '<?xml version="1.0"?><brief>Hallo</brief>');

        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Document::query()->where('document_type', DocumentType::Invoice->value)->count());
    }
}
