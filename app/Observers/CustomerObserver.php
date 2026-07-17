<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Models\Customer;

/**
 * Nur Fachlogik — Audit-Logging läuft ausschließlich über das Auditable-Trait
 * (sonst doppelte audit_logs-Zeilen, Konsolidierungs-Audit A1).
 */
class CustomerObserver {
    public function created(Customer $customer): void {
        // Standardprojekt automatisch anlegen, damit Ad-hoc-/Notfallaufträge
        // sofort einen sauberen Container für Stundenzettel/Zeiteinträge haben.
        $customer->defaultProjectOrCreate();
    }
}
