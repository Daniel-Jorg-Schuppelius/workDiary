<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleReservationGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dispatch\Contracts;

use App\Models\Fleet\Vehicle;

/**
 * Erweiterungspunkt der Fahrzeugreservierung (MVP-863): Module prüfen vor der
 * Reservierung ihre Voraussetzungen (Fuhrpark: Führerscheinkontrolle) und
 * werfen bei Verstoß. Ohne Fuhrparkmodul gibt es keine Kontrolldaten — dann
 * entfällt die Prüfung (Entscheidung Anhang D, Tabelle 6).
 */
interface VehicleReservationGuard {
    public function assertReservable(Vehicle $vehicle, int $reservedByUserId): void;
}
