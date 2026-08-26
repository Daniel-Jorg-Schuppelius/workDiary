<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelLogLockedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TravelLog;
use RuntimeException;

/**
 * Festgeschriebene Fahrt (Feature 137): Änderung/Löschung abgewiesen —
 * der Weg ist die Stornofahrt mit Referenz und Grund.
 */
class TravelLogLockedException extends RuntimeException {
    public function __construct(public readonly TravelLog $travelLog) {
        parent::__construct((string) __('Diese Fahrt ist festgeschrieben und kann nicht mehr geändert werden — bitte eine Stornofahrt mit Grund erfassen.'));
    }
}
