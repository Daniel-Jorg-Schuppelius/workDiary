<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceWorklistTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceMatchKind, IncomingInvoiceRecognition};
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, InvoiceSenderRule};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Notifications\Finance\IncomingInvoicesDigestNotification;
use App\Support\Crypto\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Notification};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Feature 163, MVP-1110: Arbeitsliste und manuelle Zuordnung im Rechnungseingang. */
final class IncomingInvoiceWorklistTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => 'DE811907980']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_tabs_split_open_assignment_from_review_and_the_menu_counts_open_ones(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH']);
        $this->incoming(['invoice_number' => 'OPEN-1']);
        $this->incoming(['invoice_number' => 'DONE-1', 'supplier_id' => $supplier->id]);
        $this->incoming(['invoice_number' => 'GONE-1', 'status' => IncomingEInvoiceStatus::Rejected]);

        $this->get(route('finance.incoming-invoices.index'))
            ->assertOk()->assertSee('OPEN-1')->assertDontSee('DONE-1')->assertDontSee('GONE-1')
            ->assertSee(__('Rechnungseingang'));
        $this->get(route('finance.incoming-invoices.index', ['tab' => 'review']))
            ->assertOk()->assertSee('DONE-1')->assertDontSee('OPEN-1');
    }

    public function test_assigning_an_existing_supplier_can_remember_the_sender(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH']);
        $incoming = $this->incoming(['sender_email' => 'rechnung@pinsel.example']);

        $this->get(route('finance.incoming-invoices.assign.form', $incoming))->assertOk()->assertSee('Pinsel GmbH');
        $this->post(route('finance.incoming-invoices.assign', $incoming), [
            'direction' => 'incoming', 'mode' => 'existing', 'party' => 'supplier:' . $supplier->sqid, 'remember_sender' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $incoming->refresh();
        $this->assertSame($supplier->id, $incoming->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::Manual, $incoming->match_kind);
        $this->assertSame($this->admin->id, $incoming->matched_user_id);
        $this->assertTrue(InvoiceSenderRule::query()->where('email', 'rechnung@pinsel.example')->where('supplier_id', $supplier->id)->exists());
    }

    public function test_a_new_supplier_is_created_from_the_document_data(): void {
        $incoming = $this->incoming(['seller_name' => 'Neu & Anders GmbH', 'seller_vat_id' => 'DE123123123']);

        $this->post(route('finance.incoming-invoices.assign', $incoming), [
            'direction' => 'incoming', 'mode' => 'new', 'new_name' => 'Neu & Anders GmbH', 'new_vat_id' => 'DE123123123',
            'new_email' => 'buchhaltung@neu-anders.example', 'new_street' => 'Hauptstraße 5', 'new_zip' => '10115',
            'new_city' => 'Berlin', 'new_country' => 'de', 'new_iban' => 'DE89 3704 0044 0532 0130 00',
        ])->assertRedirect()->assertSessionHas('success');

        $supplier = Supplier::query()->where('name', 'Neu & Anders GmbH')->sole();
        $this->assertSame($supplier->id, $incoming->refresh()->supplier_id);
        $this->assertSame('DE123123123', $supplier->vat_id);
        $this->assertSame(BlindIndex::ofIban('DE89370400440532013000'), $supplier->bankAccounts()->sole()->iban_hash);
    }

    public function test_collective_supplier_no_invoice_and_direction_correction(): void {
        $collectiveCase = $this->incoming(['seller_name' => 'Baumarkt Filiale 12']);
        $this->post(route('finance.incoming-invoices.assign', $collectiveCase), ['direction' => 'incoming', 'mode' => 'collective'])->assertSessionHas('success');
        $this->assertTrue((bool) $collectiveCase->refresh()->supplier?->is_collective);

        $spam = $this->incoming([]);
        $this->post(route('finance.incoming-invoices.assign', $spam), ['direction' => 'incoming', 'mode' => 'not_invoice', 'note' => 'Werbung'])->assertSessionHas('success');
        $this->assertSame(IncomingEInvoiceStatus::Rejected, $spam->refresh()->status);
        $this->assertSame('Werbung', $spam->decision_note);

        $customer = Customer::query()->create(['organization_id' => $this->organization->id, 'name' => 'Shop-Kunde AG']);
        $copy = $this->incoming(['buyer_name' => 'Shop-Kunde AG']);
        $this->post(route('finance.incoming-invoices.assign', $copy), ['direction' => 'outgoing', 'mode' => 'existing', 'party' => 'customer:' . $customer->sqid])->assertSessionHas('success');
        $copy->refresh();
        $this->assertSame(DocumentDirection::Outgoing, $copy->direction);
        $this->assertSame($customer->id, $copy->customer_id);

        // Art und Richtung passen nicht zusammen: kein Lieferant an einem Ausgangsbeleg.
        $mismatch = $this->incoming([]);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->post(route('finance.incoming-invoices.assign', $mismatch), ['direction' => 'outgoing', 'mode' => 'existing', 'party' => 'supplier:' . $supplier->sqid])->assertSessionHas('error');
        $this->assertNull($mismatch->refresh()->supplier_id);
    }

    public function test_values_of_a_clarification_case_are_recorded_and_then_matched(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'vat_id' => 'DE456456456']);
        $incoming = $this->incoming(['recognition' => IncomingInvoiceRecognition::None, 'invoice_number' => null, 'amount_gross' => null]);

        $this->get(route('finance.incoming-invoices.values.form', $incoming))->assertOk();
        $this->post(route('finance.incoming-invoices.values', $incoming), [
            'invoice_number' => 'SC-77', 'issue_date' => '2026-09-30', 'currency' => 'eur', 'amount_gross' => '59.50',
            'amount_net' => '50.00', 'amount_tax' => '9.50', 'party_name' => 'Scan GmbH', 'party_vat_id' => 'DE456456456',
        ])->assertRedirect()->assertSessionHas('success');

        $incoming->refresh();
        $this->assertSame('SC-77', $incoming->invoice_number);
        $this->assertSame('59.50', $incoming->amount_gross?->getAmount());
        $this->assertSame($supplier->id, $incoming->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::VatId, $incoming->match_kind);
    }

    public function test_bulk_assignment_skips_documents_of_the_other_direction(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH']);
        $a = $this->incoming([]);
        $b = $this->incoming([]);
        $outgoing = $this->incoming(['direction' => DocumentDirection::Outgoing]);

        $this->post(route('finance.incoming-invoices.bulk-assign'), [
            'ids' => [$a->sqid, $b->sqid, $outgoing->sqid], 'party' => 'supplier:' . $supplier->sqid,
        ])->assertRedirect()->assertSessionHas('warning');

        $this->assertSame($supplier->id, $a->refresh()->supplier_id);
        $this->assertSame($supplier->id, $b->refresh()->supplier_id);
        $this->assertNull($outgoing->refresh()->customer_id);
    }

    public function test_digest_notifies_accounting_only_while_something_is_open(): void {
        Notification::fake();
        $this->assertSame(0, Artisan::call('incoming-invoices:digest'));
        Notification::assertNothingSent();

        $this->incoming(['recognition' => IncomingInvoiceRecognition::None]);
        $this->assertSame(0, Artisan::call('incoming-invoices:digest'));
        Notification::assertSentTo($this->admin, IncomingInvoicesDigestNotification::class, static fn (IncomingInvoicesDigestNotification $n): bool => $n->openCount === 1 && $n->unrecognizedCount === 1);
    }

    /** @param  array<string, mixed>  $attributes */
    private function incoming(array $attributes): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice, 'created_by_user_id' => $this->admin->id]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'mail', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'recognition' => IncomingInvoiceRecognition::Extracted,
            'invoice_number' => 'RE-' . uniqid(), 'seller_name' => 'Irgendwer', 'currency' => 'EUR', 'amount_gross' => '119.00',
            'summary' => [],
            ...$attributes,
        ]);
    }
}
