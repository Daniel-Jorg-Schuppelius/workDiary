<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullRmaStockHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims\Contracts;

use App\Enums\Claims\ClaimRmaDisposition;
use App\Models\Claims\ClaimRmaReturn;
use App\Models\Customer\Customer;
use App\Models\Platform\User;

final class NullRmaStockHandler implements RmaStockHandler {
    public function wasShippedTo(int $organizationId, string $serialNo, Customer $customer): bool {
        return false;
    }

    public function bookReturn(ClaimRmaReturn $rma, string $state, User $actor): void {}

    public function applyDisposition(ClaimRmaReturn $rma, ClaimRmaDisposition $disposition, User $actor): void {}
}
