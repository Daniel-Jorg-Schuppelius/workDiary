<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeedPersonnelFileRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Hr;

use App\Events\Platform\OrganizationCreated;
use App\Services\Hr\PersonnelFilePermissions;

/** Personalakten-Kreis (Feature 141): hrFile.* nie automatisch an Admins. */
final class SeedPersonnelFileRole {
    public function handle(OrganizationCreated $event): void {
        PersonnelFilePermissions::seedOrganization($event->organization);
    }
}
