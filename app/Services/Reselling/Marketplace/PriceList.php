<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceList.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\BillingFrequency;
use Carbon\CarbonImmutable;

/**
 * Reseller-Preisliste, nachschlagbar über Produktschlüssel, Laufzeit und
 * Zahlungsintervall.
 */
final class PriceList {
    /** @var array<string, PriceListEntry> */
    private array $index = [];

    /**
     * @param  list<PriceListEntry>  $entries
     * @param  list<string>  $issues
     */
    public function __construct(
        public readonly array $entries,
        public readonly ?CarbonImmutable $validFrom,
        public readonly array $issues = [],
        private readonly ProductNameMatcher $matcher = new ProductNameMatcher(),
    ) {
        foreach ($entries as $entry) {
            $this->index[$this->key($entry->product, $entry->termMonths, $entry->interval)] ??= $entry;
        }
    }

    public static function empty(): self {
        return new self([], null);
    }

    public function isEmpty(): bool {
        return $this->entries === [];
    }

    public function find(string $product, int $termMonths, BillingFrequency $interval): ?PriceListEntry {
        return $this->index[$this->key($product, $termMonths, $interval)] ?? null;
    }

    private function key(string $product, int $termMonths, BillingFrequency $interval): string {
        return $this->matcher->productKey($product) . '|' . $termMonths . '|' . $interval->value;
    }
}
