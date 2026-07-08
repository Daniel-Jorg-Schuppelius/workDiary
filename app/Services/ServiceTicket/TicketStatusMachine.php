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
        // Warten nur aus triaged/in_progress; zurück NUR nach in_progress
        // (Feature 065 — additiv, Bestandsübergänge unverändert).
        'triaged' => ['scheduled', 'in_progress', 'rejected', 'waiting_customer', 'waiting_external', 'paused'],
        'scheduled' => ['in_progress', 'triaged', 'rejected'],
        'in_progress' => ['done', 'scheduled', 'rejected', 'waiting_customer', 'waiting_external', 'paused'],
        'waiting_customer' => ['in_progress'],
        'waiting_external' => ['in_progress'],
        'paused' => ['in_progress'],
        // done→in_progress bleibt die Wiederöffnung; accepted/closed
        // öffnen ebenfalls nur nach in_progress (mit Pflichtgrund im Service).
        'done' => ['accepted', 'in_progress', 'closed'],
        'accepted' => ['closed', 'in_progress'],
        'closed' => ['in_progress'],
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
