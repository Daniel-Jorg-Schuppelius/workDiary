<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketPriority.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ServiceTicketPriority: string implements HasLabel {
    use HasOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string {
        return match ($this) {
            self::Low => __('enums.service_ticket.service_ticket_priority.low'),
            self::Normal => __('enums.service_ticket.service_ticket_priority.normal'),
            self::High => __('enums.service_ticket.service_ticket_priority.high'),
            self::Urgent => __('enums.service_ticket.service_ticket_priority.urgent'),
        };
    }
}
