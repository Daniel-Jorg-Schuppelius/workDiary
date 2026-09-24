<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DriverLicenseReservationGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fleet\Dispatch;

use App\Exceptions\DriverLicenseCheckOverdueException;
use App\Models\Fleet\Vehicle;
use App\Services\Dispatch\Contracts\VehicleReservationGuard;
use App\Services\Fleet\DriverLicenseCheckService;

/** MVP-417: keine Reservierung bei überfälliger Führerscheinkontrolle. */
final class DriverLicenseReservationGuard implements VehicleReservationGuard {
    public function __construct(private readonly DriverLicenseCheckService $checks) {}

    public function assertReservable(Vehicle $vehicle, int $reservedByUserId): void {
        if ($this->checks->isOverdue($reservedByUserId)) {
            throw new DriverLicenseCheckOverdueException();
        }
    }
}
