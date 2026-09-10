<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UnitPriceCatalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use CommonToolkit\ValueObjects\Money;

/**
 * Leitet Stückpreis und Menge je Position aus den Gebühren ab.
 *
 * Der Export nennt nur die Gesamtgebühr der Position, keine Menge. Je Edition
 * werden die Gebühren aufsteigend durchgegangen: Ist eine Gebühr ein
 * ganzzahliges Vielfaches eines bereits bekannten Stückpreises (Toleranz zwei
 * Cent je Stück, weil der Marketplace je Stück rundet), ist sie „n × Stück";
 * sonst ist sie selbst ein Stückpreis. Preisänderungen über die Jahre ergeben
 * so mehrere Stückpreise je Edition, was gewollt ist.
 *
 * Grenze des Verfahrens: Die kleinste Gebühr einer Edition gilt immer als
 * Stückpreis. Bestellt niemand genau ein Stück (nur 2er- und 4er-Pakete),
 * wird das 2er-Paket zum „Stückpreis" und die Menge halbiert sich; ebenso
 * kippt ein einzelner Rabatt- oder Teilperiodenbetrag die Ableitung. Der
 * Katalog gruppiert je Edition **und Währung** — Gebühren verschiedener
 * Währungen werden nie miteinander verglichen (Review 2026-09-10, E).
 */
final class UnitPriceCatalog {
    private const TOLERANCE_MINOR_PER_UNIT = 2;

    /** @var array<string, array<int, array{quantity: int, unit: Money}>> Edition|Währung → Gebühr (Minor) → Ableitung */
    private array $resolved = [];

    /**
     * @param  iterable<MarketplaceEntitlement>  $entitlements
     */
    public static function fromEntitlements(iterable $entitlements): self {
        $byEdition = [];
        foreach ($entitlements as $entitlement) {
            if ($entitlement->quantity !== null) {
                continue; // Quelle nennt die Menge — nichts abzuleiten.
            }
            $byEdition[self::groupKey($entitlement)][$entitlement->fee->getMinorAmount()] = $entitlement->fee;
        }

        $catalog = new self();
        foreach ($byEdition as $edition => $fees) {
            ksort($fees);
            /** @var list<Money> $units */
            $units = [];
            foreach ($fees as $minor => $fee) {
                $best = null;
                foreach ($units as $unit) {
                    $unitMinor = $unit->getMinorAmount();
                    if ($unitMinor <= 0) {
                        continue;
                    }
                    $quantity = (int) round($minor / $unitMinor);
                    if ($quantity < 2) {
                        continue;
                    }
                    $residual = abs($minor - $quantity * $unitMinor);
                    if ($residual > self::TOLERANCE_MINOR_PER_UNIT * $quantity) {
                        continue;
                    }
                    if ($best === null || $residual < $best['residual']) {
                        $best = ['quantity' => $quantity, 'unit' => $unit, 'residual' => $residual];
                    }
                }

                if ($best === null) {
                    $units[] = $fee;
                    $catalog->resolved[$edition][$minor] = ['quantity' => 1, 'unit' => $fee];
                } else {
                    $catalog->resolved[$edition][$minor] = ['quantity' => $best['quantity'], 'unit' => $best['unit']];
                }
            }
        }

        return $catalog;
    }

    public function quantityOf(MarketplaceEntitlement $entitlement): int {
        if ($entitlement->quantity !== null) {
            return max(1, $entitlement->quantity);
        }

        return $this->resolved[self::groupKey($entitlement)][$entitlement->fee->getMinorAmount()]['quantity'] ?? 1;
    }

    public function unitPriceOf(MarketplaceEntitlement $entitlement): Money {
        if ($entitlement->unitFee !== null) {
            return $entitlement->unitFee;
        }
        if ($entitlement->quantity !== null && $entitlement->quantity > 1) {
            return $entitlement->fee->dividedBy($entitlement->quantity);
        }

        return $this->resolved[self::groupKey($entitlement)][$entitlement->fee->getMinorAmount()]['unit'] ?? $entitlement->fee;
    }

    /**
     * @return array<string, list<Money>> Edition → erkannte Stückpreise (bei mehreren Währungen „Edition|Währung")
     */
    public function unitPrices(): array {
        $out = [];
        $currencies = [];
        foreach (array_keys($this->resolved) as $key) {
            $currencies[substr($key, 0, (int) strrpos($key, '|'))][] = substr($key, (int) strrpos($key, '|') + 1);
        }
        foreach ($this->resolved as $key => $entries) {
            $edition = substr($key, 0, (int) strrpos($key, '|'));
            $label = count(array_unique($currencies[$edition] ?? [])) > 1 ? $key : $edition;
            $seen = [];
            foreach ($entries as $entry) {
                $seen[$entry['unit']->getMinorAmount()] = $entry['unit'];
            }
            ksort($seen);
            $out[$label] = array_values($seen);
        }

        return $out;
    }

    private static function groupKey(MarketplaceEntitlement $entitlement): string {
        return $entitlement->edition . '|' . $entitlement->fee->getCurrency()->value;
    }
}
