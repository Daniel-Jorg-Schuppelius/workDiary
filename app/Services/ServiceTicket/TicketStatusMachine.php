<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketStatusMachine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Exceptions\ServiceTicketException;

class TicketStatusMachine {
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'reported' => ['triaged', 'scheduled', 'in_progress', 'rejected'],
        'triaged' => ['scheduled', 'in_progress', 'rejected'],
        'scheduled' => ['in_progress', 'triaged', 'rejected'],
        'in_progress' => ['done', 'scheduled', 'rejected'],
        'done' => ['accepted', 'in_progress', 'closed'],
        'accepted' => ['closed'],
        'closed' => [],
        'rejected' => ['reported'],
    ];

    public function canTransition(ServiceTicketStatus $from, ServiceTicketStatus $to): bool {
        if ($from === $to) {
            return true;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }

    public function ensureTransition(ServiceTicketStatus $from, ServiceTicketStatus $to): void {
        if (! $this->canTransition($from, $to)) {
            throw ServiceTicketException::invalidStatusTransition($from->value, $to->value);
        }
    }
}
