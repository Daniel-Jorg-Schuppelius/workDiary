<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleReservationConflictException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use App\Models\VehicleReservation;
use RuntimeException;

/**
 * Wird geworfen, wenn ein Fahrzeug im gewünschten Zeitfenster bereits
 * reserviert ist (Doppelreservierung, Feature 028).
 */
class VehicleReservationConflictException extends RuntimeException {
    public function __construct(public readonly VehicleReservation $conflicting) {
        parent::__construct((string) __('Fahrzeug ist im gewählten Zeitfenster bereits reserviert.'));
    }
}
