<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerialStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenslauf einer Einzelseriennummer (Feature 047/048, E2). Lückenlos auditiert,
 * eine Seriennummer existiert genau einmal je Organisation + Artikel.
 */
enum SerialStatus: string implements HasLabel {
    use HasOptions;

    case Created = 'created';     // erzeugt, noch nicht eingelagert
    case InStock = 'in_stock';    // auf Lager, verfügbar
    case Reserved = 'reserved';   // einem Auftrag zugeordnet
    case Shipped = 'shipped';     // ausgeliefert (Eigentum beim Kunden)
    case Returned = 'returned';   // zurückgenommen
    case Blocked = 'blocked';     // gesperrt (verloren/gestohlen/Rückruf)
    case Scrapped = 'scrapped';   // verschrottet (terminal)

    public function label(): string {
        return __('inventory.serial.status.' . $this->value);
    }

    /** Darf die Seriennummer ausgeliefert werden? */
    public function isShippable(): bool {
        return $this === self::InStock || $this === self::Reserved;
    }

    /** Endzustand ohne weitere Übergänge. */
    public function isTerminal(): bool {
        return $this === self::Scrapped;
    }
}
