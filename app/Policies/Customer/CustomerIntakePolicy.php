<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Customer;

use App\Enums\User\Permission as P;
use App\Models\Customer\CustomerIntake;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Interne Bearbeitung der Kundeneingänge (MVP-1074/1075). Die Portalseite
 * läuft über den `customer`-Guard und die Freigabe `intakes`. Die Übernahme
 * prüft zusätzlich das Erstellrecht im Zielbereich (Adapter).
 */
class CustomerIntakePolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::CustomerPortalIntakeView,
        'view' => P::CustomerPortalIntakeView,
        'update' => P::CustomerPortalIntakeManage,
        'handover' => P::CustomerPortalIntakeHandover,
    ];

    public function handover(User $user, CustomerIntake $intake): bool {
        unset($intake);

        return $this->allows($user, 'handover');
    }
}
