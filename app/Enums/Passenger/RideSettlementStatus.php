<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RideSettlementStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Passenger;

/**
 * Abrechnungsstand einer Fahrt (MVP-456). Heute schreibt nur die
 * Spaltenvorgabe „open“; „settled“ und „waived“ waren als Konstanten angelegt.
 */
enum RideSettlementStatus: string {
    case Open = 'open';
    case Settled = 'settled';
    case Waived = 'waived';
}
