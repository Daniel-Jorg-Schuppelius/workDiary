<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalPurchase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

use Carbon\CarbonImmutable;

/**
 * Einkaufsbeleg eines Lieferanten aus dem Buchhaltungsprogramm (MVP-1036):
 * Betrag mit Vorzeichen (Gutschriften negativ), offener Rest.
 */
final readonly class ExternalPurchase {
    public function __construct(
        public int $supplierId,
        public CarbonImmutable $date,
        public float $amount,
        public float $open,
    ) {}
}
