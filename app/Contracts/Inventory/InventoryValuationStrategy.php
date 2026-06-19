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
use App\Models\{ArticleVariant, StockMovement, Warehouse};
use Illuminate\Database\Eloquent\Model;

/**
 * Austauschbares Bewertungsverfahren der Bestandsführung (Feature 048, E3).
 * Gleitender Durchschnitt oder FIFO – beide schreiben unveränderliche
 * Kostensnapshots an die append-only Bewegungen.
 */
interface InventoryValuationStrategy {
    public function method(): ValuationMethod;

    /** Wareneingang mit Einzelkosten; optional mit Herkunftsbeleg (z. B. Bestellzeile). */
    public function receipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency = 'EUR', ?int $actorUserId = null, ?Model $source = null): StockMovement;

    /** Abgang, verfahrensgemäß bewertet. */
    public function issue(ArticleVariant $variant, Warehouse $warehouse, string $qty, bool $allowNegative = false, ?int $actorUserId = null): StockMovement;

    /** Bewerteter Bestand in Basiseinheit. @return numeric-string */
    public function onHand(ArticleVariant $variant, Warehouse $warehouse): string;

    /**
     * Aktueller Ist-Stückkostenwert für die nächste Entnahme (gleitender
     * Durchschnitt bzw. nächste FIFO/FEFO-Schicht). Grundlage der Nachkalkulation.
     *
     * @return numeric-string
     */
    public function unitCost(ArticleVariant $variant, Warehouse $warehouse): string;

    /** Bewerteter Gesamtwert des Bestands. @return numeric-string */
    public function totalValue(ArticleVariant $variant, Warehouse $warehouse): string;
}
