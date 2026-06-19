<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrderPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{ManufacturingOrder, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Fertigungsaufträge (Feature 047) gehören zum Lager-/Fertigungsmodul: Sehen mit
 * inventory.viewAny, Anlegen/Steuern (Freigabe, Start, Rückmeldung, Auslieferung)
 * mit inventory.post. Mandantengrenze trägt der OrganizationScope/Model-Binding.
 */
class ManufacturingOrderPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::InventoryViewAny->value) || $user->can(P::InventoryPost->value);
    }

    public function view(User $user, ManufacturingOrder $order): bool {
        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::InventoryPost->value);
    }

    public function update(User $user, ManufacturingOrder $order): bool {
        return $user->can(P::InventoryPost->value);
    }
}
