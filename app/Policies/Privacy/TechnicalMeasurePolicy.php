<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TechnicalMeasurePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Privacy;

use App\Models\Privacy\TechnicalMeasure;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;

/** TOM-Katalog. Ohne Admin-Bypass, organisationsgebunden. */
class TechnicalMeasurePolicy {
    use ChecksOwnership;

    public function viewAny(User $user): bool {
        return $user->can('dataprotection.view');
    }

    public function view(User $user, TechnicalMeasure $measure): bool {
        return $this->sharesOrganization($user, $measure) && $user->can('dataprotection.view');
    }

    public function create(User $user): bool {
        return $user->can('dataprotection.tom.manage');
    }

    public function update(User $user, TechnicalMeasure $measure): bool {
        return $this->sharesOrganization($user, $measure) && $user->can('dataprotection.tom.manage');
    }
}
