<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeedWhistleblowingRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Whistleblowing;

use App\Events\Platform\OrganizationCreated;
use App\Services\Whistleblowing\WhistleblowingPermissions;

/** Meldestelle-Rolle (Abschnitt 5/25), vom Plattform-Admin getrennt — unabhängig von der Modulaktivierung. */
final class SeedWhistleblowingRole {
    public function handle(OrganizationCreated $event): void {
        WhistleblowingPermissions::seedOrganization($event->organization);
    }
}
