<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterReadingPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{MeterReading, User};
use App\Policies\Concerns\HasAdminBypass;

class MeterReadingPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::MeterReadingView->value);
    }

    public function view(User $user, MeterReading $reading): bool {
        return $user->can(P::MeterReadingView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::MeterReadingRecord->value);
    }

    public function record(User $user): bool {
        return $user->can(P::MeterReadingRecord->value);
    }
}
