<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfApproval.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Approval;

use App\Enums\User\Permission;
use App\Models\Platform\User;

/**
 * Entscheidung über den eigenen Vorgang (S-35, Entscheidung 2026-10-23): auch
 * Admins entscheiden nicht über den eigenen Urlaub, Monat oder Tag — außer es
 * gibt in der Organisation sonst niemanden mit dem Recht. Im Ein-Personen-
 * Betrieb bliebe der Vorgang sonst unentscheidbar.
 */
final class SelfApproval {
    /** @var array<string, bool> */
    private array $sole = [];

    public function soleDecider(User $user, Permission $permission): bool {
        if ($user->organization_id === null) {
            return false;
        }

        return $this->sole[$user->organization_id . ':' . $user->id . ':' . $permission->value] ??= ! User::query()
            ->where('organization_id', $user->organization_id)
            ->whereKeyNot($user->getKey())
            ->whereNull('deactivated_at')
            ->get()
            ->contains(static fn (User $other): bool => $other->isAdmin() || $other->hasEffectivePermission($permission->value));
    }
}
