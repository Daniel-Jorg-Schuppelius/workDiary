<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeedRoles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Hr;

use App\Console\Commands\SeedRolesCommand;
use App\Models\Organization;
use App\Services\Hr\PersonnelFilePermissions;

/**
 * Backfill: legt die Rolle `personalakte` + hrFile-Permissions für bestehende
 * Organisationen an (Feature 141). Neue Orgs erhalten sie über den
 * OrganizationObserver. Idempotent.
 */
class SeedRoles extends SeedRolesCommand {
    protected $signature = 'personalakte:seed-roles {organization? : Optionale Org-ID; ohne Angabe alle}';

    protected $description = 'Legt die Personalakten-Rolle + Permissions für bestehende Organisationen an (Backfill).';

    protected function seedOrganization(Organization $organization): void {
        PersonnelFilePermissions::seedOrganization($organization);
    }

    protected function roleName(): string {
        return PersonnelFilePermissions::ROLE_PERSONALAKTE;
    }
}
