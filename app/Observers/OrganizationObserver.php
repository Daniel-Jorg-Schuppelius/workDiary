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
use App\Services\Licensing\PlanModuleService;
use App\Services\Privacy\DataProtectionPermissions;
use App\Services\Whistleblowing\WhistleblowingPermissions;
use Database\Seeders\{EntryTypeSeeder, PermissionsSeeder};

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
        // Eigene Datenschutz-Rolle (ebenfalls vom Admin getrennt).
        DataProtectionPermissions::seedOrganization($organization);
        // Erstausstattung Eintragstypen (profil-gekoppelt) — der Deploy-Seeder
        // fasst bestehende Orgs bewusst nicht mehr an.
        EntryTypeSeeder::seedOrganization($organization);
    }

    /**
     * Plan-Wechsel → Downgrade-/Karenz-Lebenszyklus pflegen. Verlorene Module
     * bekommen eine Karenzfrist, neu gewonnene heben offene Karenz auf.
     */
    public function updated(Organization $organization): void {
        if ($organization->wasChanged('plan')) {
            app(PlanModuleService::class)->handlePlanChange(
                $organization,
                (string) $organization->getOriginal('plan'),
                (string) $organization->plan,
            );
        }
    }
}
