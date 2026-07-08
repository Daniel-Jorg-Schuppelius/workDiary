<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketConversationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\{ServiceTicketStatus, TicketMessageKind};
use App\Jobs\ServiceTicketReplyMailJob;
use App\Models\{ServiceTicket, ServiceTicketMessage, User};
use Illuminate\Support\Facades\DB;

/**
 * Konversation am Ticket (Feature 065, MVP-152). Öffentlich vs. intern
 * ist eine TYPFRAGE: reply() erzeugt public_reply (einzig versandfähig),
 * note() erzeugt internal_note (nie kundensichtbar, nie versendbar) —
 * getrennte Methoden, getrennte Rechte, getrennte Routen.
 */
class TicketConversationService {
    /**
     * Öffentliche Antwort — landet beim Kunden (Versand-Job nur hier).
     *
     * @param array<int, string> $to
     */
    public function reply(ServiceTicket $ticket, User $author, string $body, array $to = [], ?string $subject = null, string $channel = 'manual'): ServiceTicketMessage {
        $body = trim(strip_tags($body));
        if ($body === '') {
            throw new \InvalidArgumentException((string) __('Die Antwort braucht einen Inhalt.'));
        }

        return DB::transaction(function () use ($ticket, $author, $body, $to, $subject, $channel): ServiceTicketMessage {
            $message = ServiceTicketMessage::query()->create([
                'organization_id' => $ticket->organization_id,
                'service_ticket_id' => $ticket->id,
                'kind' => TicketMessageKind::PublicReply->value,
                'author_type' => $author->getMorphClass(),
                'author_id' => $author->id,
                'to' => $to !== [] ? array_values($to) : null,
                'subject' => $subject,
                'body' => $body,
                'channel' => $channel,
                'delivery_status' => $to !== [] ? 'queued' : null,
            ]);

            $ticket->audit('service_ticket.replied', ['message_id' => $message->id]);

            if ($to !== []) {
                ServiceTicketReplyMailJob::dispatch($message->id);
            }

            return $message;
        });
    }

    /** Interne Notiz — nie kundensichtbar, nie versendbar (Typgarantie). */
    public function note(ServiceTicket $ticket, User $author, string $body): ServiceTicketMessage {
        $body = trim(strip_tags($body));
        if ($body === '') {
            throw new \InvalidArgumentException((string) __('Die Notiz braucht einen Inhalt.'));
        }

        $message = ServiceTicketMessage::query()->create([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'kind' => TicketMessageKind::InternalNote->value,
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->id,
            'body' => $body,
            'channel' => 'manual',
        ]);

        $ticket->audit('service_ticket.noted', ['message_id' => $message->id]);

        return $message;
    }

    /** Systemereignis (Statuswechsel, SLA, Zuordnung) für die Timeline. */
    public function systemEvent(ServiceTicket $ticket, string $body): ServiceTicketMessage {
        return ServiceTicketMessage::query()->create([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'kind' => TicketMessageKind::SystemEvent->value,
            'body' => $body,
            'channel' => 'system',
        ]);
    }

    /**
     * Eingehende Kundennachricht (Mail/Portal): anhängen; ein wartendes
     * Ticket geht zurück in Bearbeitung (waiting → in_progress).
     */
    public function inbound(ServiceTicket $ticket, string $body, string $channel, ?string $messageId = null, ?string $inReplyTo = null, ?string $subject = null, ?object $author = null): ServiceTicketMessage {
        $message = ServiceTicketMessage::query()->create([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'kind' => TicketMessageKind::PublicReply->value,
            'author_type' => $author instanceof \Illuminate\Database\Eloquent\Model ? $author->getMorphClass() : null,
            'author_id' => $author instanceof \Illuminate\Database\Eloquent\Model ? $author->getKey() : null,
            'subject' => $subject,
            'body' => trim(strip_tags($body)),
            'channel' => $channel,
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
        ]);

        // ticket.customerReplied (P3): Bearbeiter informieren.
        if ($ticket->assigned_to_user_id !== null) {
            $assignee = \App\Models\User::query()->find($ticket->assigned_to_user_id);
            if ($assignee !== null) {
                app(\App\Services\Notification\NotificationDispatcher::class)->notify(
                    \App\Enums\Notification\NotificationEvent::TicketCustomerReplied,
                    $ticket,
                    $assignee,
                    [
                        'title' => (string) __('Kundenantwort zu Ticket :no', ['no' => $ticket->ticket_no]),
                        'body' => mb_substr($message->body, 0, 200),
                        'url' => route('service-tickets.show', $ticket),
                    ],
                    dedup: false,
                );
            }
        }

        if ($ticket->status->isWaiting()) {
            // Kundenreaktion beendet das Warten — zurück in Bearbeitung.
            $ticket->forceFill([
                'status' => ServiceTicketStatus::InProgress->value,
                'wait_reason' => null,
                'wait_until' => null,
                'wait_owner_id' => null,
            ])->save();
            $ticket->audit('service_ticket.resumed', ['origin' => 'inbound_message']);
        }

        return $message;
    }
}
