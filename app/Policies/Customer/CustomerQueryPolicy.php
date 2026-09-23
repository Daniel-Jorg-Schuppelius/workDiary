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
class CustomerQueryPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::ProtocolCustomerQueryManage,
        'view' => P::ProtocolCustomerQueryManage,
        'manage' => P::ProtocolCustomerQueryManage,
    ];

    /** Zusatz-Ability jenseits der Basis-CRUD-Methoden (C11/N28). */
    public function manage(User $user, CustomerQuery $query): bool {
        unset($query);

        return $this->allows($user, 'manage');
    }
}
