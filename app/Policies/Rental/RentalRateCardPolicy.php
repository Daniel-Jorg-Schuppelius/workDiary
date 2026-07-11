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
use App\Models\Rental\RentalRateCard;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Versionierte Verleih-Preislisten (D10): Pflege ist ein eigenes Recht,
 * Lesen genügt mit Verleih-Sicht (Konditionen stehen in der Akte).
 */
class RentalRateCardPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::RentalViewAny->value);
    }

    public function view(User $user, RentalRateCard $card): bool {
        return $user->can(P::RentalView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::RentalRates->value);
    }

    public function update(User $user, RentalRateCard $card): bool {
        return $user->can(P::RentalRates->value);
    }
}
