<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialStockStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Material;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Bestandswirkung eines Materialverbrauchs (MVP-1679). Leer heißt: nicht
 * lagergeführt, kein Lagermodul oder vor der Umstellung erfasst. Jeder
 * Abgleich des Verbrauchs setzt den Stand neu, deshalb kein Lebenszyklus.
 */
enum MaterialStockStatus: string implements HasLabel {
    use HasOptions;

    /** Abgang gebucht (bei externer Bestandsführung zusätzlich an das führende System gemeldet). */
    case Booked = 'booked';
    /** Abgang in den Negativbestand wartet auf die Vier-Augen-Freigabe. */
    case Requested = 'requested';
    /** Vier-Augen-Freigabe abgelehnt — kein Abgang gebucht. */
    case Rejected = 'rejected';
    /** Lagergeführter Artikel, aber die Organisation hat kein Standardlager. */
    case NoWarehouse = 'no_warehouse';
    /** Bestand wird extern nur lesend geführt — WorkDiary bucht nicht. */
    case ReadOnly = 'read_only';

    public function label(): string {
        return (string) __('enums.material.material-stock-status.' . $this->value);
    }
}
