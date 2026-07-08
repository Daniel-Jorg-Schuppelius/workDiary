<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Mail;

use App\Models\{CommunicationNote, Customer, Document, EmailConnection, ExternalReference, IntegrationInboxItem, Invoice, Organization, User};
use App\Services\Mail\{MailAttachment, MailInboxResolutionService, MailIntakeService, MailboxGateway, ParsedMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeMailboxGateway;
use Tests\TestCase;

/**
 * Feature 056, MVP-117: E-Mail-Eingang. Prüft Inbox-First (Match→Kandidat vs.
 * unmatched, nie blind anlegen), Dublettenschutz über Message-ID, den
 * Herkunftsnachweis im Snapshot sowie die Auflösung in einen
 * Kommunikationsprotokoll-Eintrag und den Poll-Command.
 */
final class MailIntakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function connection(bool $active = true): EmailConnection {
        return EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
            'folder' => 'INBOX',
            'active' => $active,
        ]);
    }

    private function message(string $messageId, string $from, string $subject = 'Angebot', int $uid = 1): ParsedMessage {
        return new ParsedMessage(
            messageId: $messageId,
            uid: $uid,
            fromEmail: $from,
            fromName: 'Absender',
            subject: $subject,
            body: 'Bitte um Rückmeldung.',
            receivedAt: Carbon::parse('2026-07-05 09:00:00'),
            attachmentCount: 0,
        );
    }

    /**
     * @param  list<MailAttachment>  $attachments
     */
    private function messageWith(array $attachments, string $messageId = '<att@x>', string $from = 'kunde@acme.test', string $subject = 'Rechnung anbei'): ParsedMessage {
        return new ParsedMessage(
            messageId: $messageId,
            uid: 5,
            fromEmail: $from,
            fromName: 'Absender',
            subject: $subject,
            body: 'Anbei die Unterlagen.',
            receivedAt: Carbon::parse('2026-07-05 10:00:00'),
            attachments: $attachments,
        );
    }

    private function intake(): MailIntakeService {
        return app(MailIntakeService::class);
    }

    public function test_known_sender_creates_ambiguous_candidate(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $connection = $this->connection();

        $result = $this->intake()->intake($this->organization, $connection, $this->message('<m1@x>', 'kunde@acme.test'));

        $this->assertSame('created', $result);
        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();
        $this->assertSame(IntegrationInboxItem::CASE_AMBIGUOUS, $item->case_type);
        $this->assertSame($customer->getMorphClass(), $item->referenceable_type);
        $this->assertSame($customer->id, $item->referenceable_id);
        $this->assertNotEmpty($item->candidate_ids);
    }

    public function test_unknown_sender_creates_unmatched(): void {
        $connection = $this->connection();

        $this->intake()->intake($this->organization, $connection, $this->message('<m2@x>', 'fremd@nowhere.test'));

        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();
        $this->assertSame(IntegrationInboxItem::CASE_UNMATCHED, $item->case_type);
        $this->assertNull($item->referenceable_id);
        $this->assertSame([], $item->candidate_ids);
    }

    public function test_customer_number_in_subject_suggests_customer(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-4711']);

        // Unbekannter Absender → Sender-Match leer; nur die Referenz zählt.
        $this->intake()->intake($this->organization, $this->connection(), $this->message('<ref1@x>', 'fremd@nowhere.test', 'Rückfrage zu K-4711'));

        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();
        $this->assertSame(IntegrationInboxItem::CASE_AMBIGUOUS, $item->case_type);
        $this->assertSame($customer->getMorphClass(), $item->referenceable_type);
        $this->assertSame($customer->id, $item->referenceable_id);
        $this->assertNotEmpty($item->candidate_ids);
    }

    public function test_invoice_number_suggests_its_customer(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-1', 'email' => 'kunde@acme.test']);
        Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'RE-2025-777',
            'status' => 'issued',
            'type' => 'invoice',
            'issued_on' => '2026-07-01',
            'currency' => 'EUR',
            'subtotal' => 100.00,
            'tax_rate' => 19.00,
            'tax_amount' => 19.00,
            'total' => 119.00,
        ]);

        $this->intake()->intake($this->organization, $this->connection(), $this->message('<ref2@x>', 'fremd@nowhere.test', 'Zahlung zu RE-2025-777 offen'));

        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();
        $this->assertSame($customer->id, $item->referenceable_id);
    }

    public function test_foreign_org_reference_never_matches(): void {
        $otherOrg = Organization::factory()->create();
        Customer::factory()->create(['organization_id' => $otherOrg->id, 'number' => 'K-9999']);

        $this->intake()->intake($this->organization, $this->connection(), $this->message('<ref3@x>', 'fremd@nowhere.test', 'Betreff K-9999'));

        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();
        $this->assertSame(IntegrationInboxItem::CASE_UNMATCHED, $item->case_type); // fremde Nummer bleibt außen vor
        $this->assertNull($item->referenceable_id);
    }

    public function test_dedup_by_message_id(): void {
        $connection = $this->connection();
        $service = $this->intake();

        $first = $service->intake($this->organization, $connection, $this->message('<dup@x>', 'a@b.test'));
        $second = $service->intake($this->organization, $connection, $this->message('<dup@x>', 'a@b.test'));

        $this->assertSame('created', $first);
        $this->assertSame('skipped', $second);
        $this->assertSame(1, IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->count());
    }

    public function test_snapshot_carries_provenance(): void {
        $connection = $this->connection();

        $this->intake()->intake($this->organization, $connection, $this->message('<prov@x>', 'a@b.test'));

        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();
        $snapshot = (array) $item->remote_snapshot;
        $this->assertSame('<prov@x>', $snapshot['message_id']);
        $this->assertSame('Support / INBOX', $snapshot['mailbox']);
        $this->assertSame('<prov@x>', $item->external_id);
    }

    public function test_intake_creates_no_records_blindly(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $before = Customer::query()->count();

        $this->intake()->intake($this->organization, $this->connection(), $this->message('<m3@x>', 'kunde@acme.test'));

        $this->assertSame($before, Customer::query()->count()); // kein neuer Kunde
        $this->assertSame(0, CommunicationNote::query()->count()); // kein Protokolleintrag
    }

    public function test_resolution_creates_communication_note_and_closes_item(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $connection = $this->connection();
        $this->intake()->intake($this->organization, $connection, $this->message('<res@x>', 'kunde@acme.test', 'Wartungsanfrage'));
        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();

        $note = app(MailInboxResolutionService::class)->bookAsCommunicationNote($item, $customer, $this->user);

        $this->assertSame('email', $note->type->value);
        $this->assertSame('inbound', $note->direction->value);
        $this->assertSame($customer->getMorphClass(), $note->notable_type);

        $item->refresh();
        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_LINKED, $item->status);
        $this->assertSame(1, ExternalReference::query()
            ->where('plugin_id', MailIntakeService::PLUGIN_ID)
            ->where('external_type', MailIntakeService::EXTERNAL_TYPE)
            ->where('referenceable_type', $note->getMorphClass())
            ->count());
    }

    public function test_integration_inbox_shows_mail_book_action_not_generic_create(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $this->intake()->intake($this->organization, $this->connection(), $this->message('<view@x>', 'kunde@acme.test', 'Wartungsanfrage'));

        $response = $this->actingAs($admin)->get(route('admin.integration.inbox'));

        $response->assertOk();
        // Mail-Eintrag bietet das Buchen als Kommunikationsnotiz, nicht das
        // generische „Neu anlegen" (das einen Bogus-Kunden erzeugen würde).
        $response->assertSee(route('admin.mail.inbox.book'), false);
        $response->assertSee(__('mail.inbox.book_action'));
        $response->assertDontSee(__('Neu anlegen'));
    }

    public function test_intake_persists_whitelisted_attachment_and_rejects_dangerous(): void {
        Storage::fake('local');
        Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);

        $this->intake()->intake($this->organization, $this->connection(), $this->messageWith([
            new MailAttachment('rechnung.pdf', 'application/pdf', '%PDF-1.4 hallo'),
            new MailAttachment('malware.exe', 'application/x-msdownload', 'MZ binaries'),
        ], '<att-mix@x>'));

        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();
        $atts = collect((array) (($item->remote_snapshot ?? [])['attachments'] ?? []));
        $this->assertCount(2, $atts);

        $pdf = $atts->firstWhere('original_name', 'rechnung.pdf');
        $this->assertTrue($pdf['stored']);
        Storage::disk('local')->assertExists($pdf['stored_path']);

        // Gefährlicher MIME-Typ wird verworfen (nicht persistiert), mit Grund.
        $exe = $atts->firstWhere('original_name', 'malware.exe');
        $this->assertFalse($exe['stored']);
        $this->assertSame('mime', $exe['rejected_reason']);
        $this->assertArrayNotHasKey('stored_path', $exe);
    }

    public function test_intake_rejects_oversized_attachment(): void {
        Storage::fake('local');
        config(['mail_intake.attachments.max_bytes' => 8]);

        $this->intake()->intake($this->organization, $this->connection(), $this->messageWith([
            new MailAttachment('gross.pdf', 'application/pdf', '%PDF viel zu lang'),
        ], '<att-big@x>'));

        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();
        $att = ((array) (($item->remote_snapshot ?? [])['attachments'] ?? []))[0];
        $this->assertFalse($att['stored']);
        $this->assertSame('size', $att['rejected_reason']);
    }

    public function test_booking_attaches_stored_files_to_note(): void {
        Storage::fake('local');
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $this->intake()->intake($this->organization, $this->connection(), $this->messageWith([
            new MailAttachment('rechnung.pdf', 'application/pdf', '%PDF-1.4 hallo'),
        ], '<book-att@x>'));
        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();

        $note = app(MailInboxResolutionService::class)->bookAsCommunicationNote($item, $customer, $this->user);

        $this->assertCount(1, $note->attachments()->get());
        $attachment = $note->attachments()->firstOrFail();
        $this->assertSame('rechnung.pdf', $attachment->original_name);
        $this->assertSame($this->organization->id, $attachment->organization_id);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_import_attachments_to_dms_is_idempotent(): void {
        Storage::fake('local');
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $this->intake()->intake($this->organization, $this->connection(), $this->messageWith([
            new MailAttachment('rechnung.pdf', 'application/pdf', '%PDF-1.4 hallo'),
        ], '<dms-att@x>', subject: 'Rechnung RE-2025-900'));
        $item = IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();
        $service = app(MailInboxResolutionService::class);

        $first = $service->importAttachmentsToDms($item, $this->user, $customer);
        $second = $service->importAttachmentsToDms($item->fresh(), $this->user, $customer);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second); // Doppelauflösung legt nichts doppelt an
        $this->assertSame(1, Document::query()->count());

        $document = $first[0];
        $this->assertSame('rechnung.pdf', $document->title);
        $this->assertSame($customer->getMorphClass(), $document->documentable_type);
        $this->assertSame($customer->id, $document->documentable_id);
        $this->assertStringContainsString('<dms-att@x>', (string) $document->description);
        $this->assertNotNull($document->current_version_id);
        $this->assertSame(1, ExternalReference::query()
            ->where('external_type', MailInboxResolutionService::DMS_EXTERNAL_TYPE)
            ->count());
    }

    public function test_poll_command_ingests_and_marks_processed(): void {
        $connection = $this->connection();
        $fake = new FakeMailboxGateway([$this->message('<cmd@x>', 'a@b.test', uid: 42)]);
        $this->app->instance(MailboxGateway::class, $fake);

        $exit = \Illuminate\Support\Facades\Artisan::call('mail:poll', ['--organization' => (string) $this->organization->id]);
        $this->assertSame(0, $exit);

        $this->assertSame(1, IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->count());
        $this->assertContains(42, $fake->processedUids);
        $connection->refresh();
        $this->assertNotNull($connection->last_polled_at);
    }
}
