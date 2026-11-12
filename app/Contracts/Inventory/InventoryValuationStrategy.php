<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryValuationStrategy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Contracts\Inventory;

use App\Enums\Inventory\ValuationMethod;
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockMovement, Warehouse};
use App\Services\Inventory\{EnteredQuantity, StockIssue};
use Illuminate\Database\Eloquent\Model;

/**
 * Austauschbares Bewertungsverfahren der Bestandsführung (Feature 048, E3).
 * Gleitender Durchschnitt oder FIFO – beide schreiben unveränderliche
 * Kostensnapshots an die append-only Bewegungen.
 */
interface InventoryValuationStrategy {
    public function method(): ValuationMethod;

    /** Wareneingang mit Einzelkosten je Basiseinheit; optional mit Herkunftsbeleg (z. B. Bestellzeile) und Eingabe in Artikeleinheit. */
    public function receipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency = 'EUR', ?int $actorUserId = null, ?Model $source = null, ?EnteredQuantity $entered = null): StockMovement;

    /** Abgang je Charge (gewählt oder FEFO-zugeteilt), jeder Teil verfahrensgemäß bewertet; `$requireLot` weist einen Teil ohne Charge ab. */
    public function issue(ArticleVariant $variant, Warehouse $warehouse, string $qty, bool $allowNegative = false, ?int $actorUserId = null, ?StockLot $lot = null, bool $requireLot = false, ?EnteredQuantity $entered = null): StockIssue;

    /** Bewerteter Bestand in Basiseinheit. @return numeric-string */
    public function onHand(ArticleVariant $variant, Warehouse $warehouse): string;

    /**
     * Aktueller Ist-Stückkostenwert für die nächste Entnahme (gleitender
     * Durchschnitt bzw. nächste FIFO/FEFO-Schicht). Grundlage der Nachkalkulation.
     *
     * @return numeric-string
     */
    public function unitCost(ArticleVariant $variant, Warehouse $warehouse): string;

    /**
     * Bewertung aller Varianten eines Lagers nach diesem Verfahren (Durchschnitt und Wert in einer Query).
     *
     * @return array<int, array{avg: numeric-string, value: numeric-string}> Variante → Bewertung
     */
    public function summariesForWarehouse(Warehouse $warehouse): array;
}
