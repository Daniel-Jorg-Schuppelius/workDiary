<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerQueryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{CustomerQuery, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Interne Verwaltung der Kunden-Rückfragen (Feature 012). Die Kundenseite
 * läuft ausschließlich über den öffentlichen Signaturlink bzw. den
 * `customer`-Guard und wird hiervon nicht berührt.
 */
class CustomerQueryPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ProtocolCustomerQueryManage->value);
    }

    public function view(User $user, CustomerQuery $query): bool {
        unset($query);

        return $user->can(P::ProtocolCustomerQueryManage->value);
    }

    public function manage(User $user, CustomerQuery $query): bool {
        unset($query);

        return $user->can(P::ProtocolCustomerQueryManage->value);
    }
}
