<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EarlyWarningSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting\Contracts;

use App\Models\Platform\Organization;
use App\Services\Reporting\Dto\EarlyWarning;

/**
 * Erweiterungspunkt Frühwarnungen (MVP-889): ein Modul meldet auffällige
 * Kunden, Objekte oder Muster samt fester Handlungsempfehlung. Läuft mit
 * gebundener Organisation.
 */
interface EarlyWarningSource {
    public function key(): string;

    /** @return list<EarlyWarning> */
    public function warnings(Organization $organization): array;
}
