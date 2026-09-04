<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProviderInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use Carbon\CarbonImmutable;

/**
 * Eine Anbieterrechnung oder -gutschrift (Feature 152, MVP-762), aus dem PDF
 * gelesen: Kopf plus Positionen mit Vertrag, Endkunde und Laufzeit.
 */
final class ProviderInvoice {
    /**
     * @param  list<ProviderInvoiceLine>  $lines
     * @param  list<string>  $issues
     */
    public function __construct(
        public string $number,
        public ?CarbonImmutable $date,
        public bool $credit,
        public ?string $customerNumber,
        public array $lines,
        public ?float $netTotal,
        public array $issues = [],
    ) {}

    /** Summe der Positionen (Gutschrift negativ). */
    public function linesTotal(): float {
        return round(array_sum(array_map(static fn(ProviderInvoiceLine $l): float => $l->total, $this->lines)), 2);
    }
}
