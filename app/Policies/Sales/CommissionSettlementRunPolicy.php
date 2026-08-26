<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionSettlementRunPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Sales;

use App\Enums\User\Permission as P;
use App\Models\Sales\CommissionSettlementRun;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Abrechnungslaeufe (Feature 146). Das Schliessen ist die festschreibende
 * Handlung und haengt deshalb an commission.manage — nicht am Lesen.
 * Ein geschlossener Lauf laesst sich nicht mehr loeschen (Modell-Guard).
 */
class CommissionSettlementRunPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::CommissionViewAny->value) || $user->can(P::CommissionManage->value);
    }

    public function view(User $user, CommissionSettlementRun $run): bool {
        unset($run);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::CommissionManage->value);
    }

    public function update(User $user, CommissionSettlementRun $run): bool {
        return $user->can(P::CommissionManage->value) && ! $run->isClosed();
    }

    public function close(User $user, CommissionSettlementRun $run): bool {
        return $this->update($user, $run);
    }

    public function delete(User $user, CommissionSettlementRun $run): bool {
        return $this->update($user, $run);
    }

    public function export(User $user, CommissionSettlementRun $run): bool {
        unset($run);

        return $this->viewAny($user);
    }
}
