<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeedDataProtectionRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Privacy;

use App\Events\Platform\OrganizationCreated;
use App\Services\Privacy\DataProtectionPermissions;

/** Eigene Datenschutz-Rolle (vom Admin getrennt) — unabhängig von der Modulaktivierung, damit eine spätere Freischaltung keine Rollen nachziehen muss. */
final class SeedDataProtectionRole {
    public function handle(OrganizationCreated $event): void {
        DataProtectionPermissions::seedOrganization($event->organization);
    }
}
