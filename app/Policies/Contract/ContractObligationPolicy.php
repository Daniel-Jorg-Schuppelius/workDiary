<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractObligationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Contract;

use App\Models\Contract\{Contract, ContractObligation};
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;
use Illuminate\Support\Facades\Gate;

/**
 * Pflicht im Vertragskalender: Lesen und Bearbeiten folgen dem Vertrag
 * ({@see ContractPolicy}). Gebraucht von den Merkern für die nächste Periode
 * (Feature 201, MVP-1646), die `view`/`update` am Vorkommen prüfen.
 */
class ContractObligationPolicy {
    use HasAdminBypass;

    public function view(User $user, ContractObligation $obligation): bool {
        $contract = $this->contract($user, $obligation);

        return $contract !== null && Gate::forUser($user)->allows('view', $contract);
    }

    public function update(User $user, ContractObligation $obligation): bool {
        $contract = $this->contract($user, $obligation);

        return $contract !== null && Gate::forUser($user)->allows('update', $contract);
    }

    private function contract(User $user, ContractObligation $obligation): ?Contract {
        if ((int) $obligation->organization_id !== (int) $user->organization_id) {
            return null;
        }
        $contract = $obligation->contract;

        return $contract instanceof Contract ? $contract : null;
    }
}
