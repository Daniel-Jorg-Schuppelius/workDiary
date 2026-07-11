<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalConflictException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Rental\RentalReservation;
use RuntimeException;

/**
 * Doppelbuchung im Verfügbarkeitskalender (MVP-260): Der Zeitraum kollidiert
 * inklusive Pufferzeiten mit einer bestehenden Belegung.
 */
class RentalConflictException extends RuntimeException {
    public function __construct(
        string $message,
        public readonly ?RentalReservation $conflict = null,
    ) {
        parent::__construct($message);
    }
}
