<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyTicketTrigger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Survey;

use App\Models\ServiceTicket\ServiceTicket;

/**
 * Anlass-Trigger „Ticketabschluss" (Feature 090, MVP-661).
 *
 * Fehlertoleranz ist hier Pflicht: Ein gescheiterter Einladungsversuch
 * (Opt-out, Ermüdungsschutz, fehlende Adresse, Mail-Fehler) darf den
 * Ticket-Statuswechsel NIE verhindern — er wird geloggt und übersprungen.
 */
class SurveyTicketTrigger {
    public function __construct(private readonly SurveyService $service) {}

    public function onTicketResolved(ServiceTicket $ticket): void {
        $customer = $ticket->customer;
        if ($customer === null) {
            return;
        }

        $this->service->inviteByTrigger((int) $ticket->organization_id, 'trigger_on_ticket_close', (string) $customer->email, $customer, 'ticket');
    }
}
