<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentCollectionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{ContentCollection, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Sammlungen (MVP-809). Die Policy regelt nur die Sammlung selbst; was darin
 * liegt, prüft jeder Inhalt für sich. Private Sammlungen prüft der Controller
 * zusätzlich ausdrücklich — der Admin-Bypass darf sie nicht öffnen.
 */
class ContentCollectionPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::CollectionViewAny->value);
    }

    public function view(User $user, ContentCollection $collection): bool {
        return $this->viewAny($user) && $collection->isVisibleTo($user);
    }

    public function create(User $user): bool {
        return $user->can(P::CollectionManage->value);
    }

    public function update(User $user, ContentCollection $collection): bool {
        return $user->can(P::CollectionManage->value) && $collection->isVisibleTo($user);
    }
}
