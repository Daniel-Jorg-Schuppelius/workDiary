<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarehousePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{User, Warehouse};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Lagerorte (Feature 048, MVP-067): Sehen mit inventory.viewAny, Verwaltung mit
 * inventory.configure. Mandantengrenze trägt der OrganizationScope/Model-Binding.
 */
class WarehousePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::InventoryViewAny->value) || $user->can(P::InventoryConfigure->value);
    }

    public function view(User $user, Warehouse $warehouse): bool {
        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::InventoryConfigure->value);
    }

    public function update(User $user, Warehouse $warehouse): bool {
        return $user->can(P::InventoryConfigure->value);
    }

    public function delete(User $user, Warehouse $warehouse): bool {
        return $user->can(P::InventoryConfigure->value);
    }
}
