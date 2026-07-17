<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeedRoles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Whistleblowing;

use App\Console\Commands\SeedRolesCommand;
use App\Models\Organization;
use App\Services\Whistleblowing\WhistleblowingPermissions;

/**
 * Backfill: legt die Rolle `meldestelle` + WB-Permissions fuer bestehende
 * Organisationen an. Neue Orgs erhalten sie automatisch ueber den
 * OrganizationObserver; dieser Befehl deckt Orgs ab, die vor der Modul-
 * Einfuehrung existierten. Idempotent – mehrfach ausfuehrbar.
 */
class SeedRoles extends SeedRolesCommand {
    protected $signature = 'whistleblowing:seed-roles {organization? : Optionale Org-ID; ohne Angabe alle}';

    protected $description = 'Legt die Meldestellen-Rolle + Permissions fuer bestehende Organisationen an (Backfill).';

    protected function seedOrganization(Organization $organization): void {
        WhistleblowingPermissions::seedOrganization($organization);
    }

    protected function roleName(): string {
        return WhistleblowingPermissions::ROLE_MELDESTELLE;
    }
}
