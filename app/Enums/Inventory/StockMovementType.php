<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockMovementType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Bewegungsart im append-only Lagerjournal (Feature 048, MVP-067). Bestätigte
 * Bewegungen sind unveränderlich; Fehler werden ausschließlich durch eine
 * referenzierte Gegenbuchung (Correction) berichtigt.
 */
enum StockMovementType: string implements HasLabel {
    use HasOptions;

    case Receipt = 'receipt';                       // Wareneingang (+physical)
    case Issue = 'issue';                           // Entnahme/Verbrauch (−physical)
    case Return = 'return';                         // Rückgabe (+physical)
    case TransferOut = 'transfer_out';              // Umlagerung Abgang (−physical)
    case TransferIn = 'transfer_in';                // Umlagerung Zugang (+physical)
    case Reserve = 'reserve';                       // Reservierung (+reserved)
    case ReleaseReservation = 'release_reservation'; // Reservierung freigeben (−reserved)
    case Scrap = 'scrap';                           // Ausschuss (−physical)
    case Correction = 'correction';                 // Inventurdifferenz/Gegenbuchung
    case FinishedGoodReceipt = 'finished_good_receipt'; // Zugang Fertigerzeugnis (+physical)

    public function label(): string {
        return __('inventory.movement.' . $this->value);
    }
}
