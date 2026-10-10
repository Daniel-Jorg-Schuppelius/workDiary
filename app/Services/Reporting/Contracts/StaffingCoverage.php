<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StaffingCoverage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Contracts;

use Carbon\CarbonImmutable;

/** Soll- und Ist-Besetzung der Schichten (MVP-1092), gebunden vom Dienstplan. */
interface StaffingCoverage {
    /**
     * @param  list<int>  $userIds  leere Liste = alle
     * @return array<string, array<int, array{min: int, actual: int}>> date → shift_type_id
     */
    public function staffingBetween(CarbonImmutable $from, CarbonImmutable $to, array $userIds = []): array;
}
