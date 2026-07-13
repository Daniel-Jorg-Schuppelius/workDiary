<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskTimelineTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\SlaViolationKind;
use App\Models\{Customer, EmailConnection, ServiceQueue, ServiceTicket, ServiceTicketMessage, SlaViolation, User};
use App\Services\Mail\{MailAttachment, MailIntakeService, ParsedMessage};
use App\Services\ServiceTicket\TicketConversationService;
use App\Services\Timeline\ServiceTicketTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 065, MVP-152: ServiceTicketTimelineService mischt Konversation,
 * Status-Audits, SLA-Ereignisse und Anhänge chronologisch; forCustomer()
 * unterdrückt interne Notizen/Anhänge strukturell (Leak-Schutz); der
 * Mail-Eingang übernimmt Snapshot-Anhänge idempotent; Portal-Uploads
 * landen immer kundensichtbar.
 */
final class HelpdeskTimelineTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function ticket(array $overrides = []): ServiceTicket {
        return ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'assigned_to_user_id' => $this->agent->id,
            ...$overrides,
        ]);
    }

    public function test_timeline_merges_message_status_and_sla_items_chronologically(): void {
        $ticket = $this->ticket();
        $service = app(ServiceTicketTimelineService::class);

        $this->travelTo(Carbon::parse('2026-07-01 10:00:00'));
        app(TicketConversationService::class)->reply($ticket, $this->agent, 'Erste Antwort an den Kunden');

        $this->travelTo(Carbon::parse('2026-07-01 11:00:00'));
        $ticket->audit('service_ticket.status_changed', ['from' => 'new', 'to' => 'in_progress']);

        $this->travelTo(Carbon::parse('2026-07-01 12:00:00'));
        SlaViolation::query()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $ticket->id,
            'kind' => SlaViolationKind::ResponseTime->value,
            'breached_at' => now(),
            'overdue_minutes' => 42,
        ]);
        $this->travelBack();

        $timeline = $service->forTicket($ticket);

        // Chronologisch absteigend gemischt: SLA → Status → Nachricht.
        $types = array_map(static fn($item) => $item->type, $timeline['items']);
        $this->assertSame(['sla', 'status', 'message'], $types);
        $this->assertFalse($timeline['hasMore']);
        $this->assertSame(__('Status geändert'), $timeline['items'][1]->title);
        $this->assertStringContainsString('42', (string) $timeline['items'][0]->summary);

        // Typ-Filter liefert nur die gewünschte Quelle.
        $onlyMessages = $service->forTicket($ticket, ['message']);
        $this->assertCount(1, $onlyMessages['items']);
        $this->assertSame('message', $onlyMessages['items'][0]->type);
        $this->assertSame('Erste Antwort an den Kunden', $onlyMessages['items'][0]->summary);
    }

    public function test_for_customer_suppresses_internal_notes_and_internal_attachments(): void {
        $ticket = $this->ticket();
        $conversation = app(TicketConversationService::class);

        $reply = $conversation->reply($ticket, $this->agent, 'Öffentliche Antwort ans Portal');
        $note = $conversation->note($ticket, $this->agent, 'GEHEIME interne Notiz');

        $reply->attachments()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->agent->id,
            'disk' => 'local',
            'path' => 'attachments/x/oeffentlich.pdf',
            'original_name' => 'oeffentlich.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'customer_visible' => true,
        ]);
        $note->attachments()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->agent->id,
            'disk' => 'local',
            'path' => 'attachments/x/intern.pdf',
            'original_name' => 'intern.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'customer_visible' => false,
        ]);
        // Doppelter Riegel: selbst ein (fälschlich) freigegebener Anhang einer
        // internen Notiz erreicht das Portal nicht.
        $note->attachments()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->agent->id,
            'disk' => 'local',
            'path' => 'attachments/x/leak.pdf',
            'original_name' => 'leak.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'customer_visible' => true,
        ]);

        $customerTimeline = app(ServiceTicketTimelineService::class)->forCustomer($ticket);
        $summaries = implode("\n", array_map(static fn($item) => (string) $item->summary, $customerTimeline['items']));

        $this->assertStringContainsString('Öffentliche Antwort ans Portal', $summaries);
        $this->assertStringContainsString('oeffentlich.pdf', $summaries);
        $this->assertStringNotContainsString('GEHEIME interne Notiz', $summaries);
        $this->assertStringNotContainsString('intern.pdf', $summaries);
        $this->assertStringNotContainsString('leak.pdf', $summaries);

        // Interne Sicht sieht alles (Notiz + interne Anhänge).
        $internal = app(ServiceTicketTimelineService::class)->forTicket($ticket);
        $internalSummaries = implode("\n", array_map(static fn($item) => (string) $item->summary, $internal['items']));
        $this->assertStringContainsString('GEHEIME interne Notiz', $internalSummaries);
        $this->assertStringContainsString('intern.pdf', $internalSummaries);
    }

    public function test_mail_intake_attaches_snapshot_attachments_idempotently(): void {
        Storage::fake('local');

        $connection = EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
            'folder' => 'INBOX',
            'active' => true,
        ]);
        ServiceQueue::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'is_default' => true,
            'email_connection_id' => $connection->id,
        ]);

        $ticket = $this->ticket();
        ServiceTicketMessage::query()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $ticket->id,
            'kind' => \App\Enums\ServiceTicket\TicketMessageKind::PublicReply->value,
            'body' => 'Unsere Rückfrage',
            'channel' => 'mail',
            'message_id' => '<frage-9@workdiary>',
        ]);

        $inbound = new ParsedMessage(
            messageId: '<antwort-9@kunde>',
            uid: 30,
            fromEmail: 'kunde@acme.test',
            fromName: 'Kunde',
            subject: 'Re: Rückfrage',
            body: 'Anbei das Foto.',
            receivedAt: Carbon::now(),
            attachments: [
                new MailAttachment('fehlerbild.pdf', 'application/pdf', '%PDF-1.4 kaputt'),
                new MailAttachment('malware.exe', 'application/x-msdownload', 'MZ binaries'),
            ],
            inReplyTo: '<frage-9@workdiary>',
        );

        $this->assertSame('ticket_message', app(MailIntakeService::class)->intake($this->organization, $connection, $inbound));

        $message = ServiceTicketMessage::query()->where('message_id', '<antwort-9@kunde>')->firstOrFail();
        // Whitelist greift: PDF übernommen, ausführbare Datei verworfen.
        $this->assertSame(1, $message->attachments()->count());
        $attachment = $message->attachments()->firstOrFail();
        $this->assertSame('fehlerbild.pdf', $attachment->original_name);
        $this->assertTrue((bool) $attachment->customer_visible);
        Storage::disk('local')->assertExists($attachment->path);

        // Idempotent: dieselbe Mail erneut → skipped, keine Dublette.
        $this->assertSame('skipped', app(MailIntakeService::class)->intake($this->organization, $connection, $inbound));
        $this->assertSame(1, $message->attachments()->count());

        // Gürtel + Hosenträger: auch der Anhangs-Helfer selbst ist idempotent.
        $stored = [['stored' => true, 'disk' => 'local', 'stored_path' => $attachment->path, 'original_name' => 'fehlerbild.pdf', 'mime' => 'application/pdf', 'size' => $attachment->size]];
        $this->assertSame(0, app(TicketConversationService::class)->attachStoredMailAttachments($message, $stored));
        $this->assertSame(1, $message->attachments()->count());
    }

    public function test_portal_upload_is_customer_visible(): void {
        Storage::fake('local');

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $portalUser = User::factory()
            ->kunde((int) $customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);
        ServiceQueue::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Portal',
            'visibility' => 'portal',
        ]);

        $this->actingAs($portalUser, 'customer');
        $this->withoutMiddleware(\App\Http\Middleware\RequireTwoFactorSetup::class);

        // Anlage mit Datei: Anhang hängt am Ticket, kundensichtbar.
        $this->post(route('customer.tickets.store'), [
            'title' => 'Drucker qualmt',
            'description' => 'Seit heute Morgen.',
            'files' => [UploadedFile::fake()->image('qualm.png')],
        ])->assertRedirect();

        $ticket = ServiceTicket::query()->where('title', 'Drucker qualmt')->firstOrFail();
        $ticketAttachment = $ticket->attachments()->firstOrFail();
        $this->assertTrue((bool) $ticketAttachment->customer_visible);
        $this->assertSame('qualm.png', $ticketAttachment->original_name);
        $this->assertSame((int) $portalUser->id, (int) $ticketAttachment->user_id);

        // Antwort mit Datei: Anhang hängt an der Portal-Nachricht, kundensichtbar.
        $this->post(route('customer.tickets.reply', $ticket), [
            'body' => 'Hier noch ein Bild.',
            'files' => [UploadedFile::fake()->image('detail.png')],
        ])->assertRedirect();

        $message = ServiceTicketMessage::query()
            ->where('service_ticket_id', $ticket->id)
            ->where('body', 'Hier noch ein Bild.')
            ->firstOrFail();
        $messageAttachment = $message->attachments()->firstOrFail();
        $this->assertTrue((bool) $messageAttachment->customer_visible);
        $this->assertSame('detail.png', $messageAttachment->original_name);

        // Datei-Policy greift auch im Portal: nicht gelistete Typen → 422.
        $this->post(route('customer.tickets.reply', $ticket), [
            'body' => 'Böse Datei',
            'files' => [UploadedFile::fake()->create('malware.exe', 5, 'application/x-msdownload')],
        ])->assertSessionHasErrors('files');
    }
}
