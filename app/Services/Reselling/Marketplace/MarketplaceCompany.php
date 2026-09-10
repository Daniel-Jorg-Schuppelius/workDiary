<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarketplaceCompany.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

/**
 * Endkunde im Marketplace-Export. Der Schlüssel ist die Kundennummer der
 * Quelle (Telekom: numerische Owner-Company-ID, Quality Hosting: CNL-Nummer),
 * ersatzweise der normalisierte Name. Über Quellen hinweg führt der Merger
 * Firmen am normalisierten Namen zusammen.
 */
final readonly class MarketplaceCompany {
    public function __construct(
        public string $key,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $partnerCustomerNumber = null,
    ) {}

    /**
     * Matching-Schlüssel für Firmen- und Produktnamen (Review 2026-09-10, E):
     * klein, Umlaute ausgeschrieben, alles außer a-z0-9 wird zum Leerzeichen.
     * Bewusst NICHT `StringHelper::toAscii` — das faltet „é" zu „e", die alte
     * Regel verwirft es; `company_mappings.normalized_name` wurde mit der
     * alten Regel gebildet und muss weiter treffen.
     */
    public static function matchKey(string $text): string {
        $text = mb_strtolower(trim($text));
        $text = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $text);
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    public static function normalizeName(string $name): string {
        return self::matchKey($name);
    }

    public function normalizedName(): string {
        return self::normalizeName($this->name);
    }

    /**
     * Ergänzt fehlende Angaben aus einer zweiten Quelle; Schlüssel und Name
     * bleiben die eigenen.
     */
    public function mergedWith(self $other): self {
        return new self(
            key: $this->key,
            name: $this->name,
            email: $this->email ?? $other->email,
            phone: $this->phone ?? $other->phone,
            partnerCustomerNumber: $this->partnerCustomerNumber ?? $other->partnerCustomerNumber,
        );
    }
}
