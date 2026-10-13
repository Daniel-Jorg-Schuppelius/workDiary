<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullBillingRouteNumbers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Numbering\Contracts;

use App\Models\Platform\Organization;

/** Ohne Fakturierungsweg rechnet workDiary selbst ab. */
final class NullBillingRouteNumbers implements BillingRouteNumbers {
    public function externalProgram(Organization $organization): ?string {
        return null;
    }

    public function deviations(Organization $organization): array {
        return [];
    }
}
