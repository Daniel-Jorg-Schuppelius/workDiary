<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceMatchingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceMatchKind, InvoiceStatus};
use App\Events\Invoicing\IncomingInvoiceAssigned;
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, Invoice, InvoiceSenderRule};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Billing\Sepa\PaymentProposalService;
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingInvoiceMatcher, XRechnungGenerator};
use App\Support\Crypto\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Event};
use RuntimeException;
use Tests\Concerns\{GeneratesIncomingEInvoices, WithOrganization};
use Tests\TestCase;

/** Feature 163, MVP-1108: Gegenpartei-Abgleich im Rechnungseingang. */
final class IncomingInvoiceMatchingTest extends TestCase {
    use GeneratesIncomingEInvoices;
    use RefreshDatabase;
    use WithOrganization;

    private const SELLER_VAT = 'DE123456789';

    private const SELLER_IBAN = 'DE89370400440532013000';

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
            'vat_id' => self::SELLER_VAT,
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@lieferant.example',
            'contact_phone' => '+49 30 123456',
            'iban' => self::SELLER_IBAN,
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Lieferant GmbH',
            'payment_terms_days' => 14,
        ]]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_exact_vat_id_assigns_the_supplier_and_announces_it(): void {
        Event::fake([IncomingInvoiceAssigned::class]);
        $supplier = $this->supplier('Lieferant GmbH', vat: 'de 123 456 789');

        $incoming = $this->receive('ER-1');

        $this->assertSame($supplier->id, $incoming->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::VatId, $incoming->match_kind);
        $this->assertNull($incoming->matched_user_id);
        Event::assertDispatched(IncomingInvoiceAssigned::class, static fn (IncomingInvoiceAssigned $event): bool => $event->incoming->is($incoming));
    }

    public function test_iban_assigns_when_no_supplier_carries_the_vat_id(): void {
        $supplier = $this->supplier('Lieferant (alt angelegt)', iban: 'DE89 3704 0044 0532 0130 00');

        $incoming = $this->receive('ER-2');

        $this->assertSame($supplier->id, $incoming->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::Iban, $incoming->match_kind);
    }

    public function test_conflicting_exact_matches_stay_open_with_a_deviation(): void {
        $this->supplier('Alpha GmbH', vat: self::SELLER_VAT);
        $this->supplier('Beta GmbH', iban: self::SELLER_IBAN);

        $incoming = $this->receive('ER-3');

        $this->assertNull($incoming->supplier_id);
        $this->assertContains(
            __('Widersprüchliche Treffer: :parties — bitte von Hand zuordnen.', ['parties' => 'Alpha GmbH, Beta GmbH']),
            (array) data_get($incoming->summary, 'deviations'),
        );
    }

    public function test_an_own_identifier_on_a_foreign_supplier_never_matches(): void {
        // Verschmutzter Stammsatz: fremde Firma mit der eigenen USt-IdNr.
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => self::BUYER_VAT_ID]]]);
        $this->supplier('Finanzamt (verschmutzt)', vat: self::BUYER_VAT_ID);
        $incoming = $this->row(['seller_vat_id' => self::BUYER_VAT_ID]);

        $this->assertFalse(app(IncomingInvoiceMatcher::class)->autoAssign($incoming));
        $this->assertNull($incoming->fresh()?->supplier_id);
    }

    public function test_sender_rule_applies_only_without_a_document_match_and_without_contradiction(): void {
        $supplier = $this->supplier('Malerbetrieb Pinsel', vat: 'DE111111111');
        InvoiceSenderRule::query()->create([
            'organization_id' => $this->organization->id,
            'email' => 'rechnung@pinsel.example',
            'direction' => DocumentDirection::Incoming,
            'supplier_id' => $supplier->id,
            'created_by' => $this->admin->id,
        ]);

        $withoutVat = $this->row(['sender_email' => 'Rechnung@Pinsel.example']);
        $this->assertTrue(app(IncomingInvoiceMatcher::class)->autoAssign($withoutVat));
        $this->assertSame(IncomingInvoiceMatchKind::SenderRule, $withoutVat->fresh()?->match_kind);

        // Plattform-Absender: der Beleg nennt eine andere USt-IdNr. als die Regelpartei.
        $foreignVat = $this->row(['sender_email' => 'rechnung@pinsel.example', 'seller_vat_id' => 'DE222222222']);
        $this->assertFalse(app(IncomingInvoiceMatcher::class)->autoAssign($foreignVat));
    }

    public function test_copy_of_an_own_invoice_is_assigned_to_the_customer_by_buyer_vat(): void {
        $customer = Customer::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'ACME GmbH', 'currency' => 'EUR',
            'email' => 'buchhaltung@acme.example', 'address_street' => 'Kundenweg 7', 'address_zip' => '54321',
            'address_city' => 'Hamburg', 'country' => 'DE', 'vat_id' => 'DE277777770', 'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);
        $invoice = $this->localInvoice('RE-2026-0100', $customer);
        $xml = app(XRechnungGenerator::class)->generate($invoice->refresh()->load(['items', 'customer']));

        $incoming = app(IncomingEInvoiceService::class)->storeIncoming($this->admin, $xml, 'application/xml', source: 'mail')['incoming'];

        $this->assertSame(DocumentDirection::Outgoing, $incoming?->direction);
        $this->assertSame($customer->id, $incoming->customer_id);
        $this->assertNull($incoming->supplier_id);
    }

    public function test_iban_check_against_the_supplier_uses_every_stored_account(): void {
        $supplier = $this->supplier('Lieferant GmbH', vat: self::SELLER_VAT, iban: 'DE02120300000000202051');
        $supplier->bankAccounts()->create(['organization_id' => $this->organization->id, 'iban' => self::SELLER_IBAN, 'is_primary' => false]);
        $incoming = $this->receive('ER-4');
        $proposals = app(PaymentProposalService::class);

        $this->assertSame($supplier->id, $incoming->supplier_id);
        $this->assertFalse($proposals->ibanDiffersFromMaster($incoming, $supplier));

        $incoming->forceFill(['creditor_iban' => 'DE75512108001245126199'])->save();
        $this->assertTrue($proposals->ibanDiffersFromMaster($incoming->refresh(), $supplier));
    }

    public function test_command_assigns_existing_incomings_and_keeps_transferred_ones(): void {
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => self::BUYER_VAT_ID]]]);
        $supplier = $this->supplier('Lieferant GmbH', vat: self::SELLER_VAT);
        $open = $this->row(['seller_vat_id' => self::SELLER_VAT]);
        $transferred = $this->row(['seller_vat_id' => self::SELLER_VAT, 'transferred_at' => now()]);

        $this->assertSame(0, Artisan::call('incoming-invoices:match'));

        $this->assertSame($supplier->id, $open->fresh()?->supplier_id);
        $this->assertNull($transferred->fresh()?->supplier_id);
        $this->expectException(RuntimeException::class);
        app(IncomingInvoiceMatcher::class)->assign($transferred, $supplier, IncomingInvoiceMatchKind::Manual, $this->admin);
    }

    public function test_bank_accounts_get_a_searchable_iban_index_and_deleted_suppliers_release_the_incoming(): void {
        $supplier = $this->supplier('Lieferant GmbH', vat: self::SELLER_VAT, iban: 'DE89 3704 0044 0532 0130 00');
        $this->assertSame(BlindIndex::ofIban(self::SELLER_IBAN), $supplier->bankAccounts()->sole()->iban_hash);

        $incoming = $this->receive('ER-5');
        $this->assertSame($supplier->id, $incoming->supplier_id);
        $supplier->bankAccounts()->delete();
        $supplier->delete();

        $this->assertNull($incoming->fresh()?->supplier_id);
    }

    private function receive(string $number): IncomingEInvoice {
        $customer = Customer::query()->firstOrCreate(['organization_id' => $this->organization->id, 'name' => 'Muster GmbH'], [
            'currency' => 'EUR', 'email' => 'einkauf@muster.example', 'address_street' => 'Kundenweg 7',
            'address_zip' => '54321', 'address_city' => 'Hamburg', 'country' => 'DE', 'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);
        $xml = $this->incomingXml($this->localInvoice($number, $customer));
        $incoming = app(IncomingEInvoiceService::class)->storeIncoming($this->admin, $xml, 'application/xml', source: 'mail')['incoming'];
        $this->assertNotNull($incoming);

        return $incoming->refresh();
    }

    /** @param  array<string, mixed>  $attributes */
    private function row(array $attributes): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'mail', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'invoice_number' => 'RE-' . uniqid(), 'seller_name' => 'Irgendwer', 'currency' => 'EUR', 'amount_gross' => '119.00',
            'summary' => [],
            ...$attributes,
        ]);
    }

    private function supplier(string $name, ?string $vat = null, ?string $iban = null): Supplier {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => $name, 'vat_id' => $vat]);
        if ($iban !== null) {
            $supplier->bankAccounts()->create(['organization_id' => $this->organization->id, 'iban' => $iban, 'is_primary' => true]);
        }

        return $supplier;
    }

    private function localInvoice(string $number, Customer $customer): Invoice {
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
}
