<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use CommonToolkit\ValueObjects\Money;

/**
 * Ein Artikel des eigenen Stamms, reduziert auf das, was der Abgleich braucht.
 */
final readonly class ArticleEntry {
    private const MONTHLY_UNITS = ['monat', 'monate', 'month', 'months', 'mtl', 'monatlich'];

    public function __construct(
        public string $externalId,
        public string $number,
        public string $name,
        public string $unit,
        public ?Money $netUnitPrice,
    ) {}

    /** Einheit „Monat": die Menge einer Position steht in Monaten. */
    public function isMonthly(): bool {
        return in_array(mb_strtolower(trim($this->unit)), self::MONTHLY_UNITS, true);
    }

    /** Verkaufspreis je Lizenz auf die Periodenlänge hochgerechnet. */
    public function priceForTerm(int $termMonths): ?Money {
        if ($this->netUnitPrice === null) {
            return null;
        }

        return $this->isMonthly() ? $this->netUnitPrice->times($termMonths) : $this->netUnitPrice;
    }
}
