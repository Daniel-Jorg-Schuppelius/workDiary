<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermissionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\User;

/**
 * Basis für rein permissionsbasierte Policies (Konsolidierung C11): Abilities
 * werden deklarativ über die ABILITIES-Map aufgelöst statt als can()-Einzeiler.
 * Bewusst OHNE Admin-Bypass — HasAdminBypass bleibt in der konkreten Policy,
 * damit Policies ohne Bypass (z. B. Whistleblowing) ihn nie erben.
 */
abstract class PermissionPolicy {
    /**
     * Ability => Permission (can()-Check) oder bool (Pauschalentscheid).
     * Nicht gemappte Abilities verweigern — wie eine fehlende Policy-Methode.
     *
     * @var array<string, Permission|bool>
     */
    protected const ABILITIES = [];

    public function viewAny(User $user): bool {
        return $this->allows($user, 'viewAny');
    }

    public function view(User $user, mixed $model = null): bool {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool {
        return $this->allows($user, 'create');
    }

    public function update(User $user, mixed $model = null): bool {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, mixed $model = null): bool {
        return $this->allows($user, 'delete');
    }

    /** Löst eine Ability über die Map auf — auch für Zusatz-Abilities der Subklassen. */
    protected function allows(User $user, string $ability): bool {
        $permission = static::ABILITIES[$ability] ?? false;

        if (is_bool($permission)) {
            return $permission;
        }

        return $user->can($permission->value);
    }
}
