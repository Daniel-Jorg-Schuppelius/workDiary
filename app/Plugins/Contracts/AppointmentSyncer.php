<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentSyncer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Models\Organization;

/**
 * Plugins mit dieser Fähigkeit ({@see PluginCapability::AppointmentSync})
 * empfangen extern gebuchte Termine (z. B. Calendly) als
 * bestätigungspflichtige Terminwünsche in die Disposition (Feature 095).
 * Der Webhook ist nur Impuls; dieser Sync-Einstieg gleicht per Polling nach
 * (Backfill/Reconciliation) und ist damit die verlässliche Quelle bei
 * verpassten Zustellungen. Nichts wird blind angelegt: Unzuordenbares landet
 * in der Zuordnungs-Inbox, kein Externer schreibt direkt in den Dienstplan.
 */
interface AppointmentSyncer {
    /**
     * @return array<string, int|string>  z. B. ['created' => 2, 'updated' => 1, 'skipped' => 4, 'unmatched' => 1]
     */
    public function syncAppointments(Organization $organization): array;
}
