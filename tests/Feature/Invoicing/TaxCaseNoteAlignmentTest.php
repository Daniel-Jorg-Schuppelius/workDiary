<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxCaseNoteAlignmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\{InvoiceStatus, TaxCaseAlignment};
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Invoicing\{InvoiceIssueException, InvoiceIssueService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1623: Wechselt der Steuerfall eines Entwurfs, zieht der Abgleich den
 * Hinweistext des TaxResolvers mit — solange er unverändert dasteht. Einen
 * von Hand geänderten Text überschreibt er nicht, sondern meldet ihn.
 */
final class TaxCaseNoteAlignmentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Bauträger Beispiel', 'currency' => 'EUR', 'country' => 'DE', 'vat_id' => null]);
    }

    public function test_untouched_reverse_charge_note_leaves_with_the_reverse_charge(): void {
        $this->customer->update(['is_reverse_charge_recipient' => true]);
        $invoice = $this->draft();
        $domestic = (string) config('taxation.notes.reverse_charge_domestic');
        $this->assertSame($domestic, $invoice->notes);
        $invoice->update(['notes' => "Bauvorhaben Musterweg 5\n" . $domestic . "\nVielen Dank für den Auftrag."]);

        $this->customer->update(['is_reverse_charge_recipient' => false]);
        $this->addItem($invoice)->assertSessionMissing('warning');

        $invoice->refresh();
        $this->assertFalse($invoice->is_reverse_charge);
        $this->assertSame("Bauvorhaben Musterweg 5\nVielen Dank für den Auftrag.", $invoice->notes);
    }

    public function test_untouched_note_is_replaced_by_the_note_of_the_new_case(): void {
        $this->customer->update(['country' => 'AT', 'vat_id' => 'ATU12345675']);
        $invoice = $this->draft();
        $this->assertSame((string) config('taxation.notes.reverse_charge'), $invoice->notes);
        $invoice->update(['notes' => "Projekt Lager Linz\n" . config('taxation.notes.reverse_charge')]);

        // Der Kunde zieht ins Drittland: aus Reverse Charge wird die Ausfuhr.
        $this->customer->update(['country' => 'CH', 'vat_id' => null]);
        $this->addItem($invoice);
        $invoice->refresh();
        $this->assertFalse($invoice->is_reverse_charge);
        $this->assertSame("Projekt Lager Linz\n" . config('taxation.notes.export'), $invoice->notes);
    }

    public function test_standard_draft_gets_the_note_when_reverse_charge_starts(): void {
        $invoice = $this->draft();
        $this->assertNull($invoice->notes);

        $this->customer->update(['is_reverse_charge_recipient' => true]);
        $this->addItem($invoice);

        $invoice->refresh();
        $this->assertTrue($invoice->is_reverse_charge);
        $this->assertSame((string) config('taxation.notes.reverse_charge_domestic'), $invoice->notes);
    }

    public function test_hand_edited_note_is_kept_and_the_user_is_told(): void {
        $this->customer->update(['is_reverse_charge_recipient' => true]);
        $invoice = $this->draft();
        $invoice->update(['notes' => 'Steuerschuldnerschaft des Leistungsempfängers nach § 13b Abs. 2 Nr. 4 UStG (Bauleistung).']);

        $this->customer->update(['is_reverse_charge_recipient' => false]);
        $this->addItem($invoice)->assertSessionHas('warning', (string) __('invoicing.tax_case.note_kept'));

        $invoice->refresh();
        $this->assertFalse($invoice->is_reverse_charge);
        $this->assertSame('Steuerschuldnerschaft des Leistungsempfängers nach § 13b Abs. 2 Nr. 4 UStG (Bauleistung).', $invoice->notes);
    }

    public function test_issuing_with_a_hand_edited_note_names_the_note_in_the_stop(): void {
        $this->customer->update(['is_reverse_charge_recipient' => true]);
        $invoice = $this->draft();
        $this->addItem($invoice);
        $invoice->refresh()->update(['notes' => 'Reverse Charge laut Bauvertrag.']);
        $this->customer->update(['is_reverse_charge_recipient' => false]);

        try {
            app(InvoiceIssueService::class)->issue($invoice->refresh());
            $this->fail('Ein veralteter Steuerfall darf nicht unbemerkt ausgestellt werden.');
        } catch (InvoiceIssueException $e) {
            $this->assertSame(InvoiceIssueException::REASON_TAX_CASE_CHANGED, $e->reason);
            $this->assertStringContainsString((string) __('invoicing.tax_case.note_kept'), $e->getMessage());
        }
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('Reverse Charge laut Bauvertrag.', $invoice->notes);
    }

    public function test_unchanged_case_reports_unchanged(): void {
        $invoice = $this->draft();
        $invoice->load(['items', 'customer', 'organization']);

        $this->assertSame(TaxCaseAlignment::Unchanged, app(InvoiceIssueService::class)->alignTaxCase($invoice));
    }

    private function draft(): Invoice {
        $this->actingAs($this->admin)->post(route('invoices.store'), ['customer_id' => $this->customer->sqid, 'content' => 'manual', 'payment_terms_days' => 14]);

        return Invoice::query()->latest('id')->firstOrFail();
    }

    private function addItem(Invoice $invoice): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->admin)
            ->post(route('invoices.items.store', $invoice), ['description' => 'Montage', 'quantity' => '1', 'unit' => 'pausch.', 'unit_price' => '100.00'])
            ->assertRedirect();
    }
}
