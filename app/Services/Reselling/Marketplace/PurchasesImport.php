<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchasesImport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

/**
 * Ergebnis des Lesens (einer Datei) oder des Zusammenführens (mehrerer):
 * verwertbare Positionen, Zeilenbefunde und erkannte Ablösungen.
 */
final readonly class PurchasesImport {
    /**
     * @param  list<MarketplaceEntitlement>  $entitlements
     * @param  list<string>  $issues
     * @param  list<SuccessionLink>  $links
     */
    public function __construct(
        public array $entitlements,
        public array $issues,
        public array $links = [],
    ) {}

    /**
     * @return array<string, MarketplaceCompany> Schlüssel → Firma, in Reihenfolge des ersten Auftretens
     */
    public function companies(): array {
        $companies = [];
        foreach ($this->entitlements as $entitlement) {
            $companies[$entitlement->company->key] ??= $entitlement->company;
        }

        return $companies;
    }

    /**
     * @return array<string, list<MarketplaceEntitlement>>
     */
    public function byCompany(): array {
        $grouped = [];
        foreach ($this->entitlements as $entitlement) {
            $grouped[$entitlement->company->key][] = $entitlement;
        }

        return $grouped;
    }

    /**
     * @return array<string, int> Quelle → Anzahl Positionen
     */
    public function countBySource(): array {
        $counts = [];
        foreach ($this->entitlements as $entitlement) {
            $counts[$entitlement->source] = ($counts[$entitlement->source] ?? 0) + 1;
        }

        return $counts;
    }
}
