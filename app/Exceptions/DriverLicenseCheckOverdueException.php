<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DriverLicenseCheckOverdueException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use RuntimeException;

/**
 * MVP-417: Fahrzeugreservierung für Fahrer mit überfälliger
 * Führerscheinkontrolle — Sperre mit Grund und nächstem Schritt.
 */
class DriverLicenseCheckOverdueException extends RuntimeException {
    public function __construct() {
        parent::__construct((string) __('Die Führerscheinkontrolle dieses Fahrers ist überfällig — vor einer Fahrzeugreservierung die Sichtprüfung dokumentieren.'));
    }
}
