<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceRecipientSnapshotTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\{InvoiceStatus, XRechnungSyntax};
use App\Models\Customer\Customer;
use App\Models\DocumentDesign\DocumentRenderSnapshot;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Gaeb\GaebInvoiceExportService;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService, InvoicePdfRenderer};
use ERechnungToolkit\Validators\{KositValidator, ValidationMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1623/1624: Der Empfänger einer ausgestellten Rechnung kommt aus dem beim
 * Ausstellen eingefrorenen Stand — PDF, E-Rechnung und GAEB-Export bleiben nach
 * einer Adressänderung des Kunden, wie sie ausgestellt wurden; der Entwurf zeigt
 * den heutigen Kunden. Das PDF druckt die USt-IdNr. des Kunden (§ 14a UStG).
 */
final class InvoiceRecipientSnapshotTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const array MOVED = [
        'name' => 'Umfirmiert',
        'company' => 'Umfirmiert AG',
        'address_street' => 'Neuer Weg 1',
        'address_zip' => '99999',
        'address_city' => 'Neustadt',
    ];

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Beispieltechnik GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE136695976',
            'tax_number' => '201/987/65432',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@beispieltechnik.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Beispieltechnik GmbH',
            'small_business' => '0',
        ]]]);
        $this->organization->refresh();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_draft_shows_the_current_customer(): void {
        $customer = $this->customer('DE');
        $draft = $this->draft($customer, [['Montage', null, null]]);

        $customer->update(self::MOVED);
        $draft = $draft->refresh();

        $this->assertSame('Neuer Weg 1', $this->viewData($draft)['recipient']['street']);
        $this->assertSame('Umfirmiert AG', $this->viewData($draft)['recipient']['company']);
        $this->assertStringContainsString('Neuer Weg 1', app(InvoicePdfRenderer::class)->composedHtml($draft));
    }

    public function test_reprint_after_an_address_change_shows_the_issued_recipient(): void {
        $customer = $this->customer('DE', company: 'Kunde Beispiel GmbH');
        $invoice = $this->issue($this->draft($customer, [['Montage', null, null]]));

        $customer->update(self::MOVED);
        $invoice = Invoice::query()->findOrFail($invoice->id);
        $html = app(InvoicePdfRenderer::class)->composedHtml($invoice);

        $this->assertStringContainsString('Kundenweg 7', $html);
        $this->assertStringContainsString('Kunde Beispiel GmbH', $html);
        $this->assertStringContainsString('54321 Kundenstadt', $html);
        $this->assertStringNotContainsString('Neuer Weg 1', $html);
        $this->assertStringNotContainsString('Umfirmiert', $html);

        $gaeb = app(GaebInvoiceExportService::class)->export($invoice)['content'];
        $this->assertStringContainsString('Kundenweg 7', $gaeb);
        $this->assertStringNotContainsString('Neuer Weg 1', $gaeb);
    }

    public function test_einvoice_of_an_issued_invoice_ignores_later_master_data_changes(): void {
        $customer = $this->customer('DE', 'DE811569869', 'Kunde Beispiel GmbH');
        $invoice = $this->issue($this->draft($customer, [['Montage', null, null]]));
        $generator = app(XRechnungGenerator::class);
        $ubl = $generator->generate($invoice, XRechnungSyntax::Ubl);
        $cii = $generator->generate($invoice, XRechnungSyntax::Cii);

        $customer->update(self::MOVED + ['vat_id' => 'DE811907980', 'buyer_reference' => '991-99999-78']);
        $invoice = Invoice::query()->findOrFail($invoice->id);

        $this->assertSame($ubl, $generator->generate($invoice, XRechnungSyntax::Ubl));
        $this->assertSame($cii, $generator->generate($invoice, XRechnungSyntax::Cii));
        foreach ([$ubl, $cii] as $xml) {
            $this->assertStringContainsString('Kunde Beispiel GmbH', $xml);
            $this->assertStringContainsString('DE811569869', $xml);
            $this->assertStringContainsString('991-12345-73', $xml);
            $this->assertStringNotContainsString('Neuer Weg 1', $xml);
            $this->assertStringNotContainsString('Umfirmiert', $xml);
            $this->assertStringNotContainsString('DE811907980', $xml);
            $this->assertKositAccepted($xml);
        }
    }

    public function test_buyer_vat_id_is_printed_for_reverse_charge_and_intra_community_supply(): void {
        $label = (string) __('invoicing.buyer_vat_id');

        $reverseCharge = $this->issue($this->draft($this->customer('AT', 'ATU12345675'), [['Fernwartung', null, null]]));
        $this->assertTrue((bool) $reverseCharge->is_reverse_charge);
        $this->assertSame('ATU12345675', $this->viewData($reverseCharge)['buyerVatId']);
        $this->assertStringContainsString($label . ': ATU12345675', app(InvoicePdfRenderer::class)->composedHtml($reverseCharge));

        $intraCommunity = $this->issue($this->draft($this->customer('AT', 'ATU13585627'), [['Steuermodul S7', 'K', '0.00']]));
        $this->assertStringContainsString($label . ': ATU13585627', app(InvoicePdfRenderer::class)->composedHtml($intraCommunity));
    }

    /** Wo vorhanden, steht die USt-IdNr. auch auf der Inlandsrechnung; ohne sie entfällt die Zeile. */
    public function test_buyer_vat_id_follows_the_customer_record_otherwise(): void {
        $domestic = $this->draft($this->customer('DE', 'DE811569869'), [['Montage', null, null]]);
        $this->assertSame('DE811569869', $this->viewData($domestic)['buyerVatId']);

        $private = $this->draft($this->customer('DE'), [['Montage', null, null]]);
        $this->assertNull($this->viewData($private)['buyerVatId']);
        $this->assertStringNotContainsString((string) __('invoicing.buyer_vat_id'), app(InvoicePdfRenderer::class)->composedHtml($private));
    }

    public function test_reprint_of_an_invoice_issued_before_the_buyer_vat_id_stays_unchanged(): void {
        $invoice = $this->issue($this->draft($this->customer('AT', 'ATU12345675'), [['Fernwartung', null, null]]));
        $this->assertSame('ATU12345675', $this->viewData($invoice)['buyerVatId']);

        DocumentRenderSnapshot::query()->withoutGlobalScopes()
            ->where('documentable_type', $invoice->getMorphClass())->where('documentable_id', $invoice->id)
            ->update(['generator_version' => 'document-design/1.2.0']);
        $invoice = Invoice::query()->findOrFail($invoice->id);

        $this->assertNull($this->viewData($invoice)['buyerVatId']);
        $this->assertStringNotContainsString((string) __('invoicing.buyer_vat_id'), app(InvoicePdfRenderer::class)->composedHtml($invoice));
        // Der Empfänger kommt trotzdem aus dem eingefrorenen Stand: Er IST der ausgestellte Beleg.
        $this->assertSame('Kundenweg 7', $this->viewData($invoice)['recipient']['street']);
    }

    /** Snapshots vor MVP-1624 führen Firma und Käuferreferenz nicht; nur diese Felder kommen aus dem heutigen Kunden. */
    public function test_older_snapshot_falls_back_per_field(): void {
        $customer = $this->customer('DE', company: 'Alt GmbH');
        $invoice = $this->issue($this->draft($customer, [['Montage', null, null]]));
        $snapshot = $invoice->party_snapshot;
        $snapshot['buyer'] = ['name' => 'Kunde Beispiel DE', 'street' => 'Kundenweg 7', 'zip' => '54321', 'city' => 'Kundenstadt', 'country' => 'DE', 'vat_id' => '', 'email' => 'buchhaltung@kunde.example'];
        $invoice->forceFill(['party_snapshot' => $snapshot])->saveQuietly();

        $customer->update(self::MOVED + ['buyer_reference' => '991-99999-78']);
        $recipient = Invoice::query()->findOrFail($invoice->id)->recipient();

        $this->assertSame('Kunde Beispiel DE', $recipient['name']);
        $this->assertSame('Kundenweg 7', $recipient['street']);
        $this->assertSame('Umfirmiert AG', $recipient['company']);
        $this->assertSame('991-99999-78', $recipient['buyer_reference']);
    }

    /**
     * Stornorechnung und Gutschrift sind eigene Belege: Sie frieren ihren
     * Empfänger beim eigenen Ausstellen ein (§ 14 Abs. 4 Nr. 1 UStG: Anschrift bei
     * Ausstellung), das Original behält seinen.
     */
    public function test_cancellation_and_credit_note_freeze_their_own_recipient(): void {
        $customer = $this->customer('DE');
        $original = $this->issue($this->draft($customer, [['Montage', null, null]]));
        $paid = $this->issue($this->draft($customer, [['Wartung', null, null]]));
        $paid->forceFill(['status' => InvoiceStatus::Paid, 'paid_on' => now()])->saveQuietly();

        $customer->update(['address_street' => 'Neuer Weg 1']);
        $cancellation = app(InvoiceGenerator::class)->cancellationFor(Invoice::query()->findOrFail($original->id), null, $this->admin->id);
        $credit = app(InvoiceGenerator::class)->creditNoteFor(Invoice::query()->findOrFail($paid->id), $this->admin->id);
        $this->assertSame('Neuer Weg 1', $cancellation->recipient()['street'], 'Entwurf: heutiger Kunde');
        $cancellation = $this->issue($cancellation);
        $credit = $this->issue($credit);

        $customer->update(['address_street' => 'Dritter Weg 3']);

        foreach ([$cancellation, $credit] as $document) {
            $document = Invoice::query()->findOrFail($document->id);
            $this->assertSame('Neuer Weg 1', $document->recipient()['street'], $document->type);
            $this->assertStringContainsString('Neuer Weg 1', app(InvoicePdfRenderer::class)->composedHtml($document));
        }
        foreach ([$original, $paid] as $document) {
            $this->assertSame('Kundenweg 7', Invoice::query()->findOrFail($document->id)->recipient()['street']);
        }
    }

    /**
     * MVP-1681: Hat der Kunde seine USt-IdNr. inzwischen verloren, übernehmen
     * Storno und Gutschrift zu Reverse Charge oder innergemeinschaftlicher
     * Lieferung die USt-IdNr. des Originals — die Prüfung hält nicht mehr an.
     */
    public function test_cancellation_and_credit_note_keep_the_vat_id_of_the_original(): void {
        $customer = $this->customer('AT', 'ATU12345675', 'Kunde Beispiel GmbH');
        $reverseCharge = $this->issue($this->draft($customer, [['Fernwartung', null, null]]));
        $intraCommunity = $this->issue($this->draft($customer, [['Steuermodul S7', 'K', '0.00']]));
        $intraCommunity->forceFill(['status' => InvoiceStatus::Paid, 'paid_on' => now()])->saveQuietly();
        $this->assertTrue((bool) $reverseCharge->is_reverse_charge);

        $customer->update(['vat_id' => null]);
        $cancellation = app(InvoiceGenerator::class)->cancellationFor(Invoice::query()->findOrFail($reverseCharge->id), null, $this->admin->id);
        $credit = app(InvoiceGenerator::class)->creditNoteFor(Invoice::query()->findOrFail($intraCommunity->id), $this->admin->id);
        $this->assertSame('ATU12345675', $cancellation->recipient()['vat_id'], 'Entwurf: USt-IdNr. des Originals');

        $generator = app(XRechnungGenerator::class);
        $vatIdErrors = [(string) __('invoicing.einvoice.error.reverse_charge_buyer_vat_id'), (string) __('invoicing.einvoice.error.intra_community_buyer_vat_id')];
        foreach ([[$cancellation, 'ATU12345675'], [$credit, 'ATU12345675']] as [$document, $vatId]) {
            $document = $this->issue($document);
            $this->assertSame($vatId, data_get($document->party_snapshot, 'buyer.vat_id'), $document->type);
            $this->assertSame([], array_values(array_intersect($vatIdErrors, $generator->preflight($document)['errors'])), $document->type);
            $this->assertStringContainsString($vatId, $generator->generate($document, XRechnungSyntax::Ubl));
            $this->assertStringContainsString((string) __('invoicing.buyer_vat_id') . ': ' . $vatId, app(InvoicePdfRenderer::class)->composedHtml($document));
        }
    }

    /** Ohne Reverse Charge und innergemeinschaftliche Lieferung bleibt es beim heutigen Kunden. */
    public function test_domestic_cancellation_does_not_revive_a_removed_vat_id(): void {
        $customer = $this->customer('DE', 'DE811569869');
        $original = $this->issue($this->draft($customer, [['Montage', null, null]]));

        $customer->update(['vat_id' => null]);
        $cancellation = app(InvoiceGenerator::class)->cancellationFor(Invoice::query()->findOrFail($original->id), null, $this->admin->id);

        $this->assertSame('', $this->issue($cancellation)->recipient()['vat_id']);
    }

    /** @return array<string, mixed> */
    private function viewData(Invoice $invoice): array {
        return app(InvoicePdfRenderer::class)->viewData($invoice->loadMissing(['items', 'customer', 'organization']));
    }

    private function customer(string $country, ?string $vatId = null, ?string $company = null): Customer {
        return Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kunde Beispiel ' . $country,
            'company' => $company,
            'currency' => 'EUR',
            'email' => 'buchhaltung@kunde.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Kundenstadt',
            'country' => $country,
            'vat_id' => $vatId,
            'buyer_reference' => '991-12345-73',
            'created_by' => $this->admin->id,
        ]);
    }

    /** @param list<array{0: string, 1: ?string, 2: ?string}> $lines Beschreibung, Steuerkategorie, Steuersatz */
    private function draft(Customer $customer, array $lines): Invoice {
        $invoice = app(InvoiceGenerator::class)->emptyDraft($customer);
        foreach ($lines as $i => [$description, $category, $rate]) {
            $invoice->items()->create([
                'organization_id' => $this->organization->id,
                'description' => $description,
                'quantity' => '2',
                'unit' => 'Std.',
                'unit_price' => '80.00',
                'tax_category' => $category,
                'tax_rate' => $rate,
                'service_date' => '2026-10-15',
                'position' => $i + 1,
            ]);
        }
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->refresh();
    }

    private function issue(Invoice $invoice): Invoice {
        return app(InvoiceIssueService::class)->issue($invoice)->refresh();
    }

    private function assertKositAccepted(string $xml): void {
        $kosit = new KositValidator;
        if (! $kosit->isAvailable()) {
            $this->markTestSkipped('KoSIT-Validator (Java + JAR) ist nicht verfügbar.');
        }
        $this->assertSame([], array_map(static fn (ValidationMessage $message): string => (string) $message, $kosit->validate($xml)->getErrors()));
    }
}
