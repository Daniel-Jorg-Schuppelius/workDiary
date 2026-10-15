<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\Invoicing\{InvoiceInquiryMatchKind, InvoiceInquiryStatus};
use App\Models\Audit\AuditLog;
use App\Models\Communication\CommunicationNote;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Invoicing\{Invoice, InvoiceInquiry};
use App\Models\Mail\EmailConnection;
use App\Models\Platform\User;
use App\Services\Mail\{MailIntakeService, ParsedMessage};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Arbeitsliste, Rechnungsseite und Inbox-Aktionen der Rechnungsnachfragen (Feature 166, MVP-1201/1202). */
final class InvoiceInquiryPageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Invoice $invoice;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test', 'name' => 'ACME GmbH']);
        $this->invoice = Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'RE-2026-0200',
            'status' => 'issued',
            'type' => 'invoice',
            'issued_on' => '2026-10-01',
            'currency' => 'EUR',
            'subtotal' => 100.00,
            'tax_rate' => 19.00,
            'tax_amount' => 19.00,
            'total' => 119.00,
        ]);
    }

    private function inquiry(array $attributes = []): InvoiceInquiry {
        return InvoiceInquiry::factory()->forInvoice($this->invoice)->create(array_merge([
            'from_email' => 'kunde@acme.test',
            'from_name' => 'Erika Muster',
            'subject' => 'Frage zur Position 3',
        ], $attributes));
    }

    private function inboxItemFor(string $messageId, string $from, string $subject, string $body = 'Text'): IntegrationInboxItem {
        $connection = EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Büro', 'host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl',
            'username' => 'buero@example.com', 'password' => 'secret', 'folder' => 'INBOX', 'active' => true,
        ]);
        $result = app(MailIntakeService::class)->intake($this->organization, $connection, new ParsedMessage(
            messageId: $messageId, uid: 1, fromEmail: $from, fromName: 'Fremd', subject: $subject, body: $body,
            receivedAt: Carbon::parse('2026-10-15 10:00:00'),
        ));
        $this->assertSame('created', $result);

        return IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->sole();
    }

    public function test_work_list_shows_open_inquiries_and_filters(): void {
        $open = $this->inquiry();
        $answered = $this->inquiry(['message_id' => '<zwei@x>', 'subject' => 'Schon erledigt', 'status' => InvoiceInquiryStatus::Answered, 'answered_at' => now()]);

        $this->actingAs($this->admin)->get(route('invoices.inquiries.index'))
            ->assertOk()
            ->assertSee('RE-2026-0200')
            ->assertSee('Erika Muster')
            ->assertSee($open->subject)
            ->assertDontSee($answered->subject);

        $this->actingAs($this->admin)->get(route('invoices.inquiries.index', ['status' => 'answered']))
            ->assertOk()
            ->assertSee($answered->subject)
            ->assertDontSee($open->subject);

        $this->actingAs($this->admin)->get(route('invoices.inquiries.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee($open->subject)
            ->assertSee($answered->subject);
    }

    public function test_answer_and_reopen_change_status_and_write_invoice_history(): void {
        $inquiry = $this->inquiry();

        $this->actingAs($this->admin)->post(route('invoices.inquiries.answer', $inquiry))->assertRedirect();
        $inquiry->refresh();
        $this->assertSame(InvoiceInquiryStatus::Answered, $inquiry->status);
        $this->assertSame($this->admin->id, $inquiry->answered_by);
        $this->assertNotNull($inquiry->answered_at);
        $this->assertSame(1, AuditLog::query()->where('event', 'invoice.inquiryAnswered')->where('auditable_id', $this->invoice->id)->count());

        // Zweites Beantworten ist kein Fehler, sondern ein Hinweis.
        $this->actingAs($this->admin)->post(route('invoices.inquiries.answer', $inquiry))->assertRedirect()->assertSessionHas('error');

        $this->actingAs($this->admin)->post(route('invoices.inquiries.reopen', $inquiry))->assertRedirect();
        $inquiry->refresh();
        $this->assertSame(InvoiceInquiryStatus::Open, $inquiry->status);
        $this->assertNull($inquiry->answered_by);
        $this->assertSame(1, AuditLog::query()->where('event', 'invoice.inquiryReopened')->count());
    }

    public function test_user_without_billing_right_is_forbidden(): void {
        $inquiry = $this->inquiry();
        $user = $this->orgUser();

        $this->actingAs($user)->get(route('invoices.inquiries.index'))->assertForbidden();
        $this->actingAs($user)->post(route('invoices.inquiries.answer', $inquiry))->assertForbidden();
    }

    public function test_invoice_page_lists_inquiries(): void {
        $this->inquiry(['subject' => 'Skonto noch möglich?']);

        $this->actingAs($this->admin)->get(route('invoices.show', $this->invoice))
            ->assertOk()
            ->assertSee('Skonto noch möglich?')
            ->assertSee(route('invoices.inquiries.answer', InvoiceInquiry::query()->sole()), false);
    }

    public function test_document_feed_links_to_work_list_with_open_count(): void {
        $this->inquiry();

        $this->actingAs($this->admin)->get(route('billing.feed'))
            ->assertOk()
            ->assertSee(route('invoices.inquiries.index'), false);
    }

    public function test_inbox_action_records_inquiry_from_number_and_resolves_item(): void {
        $item = $this->inboxItemFor('<inbox-1@x>', 'jemand@fremd.test', 'Zu RE-2026-0200', 'Bitte Rechnung prüfen.');
        $this->assertSame('RE-2026-0200', ($item->remote_snapshot['invoice_candidates'][0]['number'] ?? null));

        $this->actingAs($this->admin)
            ->post(route('admin.mail.inbox.book-inquiry'), ['item' => $item->sqid, 'invoice_number' => 're-2026-0200'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $inquiry = InvoiceInquiry::query()->sole();
        $this->assertSame($this->invoice->id, $inquiry->invoice_id);
        $this->assertSame(InvoiceInquiryMatchKind::Manual, $inquiry->match_kind);
        $this->assertSame('<inbox-1@x>', $inquiry->message_id);
        $this->assertSame('jemand@fremd.test', $inquiry->from_email);

        $note = CommunicationNote::query()->sole();
        $this->assertSame($note->id, $inquiry->communication_note_id);
        $this->assertTrue(MorphMap::is($note->notable_type, Customer::class));

        $item->refresh();
        $this->assertSame(IntegrationInboxStatus::ResolvedLinked, $item->status);

        // Erneut buchen: Eintrag ist aufgelöst.
        $this->actingAs($this->admin)
            ->post(route('admin.mail.inbox.book-inquiry'), ['item' => $item->sqid, 'invoice_number' => 'RE-2026-0200'])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(1, InvoiceInquiry::query()->count());
    }

    public function test_inbox_action_rejects_unknown_number(): void {
        $item = $this->inboxItemFor('<inbox-2@x>', 'jemand@fremd.test', 'Hallo');

        $this->actingAs($this->admin)
            ->post(route('admin.mail.inbox.book-inquiry'), ['item' => $item->sqid, 'invoice_number' => 'RE-GIBT-ES-NICHT'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, InvoiceInquiry::query()->count());
        $this->assertSame(IntegrationInboxStatus::Open, $item->fresh()->status);
    }

    public function test_inbox_action_books_mail_as_note_on_order(): void {
        $entry = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'customer_id' => $this->customer->id,
            'title' => 'Heizung warten',
        ]);
        $item = $this->inboxItemFor('<inbox-3@x>', 'kunde@acme.test', 'AW: Rechnung', 'Eigentlich geht es um den Auftrag.');

        $this->actingAs($this->admin)->get(route('admin.integration.inbox'))
            ->assertOk()
            ->assertSee('Heizung warten');

        $this->actingAs($this->admin)
            ->post(route('admin.mail.inbox.book-order'), ['item' => $item->sqid, 'diary_entry' => $entry->getRouteKey()])
            ->assertRedirect()
            ->assertSessionHas('success');

        $note = CommunicationNote::query()->sole();
        $this->assertTrue(MorphMap::is($note->notable_type, DiaryEntry::class));
        $this->assertSame($entry->id, $note->notable_id);
        $this->assertSame('AW: Rechnung', $note->subject);
        $this->assertSame(IntegrationInboxStatus::ResolvedLinked, $item->fresh()->status);
        $this->assertSame(0, InvoiceInquiry::query()->count());
    }

    public function test_inbox_action_rejects_order_of_other_organization(): void {
        $otherAdmin = User::factory()->admin()->create();
        $foreign = DiaryEntry::factory()->create(['organization_id' => $otherAdmin->organization_id, 'user_id' => $otherAdmin->id]);
        $item = $this->inboxItemFor('<inbox-4@x>', 'kunde@acme.test', 'AW: Rechnung');

        $this->actingAs($this->admin)
            ->post(route('admin.mail.inbox.book-order'), ['item' => $item->sqid, 'diary_entry' => $foreign->getRouteKey()])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, CommunicationNote::query()->count());
        $this->assertSame(IntegrationInboxStatus::Open, $item->fresh()->status);
    }
}
