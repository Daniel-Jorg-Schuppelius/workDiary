<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketThreadMailIntakeHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Mail;

use App\Models\Customer\Customer;
use App\Models\Mail\EmailConnection;
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\{ServiceQueue, ServiceTicket, ServiceTicketMessage};
use App\Services\Mail\Contracts\MailIntakeHandler;
use App\Services\Mail\{MailAttachmentStore, ParsedMessage};
use App\Services\ServiceTicket\TicketConversationService;
use CommonToolkit\Helper\Data\EmailHelper;

/**
 * Ticket-Pipeline des Queue-Postfachs (Feature 065, P2): Antworten auf ein
 * bestehendes Ticket (In-Reply-To/References oder Ticket-Nr. im Betreff mit
 * passendem Absender) landen im Verlauf statt in der Inbox.
 */
final class TicketThreadMailIntakeHandler implements MailIntakeHandler {
    public function __construct(
        private readonly TicketConversationService $conversation,
        private readonly MailAttachmentStore $attachments,
    ) {}

    public function priority(): int {
        return 40;
    }

    public function handle(Organization $organization, EmailConnection $connection, ParsedMessage $message): ?string {
        $hasQueue = ServiceQueue::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->where('email_connection_id', $connection->id)
            ->exists();
        if (! $hasQueue) {
            return null;
        }
        $ticket = $this->matchThreadedTicket($organization, $message);
        if ($ticket === null) {
            return null;
        }

        $seen = ServiceTicketMessage::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('message_id', $message->messageId)
            ->exists();
        if ($seen) {
            return 'skipped';
        }
        $ticketMessage = $this->conversation->inbound($ticket, $message->body, 'mail', $message->messageId, $message->inReplyTo, $message->subject);

        // Anhänge der Kundenmail (MVP-152): dieselbe Whitelist-/Größen-Policy wie beim Inbox-Intake, idempotent kopiert.
        if ($message->attachments !== []) {
            $this->conversation->attachStoredMailAttachments($ticketMessage, $this->attachments->persistFromMessage($organization, $message));
        }

        return 'ticket_message';
    }

    /**
     * Threading (Feature 065, P2): In-Reply-To/References gegen bekannte
     * Ticket-Nachrichten, dann Ticket-Nummer im Betreff ([TICKET-NO]).
     * Nur org-eigene Treffer — fremde Message-IDs (Spoofing) laufen ins Leere.
     */
    private function matchThreadedTicket(Organization $organization, ParsedMessage $message): ?ServiceTicket {
        $referencedIds = array_values(array_filter([$message->inReplyTo, ...$message->references]));
        if ($referencedIds !== []) {
            $known = ServiceTicketMessage::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereIn('message_id', $referencedIds)
                ->orderByDesc('id')
                ->first();
            if ($known !== null) {
                return $known->ticket()->withoutGlobalScopes()->first();
            }
        }

        if (preg_match('/\[([A-Z0-9\-\/]{4,30})\]/i', $message->subject, $matches) === 1) {
            $ticket = ServiceTicket::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('ticket_no', $matches[1])
                ->first();

            // Ticketnummern sind fortlaufend und stehen in jeder Kundenmail:
            // ohne Absenderprüfung konnte jeder Fremde eine öffentliche
            // „Kundenantwort" samt kundensichtbarem Anhang in ein beliebiges
            // Ticket schreiben (Sicherheitsaudit 2026-09-17, ingress-1).
            // Passt der Absender nicht zum Vorgang, läuft die Mail in die
            // normale Inbox statt in den Verlauf.
            if ($ticket !== null && $this->senderBelongsToTicket($ticket, $message)) {
                return $ticket;
            }
        }

        return null;
    }

    /**
     * Gehört die Absenderadresse zu diesem Vorgang? Anerkannt sind die Adresse
     * des Kunden, seiner Ansprechpartner und Portalzugänge, die meldende Person
     * sowie jede Adresse, die im Verlauf schon angeschrieben wurde.
     */
    private function senderBelongsToTicket(ServiceTicket $ticket, ParsedMessage $message): bool {
        $sender = EmailHelper::normalize((string) $message->fromEmail);
        if ($sender === '') {
            return false;
        }

        $known = [];

        $customer = $ticket->customer_id !== null
            ? Customer::query()->withoutGlobalScopes()->whereKey($ticket->customer_id)->first()
            : null;
        if ($customer !== null) {
            $known[] = (string) $customer->email;
            foreach ($customer->contact_persons ?? [] as $person) {
                $known[] = (string) ($person['email'] ?? '');
            }

            foreach (User::query()->withoutGlobalScopes()
                ->where('organization_id', $ticket->organization_id)
                ->where('customer_id', $customer->getKey())
                ->pluck('email') as $portalEmail) {
                $known[] = (string) $portalEmail;
            }
        }

        if ($ticket->reported_by_user_id !== null) {
            $known[] = (string) User::query()->withoutGlobalScopes()
                ->whereKey($ticket->reported_by_user_id)->value('email');
        }

        // Empfänger früherer Nachrichten des Vorgangs: wer bereits angeschrieben
        // wurde, darf antworten (auch ohne Stammdatensatz).
        foreach (ServiceTicketMessage::query()->withoutGlobalScopes()
            ->where('service_ticket_id', $ticket->getKey())
            ->get(['to', 'cc']) as $previous) {
            foreach ([...(array) ($previous->to ?? []), ...(array) ($previous->cc ?? [])] as $recipient) {
                $known[] = (string) $recipient;
            }
        }

        foreach ($known as $candidate) {
            $candidate = EmailHelper::normalize($candidate);
            if ($candidate !== '' && hash_equals($candidate, $sender)) {
                return true;
            }
        }

        return false;
    }
}
