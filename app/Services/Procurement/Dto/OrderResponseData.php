<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderResponseData.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Dto;

/**
 * Antwort des Lieferanten auf eine Bestellung, unabhängig vom Format
 * (openTRANS ORDERRESPONSE, GAEB X97/X94; MVP-964, MVP-1300).
 */
final class OrderResponseData {
    /** Auftragsbestätigung auf eine Bestellung. */
    public const CONFIRMATION = 'confirmation';

    /** Preisangebot auf eine Preisanfrage. */
    public const OFFER = 'offer';

    /**
     * @param self::CONFIRMATION|self::OFFER $kind
     * @param list<OrderResponseLine> $lines
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $orderNumber,
        public readonly ?string $supplierRef,
        public readonly array $lines,
    ) {}
}
