<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintOrderPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Print;

use App\Enums\User\Permission as P;
use App\Models\Print\PrintOrder;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Druckauftrag (MVP-459): gleiche Rechtefamilie wie der Fertigungsauftrag
 * (1:1-Spezialisierung, dieselbe fachliche Rolle) — bewusst identische
 * Permissions wie {@see \App\Policies\ManufacturingOrderPolicy}, aber mit
 * eigenem Modelltyp.
 */
class PrintOrderPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::InventoryViewAny->value) || $user->can(P::InventoryPost->value);
    }

    public function view(User $user, PrintOrder $order): bool {
        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::InventoryPost->value);
    }

    public function update(User $user, PrintOrder $order): bool {
        return $user->can(P::InventoryPost->value);
    }
}
