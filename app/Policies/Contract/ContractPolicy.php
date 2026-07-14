<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Contract;

use App\Enums\User\Permission as P;
use App\Models\Contract\Contract;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Policy des allgemeinen Vertrags (Welle D, CLM). Ein einheitliches
 * Vertragsverwaltungsrecht: viewAny/view lesen die Akte, manage pflegt
 * Verträge und Obligationen.
 */
class ContractPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ContractViewAny->value);
    }

    public function view(User $user, Contract $contract): bool {
        return $user->can(P::ContractView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::ContractManage->value);
    }

    public function update(User $user, Contract $contract): bool {
        return $user->can(P::ContractManage->value);
    }
}
