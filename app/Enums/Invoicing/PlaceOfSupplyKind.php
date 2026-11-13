<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaceOfSupplyKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Leistungsort-Art einer sonstigen Leistung (§ 3a UStG, MVP-1688): Sie
 * entscheidet mit Sitz und Merkmal „Verbraucher“ des Kunden über den
 * Steuerfall. Waren betrifft sie nicht.
 */
enum PlaceOfSupplyKind: string implements HasLabel {
    use HasOptions;

    /** § 3a Abs. 1 (an Verbraucher: Sitz des Leistenden) bzw. Abs. 2 (an Unternehmer: Sitz des Empfängers). */
    case General = 'general';
    /** § 3a Abs. 4: an Verbraucher im Drittland am Wohnsitz des Empfängers. */
    case Catalog = 'catalog';
    /** § 3a Abs. 3 Nr. 1: am Ort des Grundstücks, für alle Empfänger. */
    case Property = 'property';

    public function label(): string {
        return (string) __('enums.invoicing.place_of_supply_kind.' . $this->value);
    }
}
