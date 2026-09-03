<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceListEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\BillingFrequency;
use CommonToolkit\ValueObjects\Money;

/**
 * Eine Zeile der Reseller-Preisliste: Produkttarif je Laufzeit und
 * Zahlungsintervall mit Einkaufspreis und Hersteller-UVP.
 */
final readonly class PriceListEntry {
    public function __construct(
        public string $product,
        public int $termMonths,
        public BillingFrequency $interval,
        public Money $pricePerMonth,
        public ?Money $uvpPerMonth,
        public Money $pricePerInterval,
        public ?Money $uvpPerInterval,
        public string $offerKey,
        public int $sourceLine,
    ) {}
}
