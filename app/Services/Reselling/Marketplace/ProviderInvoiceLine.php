<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProviderInvoiceLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use Carbon\CarbonImmutable;

/**
 * Position einer Anbieterrechnung: Vertrag (= Abo-Kennung), Endkunde, Laufzeit,
 * Betrag (bei Gutschriften negativ).
 */
final class ProviderInvoiceLine {
    public function __construct(
        public int $position,
        public float $quantity,
        public string $description,
        public float $unitPrice,
        public float $total,
        public ?string $contract = null,
        public ?string $companyKey = null,
        public ?string $companyName = null,
        public ?CarbonImmutable $periodStart = null,
        public ?CarbonImmutable $periodEnd = null,
    ) {}
}
