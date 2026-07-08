<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskConversationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\{ServiceTicketStatus, TicketMessageKind};
use App\Jobs\ServiceTicketReplyMailJob;
use App\Models\{EmailConnection, IntegrationInboxItem, Organization, ServiceQueue, ServiceTicket, ServiceTicketMessage, User};
use App\Services\Mail\{MailInboxResolutionService, MailIntakeService, ParsedMessage};
use App\Services\ServiceTicket\TicketConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Mail, Queue};
use Tests\TestCase;

/**
 * Feature 065, P2 (MVP-152): getrennte Antwort/Notiz (Typfrage), harte
 * Versand-Garantie („Notiz kann nie versendet werden"), Mail-Threading
 * (In-Reply-To + Betreff-Ticket-No, Dedup, Spoofing läuft ins Leere),
 * bookAsServiceTicket, Loop-Schutz-Flag.
 */
final class HelpdeskConversationTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
    }

    private function ticket(array $overrides = []): ServiceTicket {
        return ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'status' => ServiceTicketStatus::InProgress,
            'assigned_to_user_id' => $this->agent->id,
            ...$overrides,
        ]);
    }

    private function queueWithMailbox(): array {
        $connection = EmailConnection::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Support',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
            'folder' => 'INBOX',
            'active' => true,
        ]);
        $queue = ServiceQueue::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Support',
            'is_default' => true,
            'email_connection_id' => $connection->id,
        ]);

        return [$queue, $connection];
    }

    public function test_reply_and_note_use_separate_rights_and_types(): void {
        $ticket = $this->ticket();
        Queue::fake();

        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.reply', $ticket), [
                'body' => 'Wir haben das Problem behoben.',
                'to' => ['kunde@acme.test'],
            ])->assertRedirect(route('service-tickets.show', $ticket));

        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.note', $ticket), ['body' => 'Interner Verdacht: Netzteil.'])
            ->assertRedirect(route('service-tickets.show', $ticket));

        $reply = ServiceTicketMessage::query()->where('kind', 'public_reply')->firstOrFail();
        $note = ServiceTicketMessage::query()->where('kind', 'internal_note')->firstOrFail();
        $this->assertTrue($reply->kind->isCustomerVisible());
        $this->assertFalse($note->kind->isCustomerVisible());
        Queue::assertPushed(ServiceTicketReplyMailJob::class, 1);

        // Ohne Notiz-Recht: 403 (Mitglied ohne helpdesk.ticket.internal_note).
        $member = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($member)
            ->post(route('helpdesk.tickets.note', $ticket), ['body' => 'Leak-Versuch'])
            ->assertForbidden();
    }

    public function test_internal_note_can_never_be_sent(): void {
        Mail::fake();
        $ticket = $this->ticket();
        $note = app(TicketConversationService::class)->note($ticket, $this->agent, 'Geheim');

        $this->expectException(\RuntimeException::class);
        (new ServiceTicketReplyMailJob($note->id))->handle();
    }

    public function test_public_reply_is_sent_and_marked(): void {
        Mail::fake();
        $ticket = $this->ticket();
        $reply = app(TicketConversationService::class)->reply($ticket, $this->agent, 'Antwort', ['kunde@acme.test']);

        (new ServiceTicketReplyMailJob($reply->id))->handle();

        $this->assertSame('sent', $reply->fresh()->delivery_status);
    }

    public function test_mail_threading_attaches_to_ticket_and_resumes_waiting(): void {
        [, $connection] = $this->queueWithMailbox();
        $service = app(\App\Services\ServiceTicket\ServiceTicketService::class);
        $ticket = $this->ticket();
        $ticket = $service->wait($ticket, $this->agent, ServiceTicketStatus::WaitingCustomer, 'Info fehlt', now()->addDay());

        ServiceTicketMessage::query()->create([
            'organization_id' => $this->org->id,
            'service_ticket_id' => $ticket->id,
            'kind' => TicketMessageKind::PublicReply->value,
            'body' => 'Unsere Rückfrage',
            'channel' => 'mail',
            'message_id' => '<frage-1@workdiary>',
        ]);

        $inbound = new ParsedMessage(
            messageId: '<antwort-1@kunde>',
            uid: 10,
            fromEmail: 'kunde@acme.test',
            fromName: 'Kunde',
            subject: 'Re: Rückfrage',
            body: 'Hier die Info.',
            receivedAt: Carbon::now(),
            inReplyTo: '<frage-1@workdiary>',
        );

        $result = app(MailIntakeService::class)->intake($this->org, $connection, $inbound);
        $this->assertSame('ticket_message', $result);
        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->fresh()->status);
        $this->assertSame(2, ServiceTicketMessage::query()->where('service_ticket_id', $ticket->id)->count());

        // Dedup: dieselbe Message-ID erneut → skipped, keine Dublette.
        $this->assertSame('skipped', app(MailIntakeService::class)->intake($this->org, $connection, $inbound));
        $this->assertSame(2, ServiceTicketMessage::query()->where('service_ticket_id', $ticket->id)->count());
    }

    public function test_spoofed_foreign_message_id_does_not_thread(): void {
        [, $connection] = $this->queueWithMailbox();

        // Message-ID existiert nur in einer FREMDEN Org.
        $otherOrg = Organization::factory()->create();
        $foreignTicket = ServiceTicket::factory()->create(['organization_id' => $otherOrg->id]);
        ServiceTicketMessage::query()->create([
            'organization_id' => $otherOrg->id,
            'service_ticket_id' => $foreignTicket->id,
            'kind' => TicketMessageKind::PublicReply->value,
            'body' => 'fremd',
            'channel' => 'mail',
            'message_id' => '<fremd-1@anderswo>',
        ]);

        $inbound = new ParsedMessage(
            messageId: '<spoof-1@kunde>',
            uid: 11,
            fromEmail: 'angreifer@evil.test',
            fromName: 'Spoofer',
            subject: 'Re: irgendwas',
            body: 'Spoofing-Versuch',
            receivedAt: Carbon::now(),
            inReplyTo: '<fremd-1@anderswo>',
        );

        $result = app(MailIntakeService::class)->intake($this->org, $connection, $inbound);
        $this->assertSame('created', $result, 'Kein Threading auf fremde Org — Inbox-First.');
        $this->assertSame(0, ServiceTicketMessage::query()->where('service_ticket_id', $foreignTicket->id)->where('message_id', '<spoof-1@kunde>')->count());
    }

    public function test_api_ticket_intake_is_org_bound(): void {
        \Laravel\Sanctum\Sanctum::actingAs($this->agent, ['tickets:write']);

        $response = $this->postJson('/api/tickets', [
            'title' => 'API-Störung',
            'kind' => 'incident',
        ])->assertCreated();

        $ticket = ServiceTicket::query()->where('title', 'API-Störung')->firstOrFail();
        $this->assertSame('api', $ticket->source->value);
        $this->assertSame((int) $this->org->id, (int) $ticket->organization_id);
        $this->assertSame($response->json('ticket_no'), $ticket->ticket_no);

        // Ohne Ability → 403.
        \Laravel\Sanctum\Sanctum::actingAs($this->agent, ['diary:read']);
        $this->postJson('/api/tickets', ['title' => 'Verboten'])->assertForbidden();
    }

    public function test_ticket_show_renders_conversation(): void {
        $ticket = $this->ticket();
        app(TicketConversationService::class)->reply($ticket, $this->agent, 'Öffentliche Antwort');
        app(TicketConversationService::class)->note($ticket, $this->agent, 'Nur intern sichtbar');

        $this->actingAs($this->agent)
            ->get(route('service-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Öffentliche Antwort')
            ->assertSee('Nur intern sichtbar')
            ->assertSee(__('Interne Notiz'));
    }

    public function test_book_inbox_item_as_service_ticket(): void {
        [$queue, $connection] = $this->queueWithMailbox();

        $inbound = new ParsedMessage(
            messageId: '<neu-1@kunde>',
            uid: 12,
            fromEmail: 'neu@acme.test',
            fromName: 'Neukunde',
            subject: 'Drucker defekt',
            body: 'Der Drucker raucht.',
            receivedAt: Carbon::now(),
            isAutoSubmitted: true, // Loop-Schutz-Flag landet im Snapshot
        );
        $this->assertSame('created', app(MailIntakeService::class)->intake($this->org, $connection, $inbound));

        $item = IntegrationInboxItem::query()->firstOrFail();
        $this->assertTrue((bool) ($item->remote_snapshot['auto_submitted'] ?? false));

        $ticket = app(MailInboxResolutionService::class)->bookAsServiceTicket($item, null, $this->agent);

        $this->assertSame('Drucker defekt', $ticket->title);
        $this->assertSame((int) $queue->id, (int) $ticket->queue_id);
        $this->assertSame('email', $ticket->source->value);
        $this->assertSame(1, ServiceTicketMessage::query()->where('service_ticket_id', $ticket->id)->where('message_id', '<neu-1@kunde>')->count());
        $this->assertNotSame(IntegrationInboxItem::STATUS_OPEN, $item->fresh()->status);
    }
}
