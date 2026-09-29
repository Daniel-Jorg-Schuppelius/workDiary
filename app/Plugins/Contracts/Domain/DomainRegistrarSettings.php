<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainRegistrarSettings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts\Domain;

/** Betriebsgrenzen eines Registrars, die die Kern-Dienste einhalten (MVP-1043). */
final readonly class DomainRegistrarSettings {
    public function __construct(
        public int $checkBudgetPerHour,
        public int $checkCacheTtl,
        public int $listPageSize,
        public int $staleAfterHours,
    ) {}
}
