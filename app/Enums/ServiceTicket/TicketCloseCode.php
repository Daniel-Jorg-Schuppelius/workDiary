<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketCloseCode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Abschlusscode (Feature 065, MVP-151): WIE wurde das Ticket beendet. */
enum TicketCloseCode: string implements HasLabel {
    use HasOptions;

    case Solved = 'solved';
    case Workaround = 'workaround';
    case Duplicate = 'duplicate';
    case NoFault = 'no_fault';
    case Rejected = 'rejected';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Solved => (string) __('enums.service_ticket.ticket_close_code.solved'),
            self::Workaround => (string) __('enums.service_ticket.ticket_close_code.workaround'),
            self::Duplicate => (string) __('enums.service_ticket.ticket_close_code.duplicate'),
            self::NoFault => (string) __('enums.service_ticket.ticket_close_code.no_fault'),
            self::Rejected => (string) __('enums.service_ticket.ticket_close_code.rejected'),
            self::Other => (string) __('enums.service_ticket.ticket_close_code.other'),
        };
    }
}
