<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Product, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Produktmodell (MVP-369): Admin via Bypass; Pflege über product.viewAny/
 * product.manage — gleiche Zielgruppe wie der Artikelstamm.
 */
class ProductPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ProductViewAny->value);
    }

    public function view(User $user, Product $product): bool {
        unset($product);

        return $user->can(P::ProductViewAny->value);
    }

    public function create(User $user): bool {
        return $user->can(P::ProductManage->value);
    }

    public function update(User $user, Product $product): bool {
        unset($product);

        return $user->can(P::ProductManage->value);
    }

    public function delete(User $user, Product $product): bool {
        unset($product);

        return $user->can(P::ProductManage->value);
    }
}
