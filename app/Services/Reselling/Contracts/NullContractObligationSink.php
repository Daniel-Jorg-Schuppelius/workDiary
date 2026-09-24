<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullContractObligationSink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Contracts;

use App\Models\Contract\{Contract, ContractObligation};

final class NullContractObligationSink implements ContractObligationSink {
    public function addObligation(Contract $contract, array $attributes): ?ContractObligation {
        return null;
    }
}
