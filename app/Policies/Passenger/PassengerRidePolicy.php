<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerRidePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Passenger;

use App\Enums\User\Permission as P;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Personenbeförderung (MVP-456): gilt für die Fahrtakte UND die Stammdaten
 * (Tarife, Konzessionen, Fahrzeugprofile) — eine Rechtefamilie, weil
 * Disposition und Tarifpflege dieselbe fachliche Rolle sind. Die
 * Schichtabrechnung (Kassenfunktion) verlangt zusätzlich `settle`.
 */
class PassengerRidePolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::PassengerViewAny,
        'view' => P::PassengerView,
        'create' => P::PassengerManage,
        'update' => P::PassengerManage,
        'delete' => P::PassengerManage,
        'settle' => P::PassengerSettle,
    ];

    /** Schichtabrechnung führen/abschließen (Differenzklärung). */
    public function settle(User $user, mixed $model = null): bool {
        return $this->allows($user, 'settle');
    }
}
