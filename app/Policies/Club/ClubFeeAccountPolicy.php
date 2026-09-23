<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeAccountPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Enums\User\Permission as P;
use App\Models\Club\ClubFeeAccount;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Beitragsverwaltung (MVP-849): eigenes Recht für Kassenwart/Beitragsverwaltung
 * (Tarife, Konten, Zuordnungen, Befreiungen); die Vereinsverwaltung liest.
 * Gruppenleitung sieht keine Beitragsdaten.
 */
class ClubFeeAccountPolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canFees($user) || $this->canManage($user);
    }

    public function view(User $user, ClubFeeAccount $account): bool {
        unset($account);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $this->canFees($user);
    }

    public function update(User $user, ClubFeeAccount $account): bool {
        unset($account);

        return $this->canFees($user);
    }

    public function delete(User $user, ClubFeeAccount $account): bool {
        unset($account);

        return $this->canFees($user);
    }

    private function canFees(User $user): bool {
        return $user->can(P::ClubFeesManage->value);
    }
}
