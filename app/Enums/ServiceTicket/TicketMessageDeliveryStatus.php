<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketMessageDeliveryStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\ServiceTicket;

use App\Enums\Contracts\HasLabel;

/** Versandstand einer Ticket-Antwort per E-Mail; ohne Empfänger bleibt die Spalte leer. */
enum TicketMessageDeliveryStatus: string implements HasLabel {
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('enums.service_ticket.ticket_message_delivery_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Queued => 'ghost',
            self::Sent => 'success',
            self::Failed => 'error',
        };
    }
}
