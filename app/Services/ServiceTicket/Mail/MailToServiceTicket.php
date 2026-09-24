<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailToServiceTicket.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Mail;

use App\Models\Customer\Customer;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\User;
use App\Services\Integration\InboxActionService;
use App\Services\Mail\MailIntakeService;
use RuntimeException;

/** Mail-Inbox-Eintrag als Service-Ticket buchen (MVP-343, Source E-Mail); Erst-Nachricht trägt die Message-ID fürs Threading. */
class MailToServiceTicket {
    public function __construct(private readonly InboxActionService $actions) {}

    /**
     * „Als Service-Ticket buchen" (Feature 065, P2): erzeugt aus einem
     * Mail-Inbox-Eintrag ein Ticket in der Queue des Eingangspostfachs
     * (Source email) samt Erst-Nachricht mit Message-ID fürs Threading.
     */
    public function bookAsServiceTicket(IntegrationInboxItem $item, ?Customer $customer, User $actor, ?\App\Models\ServiceTicket\ServiceQueue $queue = null): \App\Models\ServiceTicket\ServiceTicket {
        if ($item->plugin_id !== MailIntakeService::PLUGIN_ID) {
            throw new RuntimeException('Kein E-Mail-Inbox-Eintrag.');
        }

        $snapshot = $item->remote_snapshot ?? [];
        $queueId = $queue->id ?? (isset($snapshot['service_queue_id']) ? (int) $snapshot['service_queue_id'] : null);

        $ticket = app(\App\Services\ServiceTicket\ServiceTicketService::class)->create(
            $item->organization()->firstOrFail(),
            $actor,
            array_filter([
                'title' => (string) ($snapshot['subject'] ?? $item->display_title ?? __('Neues Ticket')),
                'description' => (string) ($snapshot['body'] ?? ''),
                'customer_id' => $customer?->id,
                'queue_id' => $queueId,
                'source' => \App\Enums\ServiceTicket\ServiceTicketSource::Email->value,
                'source_reference' => (string) ($snapshot['mail_message_id'] ?? $item->external_id ?? ''),
            ], fn($value) => $value !== null && $value !== ''),
        );

        // Erst-Nachricht mit Message-ID — spätere Antworten threaden hierauf.
        \App\Models\ServiceTicket\ServiceTicketMessage::query()->create([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'kind' => \App\Enums\ServiceTicket\TicketMessageKind::PublicReply->value,
            'subject' => (string) ($snapshot['subject'] ?? ''),
            'body' => trim(strip_tags((string) ($snapshot['body'] ?? ''))),
            'channel' => 'mail',
            'message_id' => (string) ($snapshot['mail_message_id'] ?? $item->external_id ?? '') ?: null,
        ]);

        $this->actions->assignTo($item, $ticket);

        return $ticket;
    }
}
