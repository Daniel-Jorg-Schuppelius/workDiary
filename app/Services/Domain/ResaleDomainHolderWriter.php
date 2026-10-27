<?php
/*
 * Created on   : Tue Oct 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleDomainHolderWriter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Domain;

use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Domain\DomainProjection;
use App\Models\Platform\User;
use App\Services\Reselling\Contracts\DomainHolderWriter;

/**
 * Bindung des Reselling-Vertrags {@see DomainHolderWriter} an die
 * Domainverwaltung (DomainManifest::bindings): die Sammelzuordnung des
 * Registers schreibt den Halter über dieselbe Zuordnung wie die Domainakte.
 */
final class ResaleDomainHolderWriter implements DomainHolderWriter {
    public function __construct(private readonly DomainCustomerMappingService $mapping) {}

    public function assign(DomainProjection $projection, Customer $customer, ?User $actor = null, ?ForeignCustomer $foreignCustomer = null): bool {
        $this->mapping->assign($projection, $customer, $actor, $foreignCustomer);

        return true;
    }

    public function markOwn(DomainProjection $projection): bool {
        $this->mapping->markOwnHolding($projection);

        return true;
    }

    public function suggestions(DomainProjection $projection): array {
        return $this->mapping->suggestFor($projection);
    }
}
