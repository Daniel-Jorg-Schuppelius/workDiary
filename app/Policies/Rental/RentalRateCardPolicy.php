<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRateCardPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Rental;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Versionierte Verleih-Preislisten (D10): Pflege ist ein eigenes Recht,
 * Lesen genügt mit Verleih-Sicht (Konditionen stehen in der Akte).
 */
class RentalRateCardPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::RentalViewAny,
        'view' => P::RentalView,
        'create' => P::RentalRates,
        'update' => P::RentalRates,
    ];
}
