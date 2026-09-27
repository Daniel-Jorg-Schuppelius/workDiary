<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DamageCasePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Damage;

use App\Enums\User\Permission as P;
use App\Models\Damage\DamageCase;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/** Schadensfälle (MVP-919): eigene Rechte; die Anlage am Träger prüft zusätzlich dessen Sicht. */
class DamageCasePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::DamageViewAny->value);
    }

    public function view(User $user, DamageCase $case): bool {
        return $user->can(P::DamageView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::DamageManage->value);
    }

    public function update(User $user, DamageCase $case): bool {
        return $user->can(P::DamageManage->value);
    }
}
