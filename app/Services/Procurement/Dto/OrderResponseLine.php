<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderResponseLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Dto;

/** Zeile einer Lieferantenantwort; zugeordnet über Zeilennummer oder Lieferanten-SKU. */
final class OrderResponseLine {
    /**
     * @param numeric-string|null $unitPrice Dezimalwert in Bestellwährung
     * @param string|null $deliveryDate Y-m-d
     */
    public function __construct(
        public readonly ?string $lineId,
        public readonly ?string $sellerItemId,
        public readonly ?float $quantity,
        public readonly ?string $unitPrice,
        public readonly ?string $deliveryDate,
    ) {}
}
