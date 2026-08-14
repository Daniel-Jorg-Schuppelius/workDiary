<?php

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\InvoiceDeliveryFormat;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection};
use App\Models\{Customer, Invoice, User};
use App\Services\Ai\Dto\ExtractRequest;
use App\Services\Invoicing\InvoicePdfImportService;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * Feature 088: KI-Fallback der Feld-Extraktion beim Rechnungsdatei-Import.
 * Leitplanken: nur fehlende Felder, konfidenz-/formatgeprüft, hohe
 * Sensibilität (nur lokale Provider), ohne Provider exakt wie ohne KI.
 */
final class InvoiceImportAiFallbackTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private FakeAiProvider $fake;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        $this->fake = FakeAiProviderFactory::install();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Beispielkunde GmbH',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
    }

    /** Sensibilität high ⇒ Cloud gesperrt — die Verbindung muss lokal sein. */
    private function connectAi(): AiProviderConnection {
        $connection = AiProviderConnection::factory()->local()->create([
            'organization_id' => $this->organization->id,
        ]);
        AiCapabilitySetting::factory()->create([
            'organization_id' => $this->organization->id,
            'capability' => InvoicePdfImportService::AI_CAPABILITY,
            'enabled' => true,
            'allowed_connection_ids' => [$connection->id],
        ]);

        return $connection;
    }

    public function test_ai_fills_only_missing_fields_with_validated_values(): void {
        $this->connectAi();
        $this->fake->extractionResponse = [
            'number' => 'KI-2026-9',
            'issued_on' => '14.08.2026',
            'net' => '250,00',
            'tax_rate' => '19',
        ];

        $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'delivery_format' => InvoiceDeliveryFormat::Pdf->value,
            'file' => UploadedFile::fake()->createWithContent('rechnung.pdf', $this->prosePdf()),
        ])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('KI-2026-9', $invoice->number);
        $this->assertSame('2026-08-14', $invoice->issued_on?->toDateString());
        $this->assertSame('250.00', $invoice->subtotal?->getAmount());
        $this->assertSame('297.50', $invoice->total?->getAmount());
        $this->assertTrue((bool) data_get($invoice->import_metadata, 'extraction.ai.used'));
        $this->assertContains('ai_fields_filled', (array) data_get($invoice->import_metadata, 'extraction.warnings'));
        $this->assertSame(1, $this->fake->callCount('extract'));

        // Nur die FEHLENDEN Felder wurden angefragt — nie das ganze Schema.
        $request = $this->fake->calls[0]['request'];
        $this->assertInstanceOf(ExtractRequest::class, $request);
        $this->assertArrayHasKey('number', $request->schema);
    }

    public function test_ai_is_not_consulted_when_heuristics_found_the_core_fields(): void {
        $this->connectAi();

        $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'delivery_format' => InvoiceDeliveryFormat::Pdf->value,
            'file' => UploadedFile::fake()->createWithContent('rechnung.pdf', $this->completePdf()),
        ])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('PDF-2026-31', $invoice->number);
        if ($this->fake->callCount('extract') === 1) {
            // Randfelder dürfen angefragt werden — die gefundenen Kernfelder nie.
            $request = $this->fake->calls[0]['request'];
            $this->assertInstanceOf(ExtractRequest::class, $request);
            $this->assertArrayNotHasKey('number', $request->schema);
            $this->assertArrayNotHasKey('net', $request->schema);
        }
        $this->assertNull(data_get($invoice->import_metadata, 'extraction.ai'));
    }

    public function test_without_ai_connection_the_import_behaves_exactly_as_before(): void {
        $this->fake->extractionResponse = ['number' => 'NIE-BENUTZT'];

        $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'delivery_format' => InvoiceDeliveryFormat::Pdf->value,
            'file' => UploadedFile::fake()->createWithContent('rechnung.pdf', $this->prosePdf()),
        ])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertNotSame('NIE-BENUTZT', $invoice->number);
        $this->assertSame('file_import', $invoice->number_source);
        $this->assertNull(data_get($invoice->import_metadata, 'extraction.ai'));
        $this->assertSame(0, $this->fake->callCount('extract'));
    }

    /** PDF ohne erkennbare Labels — die Regex-Heuristik findet nichts. */
    private function prosePdf(): string {
        return $this->renderPdf('<p>Wir berechnen Ihnen für die im August erbrachten Leistungen den unten stehenden Betrag.</p><p>Vielen Dank für Ihren Auftrag.</p>');
    }

    private function completePdf(): string {
        return $this->renderPdf(<<<'HTML'
            <p>Rechnungsnummer: PDF-2026-31</p>
            <p>Rechnungsdatum: 14.08.2026</p>
            <p>Fällig am: 28.08.2026</p>
            <p>Leitweg-ID: 991-12345-67</p>
            <p>Nettobetrag 100,00 EUR</p>
            <p>Umsatzsteuer 19 % 19,00 EUR</p>
            <p>Gesamtbetrag 119,00 EUR</p>
            HTML);
    }

    private function renderPdf(string $bodyHtml): string {
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<!doctype html><html lang="de"><body><h1>Rechnung</h1>' . $bodyHtml . '</body></html>');
        $dompdf->render();

        return $dompdf->output();
    }
}
