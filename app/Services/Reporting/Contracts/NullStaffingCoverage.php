<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullStaffingCoverage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Contracts;

use Carbon\CarbonImmutable;

final class NullStaffingCoverage implements StaffingCoverage {
    public function staffingBetween(CarbonImmutable $from, CarbonImmutable $to, array $userIds = []): array {
        return [];
    }
}
