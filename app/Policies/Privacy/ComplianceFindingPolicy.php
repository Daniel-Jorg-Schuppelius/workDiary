<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceFindingPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Privacy;

use App\Models\Privacy\ComplianceFinding;
use App\Models\User;

/** Lueckenanalyse: lesen mit dataprotection.view, entscheiden mit compliance.manage. */
class ComplianceFindingPolicy {
    public function viewAny(User $user): bool {
        return $user->can('dataprotection.view');
    }

    public function manage(User $user): bool {
        return $user->can('dataprotection.compliance.manage');
    }

    public function update(User $user, ComplianceFinding $finding): bool {
        return (int) $user->organization_id === (int) $finding->organization_id
            && $user->can('dataprotection.compliance.manage');
    }
}
