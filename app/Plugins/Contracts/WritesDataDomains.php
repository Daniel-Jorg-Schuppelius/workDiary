<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WritesDataDomains.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Enums\Integration\DataDomain;

/**
 * Plugin, das in Bereiche der Datenführerschaft schreibt (MVP-1592). Nur diese
 * Plugins stehen in der Matrix je Bereich zur Wahl; ihre Schreibwege fragen
 * vorher den {@see \App\Services\Integration\DataOwnershipGuard}.
 */
interface WritesDataDomains {
    /** @return list<DataDomain> */
    public function dataDomains(): array;
}
