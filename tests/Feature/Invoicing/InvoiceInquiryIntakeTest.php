<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiryIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Document\DocumentDispatchStatus;
use App\Enums\Invoicing\InvoiceInquiryMatchKind;
use App\Enums\Notification\NotificationEvent;
use App\Models\Audit\AuditLog;
use App\Models\Communication\CommunicationNote;
use App\Models\Customer\Customer;
use App\Models\Document\DocumentDispatch;
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Invoicing\{Invoice, InvoiceInquiry};
use App\Models\Mail\EmailConnection;
use App\Models\Platform\{Organization, User};
use App\Notifications\GenericEventNotification;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Services\Communication\Mail\CommunicationNoteReplyService;
use App\Services\Invoicing\Mail\InvoiceInquiryMailIntakeHandler;
use App\Services\Mail\{MailAttachment, MailIntakeService, ParsedMessage};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Mail, Notification, Storage};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Rechnungsnachfragen aus dem Mail-Eingang (Feature 166, MVP-1200). */
final class InvoiceInquiryIntakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test', 'number' => 'K-1']);
    }

    private function connection(): EmailConnection {
        return EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Büro',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'buero@example.com',
            'password' => 'secret',
            'folder' => 'INBOX',
            'active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    /** @param  list<string>  $references */
    private function message(string $messageId, string $from, string $subject, string $body = 'Bitte um Rückmeldung.', ?string $inReplyTo = null, array $references = [], bool $auto = false): ParsedMessage {
        return new ParsedMessage(
            messageId: $messageId,
            uid: 1,
            fromEmail: $from,
            fromName: 'Absender',
            subject: $subject,
            body: $body,
            receivedAt: Carbon::parse('2026-10-15 09:00:00'),
            inReplyTo: $inReplyTo,
            references: $references,
            isAutoSubmitted: $auto,
        );
    }

    private function issuedInvoice(string $number, ?Organization $organization = null, ?Customer $customer = null): Invoice {
        return Invoice::query()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'customer_id' => ($customer ?? $this->customer)->id,
            'number' => $number,
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

    private function dispatch(Invoice $invoice, string $messageId, string $recipient): DocumentDispatch {
        return DocumentDispatch::query()->create([
            'organization_id' => $invoice->organization_id,
            'invoice_id' => $invoice->id,
            'document_kind' => 'invoice',
            'document_id' => $invoice->id,
            'channel' => DocumentDispatch::CHANNEL_EMAIL,
            'status' => DocumentDispatchStatus::Sent,
            'recipient' => $recipient,
            'meta' => ['message_id' => $messageId, 'cc' => ['cc@acme.test']],
        ]);
    }

    private function intake(ParsedMessage $message): string {
        return app(MailIntakeService::class)->intake($this->organization, $this->connection(), $message);
    }

    public function test_reply_to_invoice_mail_is_recorded_by_thread_even_from_unknown_sender(): void {
        $invoice = $this->issuedInvoice('RE-2026-0101');
        $this->dispatch($invoice, '<versand-1@workdiary.test>', 'kunde@acme.test');

        $result = $this->intake($this->message('<antwort-1@x>', 'irgendwer@fremd.test', 'AW: Ihre Rechnung', 'Wann ist die Zahlung fällig?', inReplyTo: 'versand-1@workdiary.test'));

        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $result);
        $inquiry = InvoiceInquiry::query()->sole();
        $this->assertSame($invoice->id, $inquiry->invoice_id);
        $this->assertSame('RE-2026-0101', $inquiry->invoice_number);
        $this->assertSame($this->customer->id, $inquiry->customer_id);
        $this->assertSame(InvoiceInquiryMatchKind::Thread, $inquiry->match_kind);
        $this->assertSame('versand-1@workdiary.test', $inquiry->in_reply_to);
        $this->assertTrue($inquiry->isOpen());

        // Korrespondenz als E-Mail-Notiz beim Kunden, Nachfrage verweist darauf.
        $note = CommunicationNote::query()->sole();
        $this->assertSame($note->id, $inquiry->communication_note_id);
        $this->assertTrue(MorphMap::is($note->notable_type, Customer::class));
        $this->assertSame($this->customer->id, $note->notable_id);
        $this->assertSame('AW: Ihre Rechnung', $note->subject);

        // Rechnungshistorie.
        $this->assertSame(1, AuditLog::query()
            ->where('event', 'invoice.inquiryRecorded')
            ->where('auditable_type', MorphMap::stableKey(Invoice::class))
            ->where('auditable_id', $invoice->id)
            ->count());
        $this->assertSame(0, IntegrationInboxItem::query()->count());
    }

    /** lk-20: Die Antwort des Kunden auf eine Antwort aus der Notiz nennt nur deren Message-ID. */
    public function test_reply_to_an_answer_from_the_note_threads_back_to_the_invoice(): void {
        Mail::fake();
        $invoice = $this->issuedInvoice('RE-2026-0201');
        $this->intake($this->message('<frage-2@x>', 'kunde@acme.test', 'Frage zu RE-2026-0201'));
        $note = CommunicationNote::query()->sole();

        $reply = app(CommunicationNoteReplyService::class)->reply($note, $this->user, ['kunde@acme.test'], [], 'AW: Frage', 'Die Zahlung ist am 30. fällig.');
        $replyId = (string) ExternalReference::query()->forReferenceable($reply)->value('external_id');
        $this->assertNotSame('', $replyId);

        $result = $this->intake($this->message('<frage-3@x>', 'kunde@acme.test', 'AW: AW: Frage', 'Danke, und die Gutschrift?', inReplyTo: $replyId));

        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $result);
        $followUp = InvoiceInquiry::query()->where('message_id', '<frage-3@x>')->sole();
        $this->assertSame($invoice->id, $followUp->invoice_id);
        $this->assertSame(InvoiceInquiryMatchKind::Thread, $followUp->match_kind);
    }

    public function test_references_to_an_earlier_inquiry_thread_back_to_the_invoice(): void {
        $invoice = $this->issuedInvoice('RE-2026-0202');
        $this->intake($this->message('<frage-4@x>', 'kunde@acme.test', 'Frage zu RE-2026-0202'));

        $this->intake($this->message('<frage-5@x>', 'kunde@acme.test', 'Nachtrag', 'Noch eine Frage.', references: ['<frage-4@x>']));

        $followUp = InvoiceInquiry::query()->where('message_id', '<frage-5@x>')->sole();
        $this->assertSame($invoice->id, $followUp->invoice_id);
        $this->assertSame(InvoiceInquiryMatchKind::Thread, $followUp->match_kind);
    }

    /** lk-20: Die automatische Erfassung hängt die Anhänge der Mail an die Notiz, eingebettete Bilder nicht. */
    public function test_automatic_capture_attaches_the_mail_files_to_the_note(): void {
        Storage::fake('local');
        $this->issuedInvoice('RE-2026-0203');
        $message = $this->message('<frage-6@x>', 'kunde@acme.test', 'Frage zu RE-2026-0203')->withAttachments([
            new MailAttachment('kontoauszug.pdf', 'application/pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
            new MailAttachment('logo.png', 'image/png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='), true),
            new MailAttachment('makro.exe', 'application/x-msdownload', 'MZ'),
        ]);

        $this->intake($message);

        $note = CommunicationNote::query()->sole();
        $this->assertSame(['kontoauszug.pdf'], $note->attachments()->pluck('original_name')->all());
    }

    public function test_invoice_number_from_customer_address_is_recorded(): void {
        $invoice = $this->issuedInvoice('RE-2026-0102');

        $result = $this->intake($this->message('<frage-1@x>', 'kunde@acme.test', 'Frage zu RE-2026-0102'));

        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $result);
        $inquiry = InvoiceInquiry::query()->sole();
        $this->assertSame($invoice->id, $inquiry->invoice_id);
        $this->assertSame(InvoiceInquiryMatchKind::Number, $inquiry->match_kind);
    }

    /** Konsolidierungs-Audit 2026-10-21, ko-08/ko-07: Präfix mit Leerzeichen und Faden-ID ohne Klammern. */
    public function test_spaced_number_and_bare_thread_id_are_recognised(): void {
        $spaced = $this->issuedInvoice('RE 2026/0110');
        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $this->intake($this->message('<frage-10@x>', 'kunde@acme.test', 'Frage zu RE 2026/0110')));
        $this->assertSame($spaced->id, InvoiceInquiry::query()->where('message_id', '<frage-10@x>')->value('invoice_id'));

        $threaded = $this->issuedInvoice('RE-2026-0111');
        $this->dispatch($threaded, '<versand-11@workdiary.test>', 'kunde@acme.test');
        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $this->intake($this->message('<frage-11@x>', 'fremd@example.org', 'Re: Rechnung', inReplyTo: 'versand-11@workdiary.test')));
        $this->assertSame($threaded->id, InvoiceInquiry::query()->where('message_id', '<frage-11@x>')->value('invoice_id'));
    }

    public function test_contact_person_and_dispatch_cc_count_as_sender_of_the_case(): void {
        $this->customer->update(['contact_persons' => [['name' => 'Erika Muster', 'email' => 'erika@acme.test', 'primary' => true]]]);
        $invoice = $this->issuedInvoice('RE-2026-0103');
        $this->dispatch($invoice, '<versand-3@workdiary.test>', 'buchhaltung@acme.test');

        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $this->intake($this->message('<frage-3a@x>', 'erika@acme.test', 'RE-2026-0103')));
        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $this->intake($this->message('<frage-3b@x>', 'cc@acme.test', 'Rechnung RE-2026-0103 erhalten')));
        $this->assertSame(2, InvoiceInquiry::query()->where('invoice_id', $invoice->id)->count());
    }

    public function test_invoice_number_from_unknown_sender_marks_inbox_item_for_review(): void {
        $invoice = $this->issuedInvoice('RE-2026-0104');

        $result = $this->intake($this->message('<fremd-1@x>', 'jemand@fremd.test', 'Zu RE-2026-0104'));

        $this->assertSame('created', $result);
        $this->assertSame(0, InvoiceInquiry::query()->count());
        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->sole();
        $snapshot = $item->remote_snapshot ?? [];
        $this->assertSame(InvoiceInquiryMailIntakeHandler::REVIEW_SENDER_UNKNOWN, $snapshot['invoice_review'] ?? null);
        $this->assertSame('RE-2026-0104', $snapshot['invoice_candidates'][0]['number'] ?? null);
        $this->assertSame($invoice->getRouteKey(), $snapshot['invoice_candidates'][0]['invoice_sqid'] ?? null);
        $this->assertNull($snapshot['invoice_candidates'][0]['source'] ?? null);
        // Basisfelder des Snapshots bleiben erhalten.
        $this->assertSame('<fremd-1@x>', $snapshot['message_id'] ?? null);
    }

    public function test_two_invoice_numbers_mark_inbox_item_as_multiple(): void {
        $this->issuedInvoice('RE-2026-0105');
        $this->issuedInvoice('RE-2026-0106');

        $result = $this->intake($this->message('<zwei@x>', 'kunde@acme.test', 'Rechnungen', 'Zu RE-2026-0105 und RE-2026-0106 habe ich Fragen.'));

        $this->assertSame('created', $result);
        $this->assertSame(0, InvoiceInquiry::query()->count());
        $snapshot = IntegrationInboxItem::query()->sole()->remote_snapshot ?? [];
        $this->assertSame(InvoiceInquiryMailIntakeHandler::REVIEW_MULTIPLE, $snapshot['invoice_review'] ?? null);
        $this->assertCount(2, $snapshot['invoice_candidates'] ?? []);
    }

    public function test_same_message_is_recorded_only_once(): void {
        $this->issuedInvoice('RE-2026-0107');
        $message = $this->message('<doppelt@x>', 'kunde@acme.test', 'RE-2026-0107');

        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $this->intake($message));
        $this->assertSame('skipped', $this->intake($message));
        $this->assertSame(1, InvoiceInquiry::query()->count());
        $this->assertSame(1, CommunicationNote::query()->count());
    }

    public function test_auto_submitted_reply_never_becomes_an_inquiry(): void {
        $invoice = $this->issuedInvoice('RE-2026-0108');
        $this->dispatch($invoice, '<versand-8@workdiary.test>', 'kunde@acme.test');

        $result = $this->intake($this->message('<ooo@x>', 'kunde@acme.test', 'Abwesenheitsnotiz: RE-2026-0108', 'Ich bin nicht im Haus.', inReplyTo: '<versand-8@workdiary.test>', auto: true));

        $this->assertSame('created', $result);
        $this->assertSame(0, InvoiceInquiry::query()->count());
        $this->assertArrayNotHasKey('invoice_candidates', IntegrationInboxItem::query()->sole()->remote_snapshot ?? []);
    }

    public function test_foreign_organization_number_and_thread_never_match(): void {
        $otherOrg = Organization::factory()->create();
        $otherCustomer = Customer::factory()->create(['organization_id' => $otherOrg->id, 'email' => 'kunde@acme.test']);
        $foreign = $this->issuedInvoice('RE-FREMD-1', $otherOrg, $otherCustomer);
        $this->dispatch($foreign, '<versand-fremd@workdiary.test>', 'kunde@acme.test');

        $result = $this->intake($this->message('<fremd-org@x>', 'kunde@acme.test', 'RE-FREMD-1', 'Frage', inReplyTo: '<versand-fremd@workdiary.test>'));

        $this->assertSame('created', $result);
        $this->assertSame(0, InvoiceInquiry::query()->count());
        $this->assertArrayNotHasKey('invoice_candidates', IntegrationInboxItem::query()->sole()->remote_snapshot ?? []);
    }

    public function test_lexoffice_voucher_number_is_recorded_as_external_inquiry(): void {
        $voucher = LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'lx-voucher-1',
            'customer_id' => $this->customer->id, 'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'RE1001', 'voucher_date' => '2026-10-01',
            'total_amount' => '119.00', 'currency' => 'EUR', 'archived' => false,
        ]);

        $result = $this->intake($this->message('<lx@x>', 'kunde@acme.test', 'Ihre Rechnung RE1001'));

        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $result);
        $inquiry = InvoiceInquiry::query()->sole();
        $this->assertNull($inquiry->invoice_id);
        $this->assertSame('lexoffice', $inquiry->external_source);
        $this->assertSame((string) $voucher->id, $inquiry->external_key);
        $this->assertSame('RE1001', $inquiry->invoice_number);
        $this->assertSame($this->customer->id, $inquiry->customer_id);
        $this->assertTrue($inquiry->isExternal());
        $this->assertSame(1, CommunicationNote::query()->count());
        $this->assertSame(0, AuditLog::query()->where('event', 'invoice.inquiryRecorded')->count()); // keine lokale Rechnung
    }

    public function test_accounting_voucher_number_is_recorded_with_its_program(): void {
        $voucher = AccountingVoucher::factory()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'sevdesk',
            'customer_id' => $this->customer->id,
            'direction' => 'outgoing',
            'voucher_state' => 'open',
            'document_kind' => 'invoice',
            'voucher_number' => 'RE-77',
        ]);

        $result = $this->intake($this->message('<sd@x>', 'kunde@acme.test', 'Rückfrage', 'Zur Rechnung RE-77: stimmt der Betrag?'));

        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $result);
        $inquiry = InvoiceInquiry::query()->sole();
        $this->assertSame('sevdesk', $inquiry->external_source);
        $this->assertSame((string) $voucher->id, $inquiry->external_key);
    }

    public function test_voucher_without_customer_is_marked_for_review(): void {
        LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'lx-voucher-2',
            'customer_id' => null, 'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'RE1002', 'voucher_date' => '2026-10-01',
            'total_amount' => '50.00', 'currency' => 'EUR', 'archived' => false,
        ]);

        $this->assertSame('created', $this->intake($this->message('<lx2@x>', 'kunde@acme.test', 'RE1002')));
        $snapshot = IntegrationInboxItem::query()->sole()->remote_snapshot ?? [];
        $this->assertSame(InvoiceInquiryMailIntakeHandler::REVIEW_NO_CUSTOMER, $snapshot['invoice_review'] ?? null);
        $this->assertSame('lexoffice', $snapshot['invoice_candidates'][0]['source'] ?? null);
    }

    public function test_transferred_invoice_with_mirrored_number_counts_once_as_local(): void {
        $invoice = $this->issuedInvoice('RE-2026-0110');
        LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'lx-mirror',
            'customer_id' => $this->customer->id, 'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'RE-2026-0110', 'voucher_date' => '2026-10-01',
            'total_amount' => '119.00', 'currency' => 'EUR', 'archived' => false,
        ]);

        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $this->intake($this->message('<mirror@x>', 'kunde@acme.test', 'RE-2026-0110')));
        $inquiry = InvoiceInquiry::query()->sole();
        $this->assertSame($invoice->id, $inquiry->invoice_id);
        $this->assertNull($inquiry->external_source);
    }

    public function test_program_number_on_local_invoice_is_recognised(): void {
        $invoice = $this->issuedInvoice('ENTWURF-5');
        $invoice->update(['external_number' => 'LX-9000']);

        $this->assertSame(InvoiceInquiryMailIntakeHandler::RESULT, $this->intake($this->message('<ext@x>', 'kunde@acme.test', 'Rechnung LX-9000')));
        $this->assertSame($invoice->id, InvoiceInquiry::query()->sole()->invoice_id);
    }

    public function test_an_automatically_recorded_inquiry_notifies_the_person_behind_the_mailbox(): void {
        Notification::fake();
        $invoice = $this->issuedInvoice('RE-2026-0102');
        $this->dispatch($invoice, '<versand-2@workdiary.test>', 'kunde@acme.test');

        $this->intake($this->message('<antwort-2@x>', 'kunde@acme.test', 'AW: Ihre Rechnung', 'Frage zur Position 3', inReplyTo: 'versand-2@workdiary.test'));

        $note = CommunicationNote::query()->sole();
        Notification::assertSentTo($this->user, GenericEventNotification::class, fn (GenericEventNotification $notification): bool => $notification->event === NotificationEvent::CommunicationMailReceived
            && str_ends_with((string) ($notification->payload['url'] ?? ''), '#communication-note-' . $note->id)
            && ($notification->payload['title_params']['number'] ?? null) === 'RE-2026-0102'
            && ($notification->payload['title_params']['customer'] ?? null) === $this->customer->name);
    }
}
