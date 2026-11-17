<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManualStockReceipt.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\InventoryValuationStrategy;
use App\Enums\Inventory\OwnershipType;
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockMovement, Warehouse, WarehouseBin};
use App\Models\Platform\Organization;
use App\Settings\SettingsRegistry;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

/**
 * Manueller Eingang im Lager und per Scan (Feature 048, MVP-1709): Eigenbestand
 * wird über die Bewertungsstrategie der Variante gebucht wie der Wareneingang
 * der Bestellung (FIFO/FEFO legt eine Schicht an, der Durchschnitt rechnet
 * neu). Fremdbestand bleibt unbewertet wie bei der Entnahme (MVP-1692).
 */
final class ManualStockReceipt {
    private const int SCALE = 4;

    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly InventoryValuationManager $valuation,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * @param string|null $unitCost Einstandspreis je erfasster Einheit in deutscher oder englischer Schreibweise; leer = Einkaufspreis, sonst Durchschnitt
     */
    public function book(ArticleVariant $variant, Warehouse $warehouse, EnteredQuantity $entered, OwnershipType $ownership = OwnershipType::Own, ?string $unitCost = null, ?WarehouseBin $bin = null, ?int $actorUserId = null): StockMovement {
        $organization = $warehouse->organization;
        if ($ownership !== OwnershipType::Own || ! $organization instanceof Organization) {
            return $this->ledger->receipt($variant, $warehouse, $entered->base, $ownership, actorUserId: $actorUserId, bin: $bin, entered: $entered);
        }

        $strategy = $this->valuation->forVariant($variant, $organization);
        $cost = $this->unitCostInBase($strategy, $variant, $warehouse, $organization, $entered, $unitCost);

        return $strategy->receipt($variant, $warehouse, $entered->base, $cost->getAmount(), $cost->getCurrency()->value, $actorUserId, entered: $entered, bin: $bin);
    }

    /**
     * Einstandspreis ohne erfassten Preis, je Basiseinheit (MVP-1727): Einkaufspreis der Variante (sonst des Artikels),
     * sonst Durchschnitt des Lagers, ohne jeden Bewertungsstand 0. Auch der Mehrbestand einer Inventur geht so ein.
     */
    public function defaultUnitCost(InventoryValuationStrategy $strategy, ArticleVariant $variant, Warehouse $warehouse, Organization $organization): Money {
        $average = $strategy->averageCost($variant, $warehouse);

        return $this->fallbackCost($variant, $average, $this->bookCurrency($average, $organization));
    }

    /**
     * Einstandspreis je Basiseinheit in der Währung, in der die Bewertung die
     * Variante im Lager führt — ohne Bewertungsstand in der der Organisation;
     * so mischt kein Eingang zwei Währungen in einem Buch.
     */
    private function unitCostInBase(InventoryValuationStrategy $strategy, ArticleVariant $variant, Warehouse $warehouse, Organization $organization, EnteredQuantity $entered, ?string $unitCost): Money {
        $average = $strategy->averageCost($variant, $warehouse);
        $currency = $this->bookCurrency($average, $organization);

        $entry = Money::ofNullable($unitCost, $currency, self::SCALE);
        if ($entry !== null) {
            return Money::of($entered->unitCostInBase($entry->getAmount()), $currency, self::SCALE);
        }

        return $this->fallbackCost($variant, $average, $currency);
    }

    private function bookCurrency(?Money $average, Organization $organization): CurrencyCode {
        return $average?->getCurrency()
            ?? CurrencyCode::tryFrom(strtoupper((string) $this->settings->effective('invoicing.default_currency', $organization)->value))
            ?? CurrencyCode::Euro;
    }

    private function fallbackCost(ArticleVariant $variant, ?Money $average, CurrencyCode $currency): Money {
        // Einkaufspreise gelten je Basiseinheit; einer in anderer Währung passt nicht ins Buch.
        $purchase = $variant->effectivePurchasePrice();
        if ($purchase instanceof Money && $purchase->getCurrency() === $currency) {
            return $purchase->withScale(self::SCALE);
        }

        return $average ?? Money::zero($currency, self::SCALE);
    }
}
