<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerProfileCondition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Passenger\Navigation;

use App\Models\Platform\{Organization, User};
use App\Services\Navigation\Contracts\NavigationCondition;
use App\Services\Passenger\PassengerRideService;

/** Personenbeförderung (MVP-456) nur mit installiertem Branchenprofil taxi-mietwagen. */
final class PassengerProfileCondition implements NavigationCondition {
    public function __construct(private readonly PassengerRideService $rides) {}

    public function key(): string {
        return 'passenger.profile';
    }

    public function passes(?User $user, ?Organization $organization): bool {
        return $organization !== null && $this->rides->isPassengerProfileActive($organization);
    }
}
