<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceSellerIdsRepairTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceMatchKind, IncomingInvoiceRecognition, InvoiceStatus};
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Document\DocumentService;
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingInvoiceMatcher, XRechnungGenerator};
use ERechnungToolkit\Parsers\ERechnungParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1696: Vor php-erechnung-toolkit 0.22 stand bei UBL ohne Steuernummer die
 * Registernummer des Verkäufers (BT-30) als Steuernummer in der
 * Zusammenfassung. `incoming-invoices:repair-seller-ids` liest beide Felder aus
 * dem Original neu — einmal je Eingang, ohne Zuordnungen anzufassen; offene
 * Eingänge bekommen danach den Abgleich erneut.
 */
final class IncomingInvoiceSellerIdsRepairTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string REGISTER = 'HRB 4711, Amtsgericht Musterstadt';

    private User $admin;

    private string $xml;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        $this->xml = $this->supplierXml();
        // Danach empfängt die Organisation die Rechnung als Käuferin.
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => 'DE811907980']]]);
    }

    public function test_command_corrects_the_summary_once_and_offers_the_open_entry_again(): void {
        $this->assertNull((new ERechnungParser)->parse($this->xml)->getSeller()->getTaxRegistrationId(), 'Fixture: UBL ohne Steuernummer');
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Lieferant GmbH', 'vat_id' => 'DE123456789']);
        // Trifft nur über die falsch gelesene Steuernummer: vorher ein Widerspruch, danach nicht mehr.
        Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Anderer Lieferant', 'tax_number' => '4711']);
        $open = $this->legacyEntry('ER-2026-0101');
        $this->assertNull($open->supplier_id);
        $this->assertCount(2, app(IncomingInvoiceMatcher::class)->match($open)['conflict'], 'Vorher: Widerspruch über die falsch gelesene Steuernummer');

        $this->artisan('incoming-invoices:repair-seller-ids', ['--organization' => $this->organization->id, '--dry-run' => true])
            ->expectsOutputToContain('1 geprüft, 1 Steuernummern zu berichtigen (Trockenlauf)')
            ->assertSuccessful();
        $this->assertSame(self::REGISTER, $open->refresh()->summary['seller_tax_number']);

        $this->artisan('incoming-invoices:repair-seller-ids', ['--organization' => $this->organization->id])
            ->expectsOutputToContain('1 geprüft, 1 Steuernummern berichtigt, 1 neu zugeordnet, 0 ohne lesbares Original')
            ->assertSuccessful();

        $open->refresh();
        $this->assertNull($open->summary['seller_tax_number']);
        $this->assertSame(self::REGISTER, $open->summary['seller_register_number']);
        $this->assertSame($supplier->id, $open->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::VatId, $open->match_kind);
        $this->assertSame(1, $open->auditLogs()->where('event', 'incoming_einvoice.seller_ids_corrected')->count());

        // Idempotent: ein zweiter Lauf findet nichts mehr.
        $this->artisan('incoming-invoices:repair-seller-ids', ['--organization' => $this->organization->id])
            ->doesntExpectOutputToContain('geprüft')
            ->assertSuccessful();
        $this->assertSame(1, $open->auditLogs()->where('event', 'incoming_einvoice.seller_ids_corrected')->count());
    }

    public function test_assignments_decisions_and_transfers_stay_untouched(): void {
        $other = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Anderer Lieferant', 'tax_number' => '4711']);
        $assigned = $this->legacyEntry('ER-2026-0102', [
            'supplier_id' => $other->id,
            'match_kind' => IncomingInvoiceMatchKind::TaxNumber,
            'status' => IncomingEInvoiceStatus::Approved,
            'transferred_at' => now(),
        ]);
        $rejected = $this->legacyEntry('ER-2026-0103', ['status' => IncomingEInvoiceStatus::Rejected]);

        $this->artisan('incoming-invoices:repair-seller-ids', ['--organization' => $this->organization->id])
            ->expectsOutputToContain('2 geprüft, 2 Steuernummern berichtigt, 0 neu zugeordnet')
            ->expectsOutputToContain('bitte prüfen: ' . $assigned->sqid)
            ->assertSuccessful();

        $assigned->refresh();
        $this->assertSame($other->id, $assigned->supplier_id);
        $this->assertSame(IncomingEInvoiceStatus::Approved, $assigned->status);
        $this->assertNotNull($assigned->transferred_at);
        $this->assertSame(self::REGISTER, $assigned->summary['seller_register_number']);
        $this->assertNull($assigned->summary['seller_tax_number']);
        $this->assertSame(IncomingEInvoiceStatus::Rejected, $rejected->refresh()->status);
        $this->assertNull($rejected->supplier_id);
    }

    /** Gegenprobe: Eingänge mit neuer Zusammenfassung und nicht strukturierte bleiben, wie sie sind. */
    public function test_current_and_unstructured_entries_are_skipped(): void {
        $current = $this->legacyEntry('ER-2026-0104', [], legacy: false);
        $extracted = $this->legacyEntry('ER-2026-0105', ['recognition' => IncomingInvoiceRecognition::Extracted]);

        $this->assertNull(app(IncomingEInvoiceService::class)->correctSellerIds($current));
        $this->assertNull(app(IncomingEInvoiceService::class)->correctSellerIds($extracted));
        $this->assertSame(self::REGISTER, $extracted->refresh()->summary['seller_tax_number']);
        $this->assertFalse($current->auditLogs()->where('event', 'incoming_einvoice.seller_ids_corrected')->exists());
    }

    /**
     * Strukturierter Eingang mit abgelegtem Original; `legacy` = Zusammenfassung
     * im Stand vor MVP-1686 (Registernummer als Steuernummer, ohne Registerfeld).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function legacyEntry(string $number, array $attributes = [], bool $legacy = true): IncomingEInvoice {
        $xml = str_replace('ER-2026-0100', $number, $this->xml);
        $document = Document::factory()->create([
            'organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice,
            'created_by_user_id' => $this->admin->id, 'title' => 'E-Rechnung ' . $number,
        ]);
        app(DocumentService::class)->addVersionFromContents($document, $this->admin, $xml, $number . '.xml', 'application/xml');

        $service = app(IncomingEInvoiceService::class);
        $summary = $service->summary((new ERechnungParser)->parse($xml)) + ['recognition' => IncomingInvoiceRecognition::Structured->value];
        if ($legacy) {
            $summary['seller_tax_number'] = $summary['seller_register_number'];
            unset($summary['seller_register_number']);
        }

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', $number),
            'source' => 'upload', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'recognition' => IncomingInvoiceRecognition::Structured, 'summary' => $summary,
            ...IncomingEInvoice::columnsFromSummary($summary),
            ...$attributes,
        ]);
    }

    /** UBL eines Lieferanten mit USt-IdNr. und Registerangabe (BT-30), ohne Steuernummer. */
    private function supplierXml(): string {
        $this->organization->update(['settings' => [
            'einvoice' => [
                'seller_name' => 'Lieferant GmbH', 'street' => 'Musterstraße 1', 'zip' => '12345', 'city' => 'Berlin', 'country' => 'DE',
                'vat_id' => 'DE123456789', 'contact_name' => 'Max Muster', 'contact_email' => 'rechnung@lieferant.example', 'contact_phone' => '+49 30 123456',
                'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX', 'account_holder' => 'Lieferant GmbH',
            ],
            'branding' => ['legal' => ['register' => self::REGISTER]],
        ]]);
        $customer = Customer::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Muster GmbH', 'currency' => 'EUR', 'email' => 'einkauf@muster.example',
            'address_street' => 'Kundenweg 7', 'address_zip' => '54321', 'address_city' => 'Hamburg', 'country' => 'DE',
            'buyer_reference' => '991-12345-73', 'created_by' => $this->admin->id,
        ]);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'ER-2026-0100',
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

        return app(XRechnungGenerator::class)->generate($invoice->refresh()->load(['items', 'customer', 'organization']));
    }
}
