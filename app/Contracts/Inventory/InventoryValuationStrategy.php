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

use App\Enums\Inventory\{StockMovementType, ValuationMethod};
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockMovement, Warehouse, WarehouseBin};
use App\Services\Inventory\{EnteredQuantity, StockIssue, StockPosting};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Austauschbares Bewertungsverfahren der Bestandsführung (Feature 048, E3).
 * Gleitender Durchschnitt oder FIFO – beide schreiben unveränderliche
 * Kostensnapshots an die append-only Bewegungen.
 */
interface InventoryValuationStrategy {
    public function method(): ValuationMethod;

    /**
     * Wareneingang mit Einzelkosten je Basiseinheit; optional mit Herkunftsbeleg (z. B. Bestellzeile), Eingabe in
     * Artikeleinheit und Lagerplatz. `$type` trennt den Fertigerzeugnis-Eingang (MVP-1719) vom Wareneingang. Mit
     * `$lot` geht der Eingang in die Charge (MVP-1735): die Bewegung trägt sie, FIFO/FEFO legen die Schicht mit Charge
     * und MHD an, der Durchschnitt bucht ins Durchschnittsbuch. Prüfungen der Charge macht der Aufrufer.
     */
    public function receipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency = 'EUR', ?int $actorUserId = null, ?Model $source = null, ?EnteredQuantity $entered = null, ?WarehouseBin $bin = null, StockMovementType $type = StockMovementType::Receipt, ?StockLot $lot = null): StockMovement;

    /**
     * Aktueller Durchschnitt je Basiseinheit des Bewertungsstands im Lager, in dessen Währung; null ohne
     * Bewertungsstand. FIFO/FEFO mitteln die Restschichten, ohne Rest gilt die jüngste Schicht.
     */
    public function averageCost(ArticleVariant $variant, Warehouse $warehouse): ?Money;

    /**
     * Abgang je Charge (gewählt oder FEFO-zugeteilt), jeder Teil verfahrensgemäß bewertet; `$requireLot` weist einen Teil ohne Charge ab.
     * Mit Schlüssel ist der Abgang idempotent, `$source` ist der Beleg der Bewegungen; mit `$bin` zählt nur der Bestand dieses Platzes.
     */
    public function issue(ArticleVariant $variant, Warehouse $warehouse, string $qty, bool $allowNegative = false, ?int $actorUserId = null, ?StockLot $lot = null, bool $requireLot = false, ?EnteredQuantity $entered = null, ?string $idempotencyKey = null, ?Model $source = null, ?WarehouseBin $bin = null): StockIssue;

    /**
     * Rückbuchung eines Abgangs (Bewegungsart Return) zum Stückkostenwert des
     * Abgangs in dessen Charge; der Bestand des Verfahrens wächst wieder an
     * (Durchschnittsbuch bzw. neue Schicht).
     */
    public function returnToStock(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency = 'EUR', ?int $actorUserId = null, ?Model $source = null, ?StockLot $lot = null, ?string $idempotencyKey = null): StockMovement;

    /**
     * Umlagerung (MVP-1726): Abgang im Quelllager nach Verfahren als TransferOut — Prüfung und Chargenzuteilung wie bei
     * der Entnahme, je Charge eine Bewegung —, je Teil ein TransferIn im Ziellager mit derselben Charge und demselben
     * Wert. Das Bewertungsbuch wandert mit, der Bestandswert der Organisation bleibt gleich. Mit Schlüssel idempotent.
     * Vor der ersten Buchung sind Bestand und Buch beider Lager gesperrt, je in aufsteigender Lager-ID (MVP-1738).
     */
    public function transfer(ArticleVariant $variant, Warehouse $from, Warehouse $to, string $qty, ?int $actorUserId = null, ?StockLot $lot = null, ?EnteredQuantity $entered = null, ?string $idempotencyKey = null): StockIssue;

    /**
     * Bewertete Korrektur des physischen Eigenbestands (MVP-1727): ein Zugang zum Stückwert `$unitCost` je Basiseinheit
     * wie ein Eingang, ein Abgang nach Verfahren aus der Charge der Buchung, ohne Chargenzuteilung. Ein schon gebuchter
     * Schlüssel liefert die frühere Bewegung, ohne erneut zu bewerten.
     */
    public function correct(StockPosting $posting, ?Money $unitCost = null): StockMovement;

    /** Bewerteter Bestand in Basiseinheit. @return numeric-string */
    public function onHand(ArticleVariant $variant, Warehouse $warehouse): string;

    /**
     * Hebt das Bewertungsbuch des Lagers auf den physischen Eigenbestand an (MVP-1736), ohne Lagerbewegung — die Menge
     * ist schon da, nur ihr Wert fehlte. Liest das Buch gesperrt; die fehlende Menge geht zum Stückwert ein (Durchschnitt
     * rechnet neu, FIFO/FEFO legen eine Schicht ohne Bewegung an, die als älteste zählt). Ein Buch auf oder über dem
     * Bestand bleibt unverändert. Den Bestand sperrt der Aufrufer vorher.
     *
     * @param numeric-string $physicalQty
     * @return numeric-string nachgetragene Menge, "0.0000" ohne Lücke
     */
    public function raiseBook(ArticleVariant $variant, Warehouse $warehouse, string $physicalQty, Money $unitCost): string;

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
