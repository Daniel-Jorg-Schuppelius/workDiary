<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchasesImportMerger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

/**
 * Führt die Exporte mehrerer Quellen zu einem Bestand zusammen: Firmen mit
 * gleichem normalisierten Namen bekommen einen gemeinsamen Schlüssel (der des
 * aktuellen Systems, Quality Hosting, gewinnt; fehlende Angaben werden
 * ergänzt), danach werden Ablösungen verknüpft.
 */
final class PurchasesImportMerger {
    public function __construct(
        private readonly ContractSuccessionLinker $linker = new ContractSuccessionLinker(),
    ) {}

    public function merge(PurchasesImport ...$imports): PurchasesImport {
        $imports = array_values($imports);

        /** @var array<string, MarketplaceCompany> $byName */
        $byName = [];
        foreach ($this->prioritized($imports) as $import) {
            foreach ($import->companies() as $company) {
                $name = $company->normalizedName();
                $byName[$name] = isset($byName[$name]) ? $byName[$name]->mergedWith($company) : $company;
            }
        }

        $entitlements = [];
        $issues = [];
        foreach ($imports as $import) {
            foreach ($import->entitlements as $entitlement) {
                $unified = $byName[$entitlement->company->normalizedName()] ?? $entitlement->company;
                $entitlements[] = $unified->key === $entitlement->company->key && $unified === $entitlement->company
                    ? $entitlement
                    : $entitlement->withCompany($unified);
            }
            array_push($issues, ...$import->issues);
        }

        $linked = $this->linker->link($entitlements);

        return new PurchasesImport($linked['entitlements'], $issues, $linked['links']);
    }

    /**
     * Quality Hosting zuerst, damit dessen Kundennummer der gemeinsame
     * Schlüssel wird.
     *
     * @param  list<PurchasesImport>  $imports
     * @return list<PurchasesImport>
     */
    private function prioritized(array $imports): array {
        usort($imports, static function (PurchasesImport $a, PurchasesImport $b): int {
            $rank = static fn(PurchasesImport $import): int => isset($import->countBySource()[MarketplaceEntitlement::SOURCE_QUALITYHOSTING]) ? 0 : 1;

            return $rank($a) <=> $rank($b);
        });

        return $imports;
    }
}
