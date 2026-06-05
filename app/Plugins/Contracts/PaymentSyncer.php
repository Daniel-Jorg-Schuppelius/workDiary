<?php
/*
 * Created on   : Fri Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentSyncer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Models\Organization;

/**
 * Plugins mit dieser Fähigkeit ({@see PluginCapability::PaymentSync}) lesen
 * Zahlungs-/Abgleichdaten aus dem externen System zurück (z. B. Rechnungsstatus).
 * Gibt eine Ergebnis-Statistik zurück.
 */
interface PaymentSyncer {
    /**
     * @return array<string, int|string>
     */
    public function syncPayments(Organization $organization): array;
}
