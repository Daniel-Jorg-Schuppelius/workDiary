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
use App\Models\Automation\AutomationRule;
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, InvoiceSenderRule};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Notifications\Finance\IncomingInvoicesDigestNotification;
use App\Services\Invoicing\Automation\{AssignIncomingInvoiceAction, IncomingInvoiceReceivedTrigger, RejectIncomingInvoiceAction};
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
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
        $rule = InvoiceSenderRule::query()->where('email', 'rechnung@pinsel.example')->where('supplier_id', $supplier->id)->sole();

        // Regelseite (MVP-1208) zeigt die Absenderregel und löscht sie.
        $this->get(route('finance.incoming-invoices.rules'))->assertOk()->assertSee('rechnung@pinsel.example')->assertSee('Pinsel GmbH');
        $this->delete(route('finance.incoming-invoices.rules.sender.destroy', $rule))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('invoice_sender_rules', ['id' => $rule->id]);
    }

    public function test_rejecting_as_no_invoice_can_create_an_inbox_rule_and_file_the_other_open_documents(): void {
        $first = $this->incoming(['seller_name' => 'Beispiel-Konverter.de']);
        $second = $this->incoming(['seller_name' => ' beispiel-konverter.de']);
        $rejected = $this->incoming(['seller_name' => 'Beispiel-Konverter.de', 'status' => IncomingEInvoiceStatus::Rejected]);
        $other = $this->incoming(['seller_name' => 'Pinsel GmbH']);

        $this->get(route('finance.incoming-invoices.assign.form', $first))->assertOk()->assertSee('Beispiel-Konverter.de');
        $this->post(route('finance.incoming-invoices.assign', $first), [
            'direction' => 'incoming', 'mode' => 'not_invoice', 'note' => 'Testnachricht', 'exclude_action' => 'reject', 'apply_to_open' => '1',
        ])->assertRedirect()->assertSessionHas('success', static fn (string $message): bool => str_contains($message, '1 weiterer offener Eingang von „Beispiel-Konverter.de“'));

        $this->assertSame(IncomingEInvoiceStatus::Rejected, $first->refresh()->status);
        $this->assertSame(IncomingEInvoiceStatus::Rejected, $second->refresh()->status);
        $this->assertSame('Testnachricht', $second->decision_note);
        $this->assertSame($this->admin->id, $second->decided_by);
        $this->assertSame(IncomingEInvoiceStatus::Received, $other->refresh()->status);
        $this->assertNull($rejected->refresh()->decision_note);

        $rule = AutomationRule::query()->sole();
        $this->assertSame(IncomingInvoiceReceivedTrigger::KEY, $rule->trigger_event);
        $this->assertSame('beispiel-konverter.de', data_get($rule->conditions, 'all.0.value'));
        $this->assertSame(RejectIncomingInvoiceAction::TYPE, data_get($rule->actions, '0.type'));

        // Neue Eingänge und die Nacherkennung laufen durch dieselbe Regel.
        $late = $this->incoming(['seller_name' => 'BEISPIEL-KONVERTER.DE']);
        $this->assertTrue(app(IncomingEInvoiceService::class)->applyRules($late));
        $this->assertSame(IncomingEInvoiceStatus::Rejected, $late->refresh()->status);
        $this->assertSame('Testnachricht', $late->decision_note);
        $this->assertNull($late->decided_by);

        $this->get(route('finance.incoming-invoices.rules'))->assertOk()->assertSee('beispiel-konverter.de')->assertSee('Testnachricht');
        $this->post(route('finance.incoming-invoices.rules.automation.toggle', $rule))->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($rule->refresh()->is_active);
        $this->delete(route('finance.incoming-invoices.rules.automation.destroy', $rule))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('automation_rules', ['id' => $rule->id]);
        // Sicherheitsaudit 2026-10-21, authz-1: Schalten und Löschen hinterlassen eine Spur.
        $events = \App\Models\Audit\AuditLog::query()
            ->where('auditable_type', \App\Support\MorphMap::stableKey(\App\Models\Automation\AutomationRule::class))
            ->where('auditable_id', $rule->id)->pluck('event')->all();
        $this->assertContains('updated', $events);
        $this->assertContains('deleted', $events);
    }

    public function test_discarding_removes_document_and_file_and_future_documents_are_discarded_by_rule(): void {
        $first = $this->incoming(['seller_name' => 'Beispiel-Konverter.de']);
        $second = $this->incoming(['seller_name' => 'Beispiel-Konverter.de']);
        $documentIds = [$first->document_id, $second->document_id];

        $this->post(route('finance.incoming-invoices.assign', $first), [
            'direction' => 'incoming', 'mode' => 'not_invoice', 'note' => 'Testnachricht', 'exclude_action' => 'discard', 'apply_to_open' => '1',
        ])->assertRedirect()->assertSessionHas('success', static fn (string $message): bool => str_contains($message, 'verworfen'));

        $this->assertDatabaseMissing('incoming_einvoices', ['id' => $first->id]);
        $this->assertDatabaseMissing('incoming_einvoices', ['id' => $second->id]);
        foreach ($documentIds as $documentId) {
            $this->assertDatabaseMissing('documents', ['id' => $documentId]);
        }
        $this->assertDatabaseHas('audit_logs', ['event' => 'incoming_einvoice.discarded', 'auditable_id' => $first->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'document.purged', 'auditable_id' => $documentIds[0]]);

        $late = $this->incoming(['seller_name' => 'Beispiel-Konverter.de']);
        $lateDocumentId = $late->document_id;
        $this->assertTrue(app(IncomingEInvoiceService::class)->applyRules($late));
        $this->assertFalse($late->exists);
        $this->assertDatabaseMissing('incoming_einvoices', ['id' => $late->id]);
        $this->assertDatabaseMissing('documents', ['id' => $lateDocumentId]);
    }

    public function test_rejected_tab_lists_rejected_documents_and_deletes_them_permanently(): void {
        $rejected = $this->incoming(['invoice_number' => 'REJ-1', 'status' => IncomingEInvoiceStatus::Rejected]);
        $open = $this->incoming(['invoice_number' => 'OPEN-2']);

        $this->get(route('finance.incoming-invoices.index', ['tab' => 'rejected']))
            ->assertOk()->assertSee('REJ-1')->assertDontSee('OPEN-2')->assertSee(__('Endgültig löschen'));
        $this->post(route('finance.incoming-invoices.bulk-discard'), ['ids' => [$rejected->sqid, $open->sqid]])
            ->assertRedirect()->assertSessionHas('warning', static fn (string $message): bool => str_contains($message, '1 Eingänge endgültig gelöscht, 1 übersprungen'));

        $this->assertDatabaseMissing('incoming_einvoices', ['id' => $rejected->id]);
        $this->assertDatabaseHas('incoming_einvoices', ['id' => $open->id]);
        $this->get(route('finance.incoming-invoices.index', ['tab' => 'all', 'status' => 'received']))->assertOk()->assertSee('OPEN-2');
    }

    public function test_an_inbox_rule_can_assign_a_party_by_seller_name(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH']);
        AutomationRule::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Pinsel', 'trigger_event' => IncomingInvoiceReceivedTrigger::KEY,
            'conditions' => ['all' => [['field' => 'seller_name_key', 'op' => 'contains', 'value' => 'pinsel']]],
            'actions' => [['type' => AssignIncomingInvoiceAction::TYPE, 'params' => ['party' => 'supplier:' . $supplier->sqid]]],
            'is_active' => true, 'priority' => 100, 'created_by_id' => $this->admin->id,
        ]);
        $incoming = $this->incoming(['seller_name' => 'Pinsel  GmbH & Co.']);

        $this->assertTrue(app(IncomingEInvoiceService::class)->applyRules($incoming));
        $this->assertSame($supplier->id, $incoming->refresh()->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::Rule, $incoming->match_kind);
        $this->get(route('finance.incoming-invoices.rules'))->assertOk()->assertSee('Pinsel GmbH');
    }

    public function test_assigning_can_apply_to_the_other_open_documents_of_the_same_party_name(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH']);
        $first = $this->incoming(['seller_name' => 'Pinsel  GmbH']);
        $second = $this->incoming(['seller_name' => 'pinsel gmbh']);
        $outgoing = $this->incoming(['buyer_name' => 'Pinsel GmbH', 'seller_name' => 'Pinsel GmbH', 'direction' => DocumentDirection::Outgoing]);
        $other = $this->incoming(['seller_name' => 'Andere GmbH']);

        $this->post(route('finance.incoming-invoices.assign', $first), [
            'direction' => 'incoming', 'mode' => 'existing', 'party' => 'supplier:' . $supplier->sqid, 'apply_to_open' => '1',
        ])->assertRedirect()->assertSessionHas('success', static fn (string $message): bool => str_contains($message, '1 weiterer offener Eingang'));

        $this->assertSame($supplier->id, $first->refresh()->supplier_id);
        $this->assertSame($supplier->id, $second->refresh()->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::Manual, $second->match_kind);
        $this->assertNull($outgoing->refresh()->supplier_id);
        $this->assertNull($other->refresh()->supplier_id);
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

    public function test_bulk_assignment_names_the_reason_for_each_skipped_document(): void {
        // EU-Ausland braucht einen echten Firmenkontakt — die Vorgabe „Sammelkontakt“ der Sammelaktion scheitert daran.
        $austrian = $this->incoming(['seller_name' => 'RZL GmbH', 'summary' => ['seller_country' => 'AT']]);
        $transferred = $this->incoming([]);
        $transferred->forceFill(['transferred_at' => now()])->save();

        $collective = __('Reverse Charge, innergemeinschaftlicher Fall oder Drittland: Dafür braucht es einen echten Firmenkontakt, keinen Sammelkontakt.');
        $handedOver = __('Der Eingang ist bereits an die Buchhaltung übergeben — die Zuordnung ist dort zu ändern.');
        $this->post(route('finance.incoming-invoices.bulk-assign'), ['ids' => [$austrian->sqid, $transferred->sqid], 'party' => 'collective', 'confirmed' => '1'])
            ->assertRedirect()
            ->assertSessionHas('warning', static fn (string $message): bool => str_contains($message, '0 Eingänge zugeordnet, 2 übersprungen.')
                && str_contains($message, '1 × ' . $collective) && str_contains($message, '1 × ' . $handedOver));

        $this->assertNull($austrian->refresh()->supplier_id);
    }

    public function test_a_new_party_also_assigns_the_other_open_documents_of_that_party(): void {
        $first = $this->incoming(['seller_name' => 'RZL GmbH', 'seller_vat_id' => 'ATU12345678']);
        $second = $this->incoming(['seller_name' => 'RZL GmbH', 'seller_vat_id' => 'ATU12345678']);
        $third = $this->incoming(['seller_name' => 'RZL GmbH', 'seller_vat_id' => 'ATU12345678']);
        $other = $this->incoming(['seller_name' => 'Andere GmbH', 'seller_vat_id' => 'DE999999999']);

        $this->post(route('finance.incoming-invoices.assign', $first), [
            'direction' => 'incoming', 'mode' => 'new', 'new_name' => 'RZL GmbH', 'new_vat_id' => 'ATU12345678', 'new_country' => 'at',
        ])->assertRedirect()->assertSessionHas('success', static fn (string $message): bool => str_contains($message, '2 weitere offene Eingänge'));

        $supplier = Supplier::query()->where('name', 'RZL GmbH')->sole();
        $this->assertSame($supplier->id, $first->refresh()->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::Manual, $first->match_kind);
        $this->assertSame($supplier->id, $second->refresh()->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::VatId, $second->match_kind);
        $this->assertSame($supplier->id, $third->refresh()->supplier_id);
        $this->assertNull($other->refresh()->supplier_id);
    }

    public function test_a_remembered_sender_also_assigns_the_other_open_documents_from_that_address(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH']);
        $first = $this->incoming(['sender_email' => 'rechnung@pinsel.example']);
        $second = $this->incoming(['sender_email' => 'rechnung@pinsel.example']);
        $rejected = $this->incoming(['sender_email' => 'rechnung@pinsel.example', 'status' => IncomingEInvoiceStatus::Rejected]);
        $stranger = $this->incoming(['sender_email' => 'noreply@portal.example']);

        $this->post(route('finance.incoming-invoices.assign', $first), [
            'direction' => 'incoming', 'mode' => 'existing', 'party' => 'supplier:' . $supplier->sqid, 'remember_sender' => '1',
        ])->assertRedirect()->assertSessionHas('success', static fn (string $message): bool => str_contains($message, '1 weiterer offener Eingang'));

        $this->assertSame($supplier->id, $second->refresh()->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::SenderRule, $second->match_kind);
        $this->assertNull($rejected->refresh()->supplier_id);
        $this->assertNull($stranger->refresh()->supplier_id);
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

    public function test_a_reminder_hint_proposes_no_invoice_and_links_the_reminded_documents(): void {
        $invoice = $this->incoming(['invoice_number' => 'RZXRE2501475', 'seller_name' => 'Rechenzentrum Beispielstadt']);
        $reminder = $this->incoming([
            'invoice_number' => null, 'seller_name' => null, 'recognition' => IncomingInvoiceRecognition::None, 'amount_gross' => null,
            'summary' => ['kind_hint' => 'reminder', 'reminder_for' => ['RZXRE2501475'], 'extraction' => ['reason' => 'no_number', 'reader' => 'pdftotext', 'found' => ['gross' => '16.51'], 'hints' => ['issue_date' => '2025-07-07'], 'kind_hint' => 'reminder', 'reminder_for' => ['RZXRE2501475']]],
        ]);

        $this->get(route('finance.incoming-invoices.index'))->assertOk()->assertSee(__('enums.billing.kind.reminder'));
        $this->get(route('finance.incoming-invoices.assign.form', $reminder))->assertOk()
            ->assertSee(__('Sieht aus wie'))->assertSee('Mahnung zu RZXRE2501475')
            ->assertSee(route('finance.incoming-invoices.show', $invoice->document), false)
            ->assertSee('value="not_invoice" class="join-item btn btn-sm" aria-label="' . __('Keine Rechnung') . '" checked', false);
        $this->get(route('finance.incoming-invoices.values.form', $reminder))->assertOk()
            ->assertSee('value="2025-07-07"', false)->assertSee(__('Vorschlag aus dem Lieferdatum, bitte am Original prüfen.'));
        $this->get(route('finance.incoming-invoices.show', $reminder->document))->assertOk()
            ->assertSee(__('Bezieht sich auf'))->assertSee('RZXRE2501475');
    }
}
