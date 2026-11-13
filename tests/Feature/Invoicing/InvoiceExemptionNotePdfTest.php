<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceExemptionNotePdfTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Finance\TaxRuleStatus;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\DocumentDesign\DocumentRenderSnapshot;
use App\Models\Finance\TaxRule;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService, InvoicePdfRenderer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1623: Das Rechnungs-PDF druckt bei Positionen ohne Steuer den
 * Befreiungshinweis der E-Rechnung (§ 14 Abs. 4 Nr. 8, § 14a UStG), sofern der
 * Rechnungstext ihn nicht trägt; ausgestellte Altbelege bleiben beim Nachdruck,
 * wie sie waren. Gutschrift und Storno übernehmen die Kategorie des Originals.
 */
final class InvoiceExemptionNotePdfTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seller();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_draft_prints_the_note_of_each_category_without_tax(): void {
        $invoice = $this->draft($this->customer('DE'), [['Montage', null, null], ['Steuermodul S7', 'K', '0.00'], ['Beratung Standort Zürich', 'O', '0.00']]);

        $notes = $this->taxNotes($invoice);

        $this->assertSame([(string) __('invoicing.einvoice.exemption_intra_community'), (string) __('invoicing.einvoice.exemption_not_subject')], $notes);
        $html = app(InvoicePdfRenderer::class)->composedHtml($invoice);
        $this->assertStringContainsString(e((string) __('invoicing.einvoice.exemption_intra_community')), $html);
    }

    public function test_a_note_already_in_the_invoice_text_is_not_repeated(): void {
        $invoice = $this->draft($this->customer('DE'), [['Steuermodul S7', 'K', '0.00']]);
        $invoice->update(['notes' => "Vielen Dank für Ihren Auftrag.\nSteuerfreie innergemeinschaftliche Lieferung (§4 Nr. 1 Buchstabe b i.V.m. §6a UStG)"]);

        $this->assertSame([], $this->taxNotes($invoice->refresh()));
    }

    /** Der automatische Hinweis des Resolvers zählt für seine Kategorie, auch wenn er anders lautet als der Befreiungsgrund. */
    public function test_the_automatic_note_of_the_category_counts_as_present(): void {
        $customer = $this->customer('CH');
        $withNote = app(InvoiceIssueService::class)->issue($this->draft($customer, [['Wartung Steuerungsanlage', null, null]]))->refresh();
        // Leistung ins Drittland: nicht im Inland steuerbar (MVP-1684).
        $this->assertSame('O', data_get($withNote->tax_context, 'category'));
        $this->assertStringContainsString((string) config('taxation.notes.export'), (string) $withNote->notes);

        $this->assertSame([], $this->taxNotes($withNote));

        $draft = $this->draft($customer, [['Wartung Steuerungsanlage', null, null]]);
        $draft->update(['notes' => null]);
        $withoutNote = app(InvoiceIssueService::class)->issue($draft->refresh())->refresh();
        $this->assertSame([(string) __('invoicing.einvoice.exemption_not_subject')], $this->taxNotes($withoutNote));
    }

    public function test_reverse_charge_and_small_business_keep_their_own_line(): void {
        $reverseCharge = $this->draft($this->customer('AT', 'ATU12345675'), [['Fernwartung', null, null]]);
        $this->assertTrue((bool) $reverseCharge->is_reverse_charge);
        $reverseCharge->update(['notes' => null]);

        $this->assertSame([], $this->taxNotes($reverseCharge->refresh()));
        $this->assertSame(1, substr_count(app(InvoicePdfRenderer::class)->composedHtml($reverseCharge), 'Steuerschuldnerschaft des Leistungsempfängers'));

        $this->seller(smallBusiness: true);
        $smallBusiness = $this->draft($this->customer('DE'), [['Reparatur', null, null]]);
        $smallBusiness->update(['notes' => null]);
        $this->assertSame([], $this->taxNotes($smallBusiness->refresh()));
        $this->assertStringContainsString('§ 19 UStG', app(InvoicePdfRenderer::class)->composedHtml($smallBusiness));
    }

    /** AE an einer Position ohne Reverse Charge im Kopf: § 14a Abs. 5 UStG verlangt den Hinweis trotzdem. */
    public function test_reverse_charge_on_a_single_line_prints_the_statutory_wording(): void {
        $invoice = $this->draft($this->customer('DE'), [['Montage', null, null], ['Bauleistung Halle 3', 'AE', '0.00']]);

        $this->assertSame([(string) __('invoicing.einvoice.exemption_reverse_charge')], $this->taxNotes($invoice));
    }

    public function test_tax_exempt_lines_print_the_note_of_the_exempt_tax_rule(): void {
        TaxRule::query()->create([
            'organization_id' => $this->organization->id,
            'country' => 'DE',
            'category' => 'services',
            'rate_type' => 'exempt',
            'rate' => '0.00',
            'valid_from' => '2026-01-01',
            'note' => 'Steuerfreie Heilbehandlung (§ 4 Nr. 14 Buchst. a UStG)',
            'status' => TaxRuleStatus::Active,
        ]);
        $invoice = $this->draft($this->customer('DE'), [['Physiotherapie', 'E', '0.00']]);

        $this->assertSame(['Steuerfreie Heilbehandlung (§ 4 Nr. 14 Buchst. a UStG)'], $this->taxNotes($invoice));
    }

    public function test_reprint_of_an_invoice_issued_before_the_notes_stays_unchanged(): void {
        $invoice = $this->draft($this->customer('DE'), [['Steuermodul S7', 'K', '0.00']]);
        $invoice = app(InvoiceIssueService::class)->issue($invoice)->refresh();
        $this->assertSame([(string) __('invoicing.einvoice.exemption_intra_community')], $this->taxNotes($invoice));

        DocumentRenderSnapshot::query()->withoutGlobalScopes()
            ->where('documentable_type', $invoice->getMorphClass())->where('documentable_id', $invoice->id)
            ->update(['generator_version' => 'document-design/1.1.0']);

        $this->assertSame([], $this->taxNotes($invoice->refresh()));
        $this->assertStringNotContainsString(e((string) __('invoicing.einvoice.exemption_intra_community')), app(InvoicePdfRenderer::class)->composedHtml($invoice));
    }

    public function test_credit_note_and_cancellation_carry_the_category_of_the_original(): void {
        $original = app(InvoiceIssueService::class)->issue($this->draft($this->customer('DE'), [['Steuermodul S7', 'K', '0.00'], ['Montage', null, null]]))->refresh();
        $reason = (string) __('invoicing.einvoice.exemption_intra_community');

        $cancellation = app(InvoiceGenerator::class)->cancellationFor($original, null, $this->admin->id);
        $this->assertSame(['K', 'S'], $cancellation->items()->orderBy('position')->pluck('tax_category')->all());
        $this->assertSame([$reason], $this->taxNotes($cancellation));

        $paid = app(InvoiceIssueService::class)->issue($this->draft($this->customer('CH'), [['Wartung Steuerungsanlage', null, null]]))->refresh();
        $paid->forceFill(['status' => InvoiceStatus::Paid, 'paid_on' => now()])->saveQuietly();
        $credit = app(InvoiceGenerator::class)->creditNoteFor($paid->refresh(), $this->admin->id);

        // Ohne Positionskategorie gilt die eingefrorene Kategorie des Originals (Drittland-Leistung: O).
        $this->assertSame(['O'], $credit->items()->pluck('tax_category')->all());
        $this->assertSame([(string) __('invoicing.einvoice.exemption_not_subject')], $this->taxNotes($credit));
    }

    /** @return list<string> */
    private function taxNotes(Invoice $invoice): array {
        return app(InvoicePdfRenderer::class)->viewData($invoice->loadMissing(['items', 'customer', 'organization']))['taxNotes'];
    }

    private function seller(bool $smallBusiness = false): void {
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Beispieltechnik GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => $smallBusiness ? '' : 'DE136695976',
            'tax_number' => '201/987/65432',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'small_business' => $smallBusiness ? '1' : '0',
        ]]]);
        $this->organization->refresh();
    }

    private function customer(string $country, ?string $vatId = null): Customer {
        return Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kunde Beispiel ' . $country,
            'currency' => 'EUR',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Kundenstadt',
            'country' => $country,
            'vat_id' => $vatId,
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
}
