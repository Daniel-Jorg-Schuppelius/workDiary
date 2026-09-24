<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullClaimIntake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims\Contracts;

use App\Models\Claims\ClaimCase;
use App\Models\Platform\{Organization, User};
use App\Modules\ModuleUnavailableException;

final class NullClaimIntake implements ClaimIntake {
    public function open(Organization $organization, User $creator, array $attributes): ClaimCase {
        throw ModuleUnavailableException::for('claims');
    }
}
