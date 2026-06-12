<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSoftwareProductPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsSoftwareProduct;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln Softwareinventar (Feature 044): wiederverwendet die
 * bestehenden ISMS-Permissions — Lesen mit isms.viewAny/isms.view,
 * Pflege nur mit isms.manage (Standard: nur admin). KEINE eigenen
 * Software-Permissions.
 */
class IsmsSoftwareProductPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::IsmsViewAny->value);
    }

    public function view(User $user, IsmsSoftwareProduct $product): bool {
        return $user->can(P::IsmsView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function update(User $user, IsmsSoftwareProduct $product): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function delete(User $user, IsmsSoftwareProduct $product): bool {
        return $user->can(P::IsmsManage->value);
    }
}
