<?php
/*
 * Created on   : Tue Oct 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullDomainHolderWriter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Contracts;

use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Domain\DomainProjection;
use App\Models\Platform\User;

final class NullDomainHolderWriter implements DomainHolderWriter {
    public function assign(DomainProjection $projection, Customer $customer, ?User $actor = null, ?ForeignCustomer $foreignCustomer = null): bool {
        return false;
    }

    public function markOwn(DomainProjection $projection): bool {
        return false;
    }

    public function suggestions(DomainProjection $projection): array {
        return [];
    }
}
