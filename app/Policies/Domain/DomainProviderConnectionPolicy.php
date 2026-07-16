<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProviderConnectionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Domain;

use App\Enums\User\Permission as P;
use App\Models\Domain\DomainProviderConnection;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * DomainReselling-Verbindung (Feature 083): view = Verbindung/Health sehen,
 * manage = konfigurieren/Zugangsdaten rotieren/Pilot bestätigen.
 */
class DomainProviderConnectionPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::DomainProviderView->value);
    }

    public function view(User $user, DomainProviderConnection $connection): bool {
        return $user->can(P::DomainProviderView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::DomainProviderManage->value);
    }

    public function update(User $user, DomainProviderConnection $connection): bool {
        return $user->can(P::DomainProviderManage->value);
    }

    public function delete(User $user, DomainProviderConnection $connection): bool {
        return $user->can(P::DomainProviderManage->value);
    }
}
