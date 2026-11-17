<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockCorrection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\StockMovement;
use App\Models\Platform\Organization;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;

/**
 * Korrekturbuchung mit Bewertung (Feature 048, MVP-1727): Inventurdifferenz,
 * Gegenbuchung eines Spiegelkonflikts, Übernahme-Inventur, Wiedereinlagerung.
 * Physischer Eigenbestand läuft über die Strategie der Variante — ein
 * Mehrbestand zum übergebenen Stückwert, ohne ihn wie ein manueller Eingang
 * (Einkaufspreis, sonst Durchschnitt), ein Minderbestand nach Verfahren.
 * Andere Zustände (reserviert, gesperrt, Quarantäne) und Fremdbestand ändern
 * den Bestandswert nicht und werden nur mengenmäßig gebucht.
 */
final class StockCorrection {
    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly InventoryValuationManager $valuation,
        private readonly ManualStockReceipt $receipts,
    ) {}

    /** @param Money|null $unitCost Stückwert eines Mehrbestands je Basiseinheit; beim Minderbestand ohne Bedeutung */
    public function book(StockPosting $posting, ?Money $unitCost = null): StockMovement {
        $organization = $posting->warehouse->organization;
        if (! $posting->isValuedStock() || ! $organization instanceof Organization) {
            return $this->ledger->post($posting);
        }

        $strategy = $this->valuation->forVariant($posting->variant, $organization);
        if ($unitCost === null && NumberHelper::isPositivePrecise($posting->signedQty, InventoryLedger::SCALE)) {
            $unitCost = $this->receipts->defaultUnitCost($strategy, $posting->variant, $posting->warehouse, $organization);
        }

        return $strategy->correct($posting, $unitCost);
    }
}
