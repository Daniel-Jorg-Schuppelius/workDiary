<?php
/*
 * Created on   : Wed Nov 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceWorkThroughTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Models\Document\{Document, DocumentVersion};
use App\Models\Invoicing\{IncomingDismissedFile, IncomingEInvoice};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingOrigin};
use App\Services\Mail\MailAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rechnungseingang zügig abarbeiten: „Keine Rechnung – löschen“ in einem
 * Schritt (MVP-1805) und Durcharbeiten der Reiter „Zuzuordnen“ und
 * „Zu prüfen“ (MVP-1806).
 */
final class IncomingInvoiceWorkThroughTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH', 'company' => 'Pinsel GmbH']);
        $this->actingAs($this->admin);
    }

    public function test_a_single_document_is_deleted_as_no_invoice_in_one_step(): void {
        $incoming = $this->incoming();
        $documentId = $incoming->document_id;

        $this->post(route('finance.incoming-invoices.not-invoice'), ['only' => $incoming->sqid])
            ->assertRedirect(route('finance.incoming-invoices.index'))
            ->assertSessionHas('success', __('Eingang als „keine Rechnung“ gelöscht.'));

        $this->assertDatabaseMissing('incoming_einvoices', ['id' => $incoming->id]);
        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'incoming_einvoice.decided', 'auditable_id' => $incoming->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'incoming_einvoice.discarded', 'auditable_id' => $incoming->id]);
    }

    public function test_an_assigned_document_loses_its_party_first_and_is_then_deleted(): void {
        $incoming = $this->incoming(['supplier_id' => $this->supplier->id]);

        $this->post(route('finance.incoming-invoices.not-invoice'), ['only' => $incoming->sqid])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('incoming_einvoices', ['id' => $incoming->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'incoming_einvoice.unassigned', 'auditable_id' => $incoming->id]);
    }

    public function test_the_selection_skips_decided_and_transferred_documents_with_a_reason(): void {
        $open = $this->incoming();
        $approved = $this->incoming(['supplier_id' => $this->supplier->id, 'status' => IncomingEInvoiceStatus::Approved]);
        $transferred = $this->incoming(['supplier_id' => $this->supplier->id, 'transferred_at' => now()]);

        $this->post(route('finance.incoming-invoices.not-invoice'), ['ids' => [$open->sqid, $approved->sqid, $transferred->sqid]])
            ->assertRedirect()
            ->assertSessionHas('warning', static fn (string $message): bool => str_starts_with($message, (string) __(':deleted Eingänge als „keine Rechnung“ gelöscht, :skipped übersprungen.', ['deleted' => 1, 'skipped' => 2]))
                && str_contains($message, '2 × ' . __('Nur offene, nicht übergebene Eingänge lassen sich als „keine Rechnung“ löschen.')));

        $this->assertDatabaseMissing('incoming_einvoices', ['id' => $open->id]);
        $this->assertSame(IncomingEInvoiceStatus::Approved, $approved->refresh()->status);
        $this->assertSame($this->supplier->id, $transferred->refresh()->supplier_id);
    }

    public function test_deleting_needs_the_billing_right(): void {
        $incoming = $this->incoming();
        $this->actingAs(User::factory()->create(['organization_id' => $this->organization->id]));

        $this->post(route('finance.incoming-invoices.not-invoice'), ['only' => $incoming->sqid])->assertForbidden();

        $this->assertDatabaseHas('incoming_einvoices', ['id' => $incoming->id]);
    }

    public function test_the_list_offers_the_quick_delete_and_the_entry_into_working_through(): void {
        $newest = $this->incoming(['received_at' => now()]);
        $this->incoming(['received_at' => now()->subDay()]);

        $html = (string) $this->get(route('finance.incoming-invoices.index', ['tab' => 'assign']))->assertOk()
            ->assertSee(__('Durcharbeiten'))->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*formaction="[^"]*keine-rechnung"[^>]*data-confirm-dialog/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*form="not-invoice-form"[^>]*name="only"[^>]*value="' . preg_quote($newest->sqid, '/') . '"/', $html);
        $this->assertStringContainsString(e(route('finance.incoming-invoices.show', ['document' => $newest->document, 'queue' => 'assign'])), $html);

        $this->get(route('finance.incoming-invoices.index', ['tab' => 'all']))->assertOk()->assertDontSee(__('Durcharbeiten'));
    }

    public function test_the_detail_page_shows_position_and_neighbours_of_the_queue(): void {
        $first = $this->incoming(['received_at' => now()]);
        $second = $this->incoming(['received_at' => now()->subDay()]);
        $third = $this->incoming(['received_at' => now()->subDays(2)]);

        $html = (string) $this->get(route('finance.incoming-invoices.show', ['document' => $second->document, 'queue' => 'assign']))->assertOk()
            ->assertSee(__(':position von :total', ['position' => 2, 'total' => 3]))->getContent();

        $this->assertStringContainsString(e(route('finance.incoming-invoices.show', ['document' => $first->document, 'queue' => 'assign'])), $html);
        $this->assertStringContainsString(e(route('finance.incoming-invoices.show', ['document' => $third->document, 'queue' => 'assign'])), $html);
        $this->assertStringContainsString('name="queue" value="assign"', $html);

        $this->get(route('finance.incoming-invoices.show', $second->document))->assertOk()
            ->assertDontSee(__(':position von :total', ['position' => 2, 'total' => 3]));
    }

    public function test_after_deleting_the_next_document_of_the_queue_follows(): void {
        $first = $this->incoming(['received_at' => now()]);
        $second = $this->incoming(['received_at' => now()->subDay()]);

        $this->post(route('finance.incoming-invoices.not-invoice'), ['only' => $first->sqid, 'queue' => 'assign'])
            ->assertRedirect(route('finance.incoming-invoices.show', ['document' => $second->document, 'queue' => 'assign']));
        // Letzter der Schlange: zurück zur Liste.
        $this->post(route('finance.incoming-invoices.not-invoice'), ['only' => $second->sqid, 'queue' => 'assign'])
            ->assertRedirect(route('finance.incoming-invoices.index'));
    }

    public function test_assigning_moves_on_and_the_end_of_the_queue_wraps_to_skipped_documents(): void {
        $skipped = $this->incoming(['received_at' => now()]);
        $last = $this->incoming(['received_at' => now()->subDay()]);

        $this->post(route('finance.incoming-invoices.assign', $last), [
            'direction' => 'incoming', 'mode' => 'existing', 'party' => 'supplier:' . $this->supplier->sqid, 'queue' => 'assign',
        ])->assertRedirect(route('finance.incoming-invoices.show', ['document' => $skipped->document, 'queue' => 'assign']));

        $this->assertSame($this->supplier->id, $last->refresh()->supplier_id);
    }

    public function test_a_document_stays_while_it_is_still_in_the_queue(): void {
        $incoming = $this->incoming(['supplier_id' => $this->supplier->id]);
        $next = $this->incoming(['supplier_id' => $this->supplier->id, 'received_at' => now()->subDay()]);
        $here = route('finance.incoming-invoices.show', ['document' => $incoming->document, 'queue' => 'review']);

        // Rückfrage bleibt in „Zu prüfen“, die Freigabe verlässt die Schlange.
        $this->post(route('finance.incoming-invoices.decide', $incoming), ['decision' => 'question', 'queue' => 'review'])->assertRedirect($here);
        $this->post(route('finance.incoming-invoices.decide', $incoming), ['decision' => 'approved', 'queue' => 'review'])
            ->assertRedirect(route('finance.incoming-invoices.show', ['document' => $next->document, 'queue' => 'review']));
        // Ohne Schlange wie bisher: zurück auf den Eingang.
        $this->post(route('finance.incoming-invoices.decide', $next), ['decision' => 'question'])
            ->assertRedirect(route('finance.incoming-invoices.show', $next->document));
    }

    public function test_a_deleted_file_is_remembered_and_not_taken_from_the_mailbox_again(): void {
        $incoming = $this->incoming(['sha256' => hash('sha256', 'AGB')]);
        $this->post(route('finance.incoming-invoices.not-invoice'), ['only' => $incoming->sqid])->assertRedirect();
        $file = IncomingDismissedFile::query()->where('sha256', hash('sha256', 'AGB'))->firstOrFail();

        $attachment = new MailAttachment(filename: 'agb.pdf', mime: 'application/pdf', content: 'AGB');
        $result = app(IncomingEInvoiceService::class)->storeMessage($this->admin, [$attachment], new IncomingOrigin('shop@example.test', 'msg-1'));

        $this->assertSame(1, $result['discarded']);
        $this->assertSame([], $result['incomings']);
        $this->assertSame(1, $file->refresh()->hits);

        $this->get(route('finance.incoming-invoices.rules'))->assertOk()->assertSee(__('Gelöschte Dateien'));
        $this->delete(route('finance.incoming-invoices.rules.files.destroy', $file))->assertRedirect();
        $this->assertDatabaseMissing('incoming_dismissed_files', ['id' => $file->id]);
    }

    public function test_the_delete_button_is_visible_while_working_through_and_sits_in_the_menu_otherwise(): void {
        $incoming = $this->incoming();

        $this->get(route('finance.incoming-invoices.show', ['document' => $incoming->document, 'queue' => 'assign']))->assertOk()
            ->assertSee('data-toolbar-placement="bar"', false);
        $this->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()
            ->assertSee('data-toolbar-placement="danger"', false)->assertDontSee('data-toolbar-placement="bar"', false);
    }

    public function test_a_reminder_becomes_an_attachment_of_the_invoice_even_after_transfer(): void {
        $invoice = $this->incoming(['invoice_number' => 'RE-4711', 'supplier_id' => $this->supplier->id, 'status' => IncomingEInvoiceStatus::Approved, 'transferred_at' => now()]);
        $reminder = $this->withOriginal($this->incoming(['invoice_number' => null, 'summary' => ['kind_hint' => 'reminder', 'reminder_for' => ['RE-4711']]]), 'Mahnung');
        $documentId = $reminder->document_id;

        $this->post(route('finance.incoming-invoices.assign', $reminder), ['direction' => 'incoming', 'mode' => 'attach', 'attach_to' => $invoice->sqid])
            ->assertRedirect()->assertSessionHas('success', __('Beleg an Rechnung :number angehängt.', ['number' => 'RE-4711']));

        $this->assertDatabaseMissing('incoming_einvoices', ['id' => $reminder->id]);
        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
        $attachment = $invoice->attachments()->sole();
        $this->assertSame('mahnung.pdf', $attachment->original_name);
        $this->assertSame('Mahnung', Storage::disk('local')->get($attachment->path));
        $this->assertDatabaseHas('audit_logs', ['event' => 'incoming_einvoice.document_attached', 'auditable_id' => $invoice->id]);
        $this->assertDatabaseMissing('incoming_dismissed_files', ['sha256' => $reminder->sha256]);
    }

    public function test_a_reminder_waits_for_an_invoice_that_has_not_arrived_and_attaches_itself_later(): void {
        $reminder = $this->withOriginal($this->incoming(['invoice_number' => null, 'summary' => ['kind_hint' => 'reminder', 'reminder_for' => ['RE-9000']]]), 'Mahnung');

        $this->get(route('finance.incoming-invoices.assign.form', $reminder))->assertOk()
            ->assertSee('value="attach" class="join-item btn btn-sm" aria-label="' . __('An Rechnung anhängen') . '" checked', false)
            ->assertSee('value="RE-9000"', false);
        $this->post(route('finance.incoming-invoices.assign', $reminder), ['direction' => 'incoming', 'mode' => 'attach', 'attach_number' => 'RE-9000'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('RE-9000', $reminder->refresh()->awaited_invoice_number);
        $this->get(route('finance.incoming-invoices.index', ['tab' => 'assign']))->assertOk()->assertSee(__('Nichts zuzuordnen.'));
        $this->get(route('finance.incoming-invoices.index', ['tab' => 'waiting']))->assertOk()
            ->assertSee(__('Wartet auf Rechnung :number', ['number' => 'RE-9000']));

        // Eine andere Rechnung lässt den Beleg warten, die genannte nimmt ihn auf.
        $service = app(IncomingEInvoiceService::class);
        $this->assertSame(0, $service->attachAwaiting($this->incoming(['invoice_number' => 'RE-9001'])));
        $invoice = $this->incoming(['invoice_number' => 're-9000 ']);
        $this->assertSame(1, $service->attachAwaiting($invoice));

        $this->assertDatabaseMissing('incoming_einvoices', ['id' => $reminder->id]);
        $this->assertSame(1, $invoice->attachments()->count());
    }

    public function test_capturing_the_values_of_the_awaited_invoice_attaches_the_waiting_reminder(): void {
        $reminder = $this->withOriginal($this->incoming(['invoice_number' => null]), 'Mahnung');
        app(IncomingEInvoiceService::class)->awaitInvoice($reminder, 'RE-77', $this->admin);
        $invoice = $this->incoming(['invoice_number' => null, 'recognition' => IncomingInvoiceRecognition::None]);

        $this->post(route('finance.incoming-invoices.values', $invoice), [
            'invoice_number' => 'RE-77', 'issue_date' => '2026-11-01', 'currency' => 'EUR', 'amount_gross' => '119.00',
        ])->assertRedirect();

        $this->assertDatabaseMissing('incoming_einvoices', ['id' => $reminder->id]);
        $this->assertSame(1, $invoice->attachments()->count());
    }

    public function test_another_way_ends_the_waiting(): void {
        $reminder = $this->incoming(['invoice_number' => null]);
        app(IncomingEInvoiceService::class)->awaitInvoice($reminder, 'RE-77', $this->admin);

        $this->post(route('finance.incoming-invoices.assign', $reminder), ['direction' => 'incoming', 'mode' => 'existing', 'party' => 'supplier:' . $this->supplier->sqid])->assertRedirect();

        $this->assertNull($reminder->refresh()->awaited_invoice_number_key);
        $this->assertSame($this->supplier->id, $reminder->supplier_id);
    }

    public function test_the_detail_page_names_the_mail_the_document_came_from(): void {
        $incoming = $this->incoming([
            'sender_email' => 'buchhaltung@lieferant.example', 'source_reference' => '<abc-123@lieferant.example>',
            'summary' => ['mail_subject' => 'Ihre Rechnung November', 'mail_from_name' => 'Buchhaltung Lieferant', 'mail_received_at' => '2026-11-20T08:15:00+00:00', 'mail_mailbox' => 'Rechnungen', 'mail_attachments' => ['rechnung.pdf', 'agb.pdf']],
        ]);

        $this->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()
            ->assertSee('Buchhaltung Lieferant')->assertSee('Ihre Rechnung November')
            ->assertSee(__('Postfach :name', ['name' => 'Rechnungen']))->assertSee('rechnung.pdf, agb.pdf')
            ->assertSee('<abc-123@lieferant.example>');
    }

    private function withOriginal(IncomingEInvoice $incoming, string $contents): IncomingEInvoice {
        $path = 'documents/test/' . uniqid('doc', true) . '.pdf';
        Storage::disk('local')->put($path, $contents);
        $version = DocumentVersion::factory()->create(['document_id' => $incoming->document_id, 'path' => $path, 'original_name' => 'mahnung.pdf', 'mime' => 'application/pdf', 'uploaded_by_user_id' => $this->admin->id]);
        $incoming->document->forceFill(['current_version_id' => $version->id])->save();

        return $incoming->refresh();
    }

    /** @param  array<string, mixed>  $attributes */
    private function incoming(array $attributes = []): IncomingEInvoice {
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
