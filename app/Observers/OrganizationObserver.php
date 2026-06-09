<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Models\Organization;
use App\Services\Whistleblowing\WhistleblowingPermissions;
use Database\Seeders\PermissionsSeeder;

/**
 * Sorgt dafür, dass jede neu angelegte Organisation sofort über die
 * vollständige Menge an Default-Rollen verfügt. Verhindert, dass ein
 * frisch registriertes Tenant ohne brauchbare Rollen dasteht.
 */
class OrganizationObserver {
    public function created(Organization $organization): void {
        PermissionsSeeder::seedOrganization($organization);
        // Eigene, vom Plattform-Admin getrennte Meldestelle-Rolle (Abschnitt 5/25).
        WhistleblowingPermissions::seedOrganization($organization);
    }
}
