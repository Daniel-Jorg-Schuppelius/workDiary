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
 */
final class UnitPriceCatalog {
    private const TOLERANCE_MINOR_PER_UNIT = 2;

    /** @var array<string, array<int, array{quantity: int, unit: Money}>> */
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
            $byEdition[$entitlement->edition][$entitlement->fee->getMinorAmount()] = $entitlement->fee;
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

        return $this->resolved[$entitlement->edition][$entitlement->fee->getMinorAmount()]['quantity'] ?? 1;
    }

    public function unitPriceOf(MarketplaceEntitlement $entitlement): Money {
        if ($entitlement->unitFee !== null) {
            return $entitlement->unitFee;
        }
        if ($entitlement->quantity !== null && $entitlement->quantity > 1) {
            return $entitlement->fee->dividedBy($entitlement->quantity);
        }

        return $this->resolved[$entitlement->edition][$entitlement->fee->getMinorAmount()]['unit'] ?? $entitlement->fee;
    }

    /**
     * @return array<string, list<Money>> Edition → erkannte Stückpreise
     */
    public function unitPrices(): array {
        $out = [];
        foreach ($this->resolved as $edition => $entries) {
            $seen = [];
            foreach ($entries as $entry) {
                $seen[$entry['unit']->getMinorAmount()] = $entry['unit'];
            }
            ksort($seen);
            $out[$edition] = array_values($seen);
        }

        return $out;
    }
}
