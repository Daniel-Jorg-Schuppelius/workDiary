<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValuationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\InventoryValuationStrategy;
use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState, ValuationMethod};
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockMovement, StockValuation, Warehouse, WarehouseBin};
use App\Support\DecimalQty;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Bestandsbewertung mit gleitendem Durchschnittspreis (Feature 048, MVP-070).
 * Jede bewertungsrelevante Bewegung erhält einen UNVERÄNDERLICHEN Kostensnapshot
 * (cost_unit/cost_total an der append-only stock_movements-Zeile). Der laufende
 * Durchschnitt wird je Variante/Lagerort fortgeschrieben; Abgänge werden zum
 * aktuellen Durchschnitt bewertet (der Durchschnitt bleibt dabei unverändert).
 * Spätere Preisänderungen verändern historische Bewegungen nicht.
 */
class ValuationService implements InventoryValuationStrategy {
    public const int SCALE = 4;

    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly LotService $lots,
    ) {}

    public function method(): ValuationMethod {
        return ValuationMethod::MovingAverage;
    }

    /** Ist-Stückkosten = aktueller gleitender Durchschnitt. @return numeric-string */
    public function unitCost(ArticleVariant $variant, Warehouse $warehouse): string {
        return $this->average($variant, $warehouse);
    }

    /** Bewerteter Bestand in Basiseinheit (= geführte Menge). @return numeric-string */
    public function onHand(ArticleVariant $variant, Warehouse $warehouse): string {
        $valuation = $this->valuationFor($variant, $warehouse);

        return $valuation->exists ? bcadd($valuation->qty_on_hand, '0', self::SCALE) : '0';
    }

    /** Wareneingang mit Einzelkosten: aktualisiert den gleitenden Durchschnitt; die Charge steht nur an der Bewegung. */
    public function receipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency = 'EUR', ?int $actorUserId = null, ?Model $source = null, ?EnteredQuantity $entered = null, ?WarehouseBin $bin = null, StockMovementType $type = StockMovementType::Receipt, ?StockLot $lot = null): StockMovement {
        $qty = DecimalQty::positive($qty);
        $unitCost = DecimalQty::positive($unitCost);

        return DB::transaction(function () use ($variant, $warehouse, $qty, $unitCost, $currency, $actorUserId, $source, $entered, $bin, $type, $lot): StockMovement {
            // Bestand vor Buch wie bei der Entnahme (MVP-1738), sonst verklemmen sich Eingang und Abgang.
            $this->ledger->availableForUpdate($variant, $warehouse);
            $addValue = bcmul($qty, $unitCost, self::SCALE);
            $this->addToBook($variant, $warehouse, $qty, $addValue, $unitCost, $currency);

            return $this->ledger->post(new StockPosting(
                $variant,
                $warehouse,
                StockState::Physical,
                $qty,
                $type,
                OwnershipType::Own,
                originalQty: $entered?->originalQty,
                originalUnit: $entered?->originalUnit,
                actorUserId: $actorUserId,
                source: $source,
                costUnit: $unitCost,
                costTotal: $addValue,
                currency: $currency,
                stockLotId: $lot?->id,
                bin: $bin,
            ));
        });
    }

    /** Aktueller gleitender Durchschnitt des Lagers; auch bei leerem Bestand der zuletzt fortgeschriebene. */
    public function averageCost(ArticleVariant $variant, Warehouse $warehouse): ?Money {
        $valuation = $this->valuationFor($variant, $warehouse);
        if (! $valuation->exists) {
            return null;
        }

        return $valuation->avg_cost ?? Money::zero($valuation->currency, self::SCALE);
    }

    /**
     * Rückbuchung eines Abgangs ins Lager (Bewegungsart Return): wie ein
     * Wareneingang zum angegebenen Stückkostenwert, aber als klar erkennbare
     * Gegenbuchung im Journal (referenziert die ursprüngliche Bewegung als source),
     * in die Charge, aus der der Abgang kam — ist sie inzwischen gesperrt, wird
     * die Menge mitgesperrt, denn die Rückbuchung darf nicht scheitern.
     */
    public function returnToStock(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency = 'EUR', ?int $actorUserId = null, ?Model $source = null, ?StockLot $lot = null, ?string $idempotencyKey = null): StockMovement {
        $qty = DecimalQty::positive($qty);
        $unitCost = DecimalQty::positive($unitCost);

        return DB::transaction(function () use ($variant, $warehouse, $qty, $unitCost, $currency, $actorUserId, $source, $lot, $idempotencyKey): StockMovement {
            $this->ledger->availableForUpdate($variant, $warehouse);
            $addValue = bcmul($qty, $unitCost, self::SCALE);
            $this->addToBook($variant, $warehouse, $qty, $addValue, $unitCost, $currency);

            return $this->lots->holdArrival($lot, fn (): StockMovement => $this->ledger->post(new StockPosting(
                $variant,
                $warehouse,
                StockState::Physical,
                $qty,
                StockMovementType::Return,
                OwnershipType::Own,
                idempotencyKey: $idempotencyKey,
                actorUserId: $actorUserId,
                source: $source,
                costUnit: $unitCost,
                costTotal: $addValue,
                currency: $currency,
                stockLotId: $lot?->id,
            )));
        });
    }

    /**
     * Abgang über das Lagerbuch (Charge gewählt oder FEFO-zugeteilt), jeder
     * Teil zum aktuellen Durchschnitt bewertet (Durchschnitt unverändert).
     */
    public function issue(ArticleVariant $variant, Warehouse $warehouse, string $qty, bool $allowNegative = false, ?int $actorUserId = null, ?StockLot $lot = null, bool $requireLot = false, ?EnteredQuantity $entered = null, ?string $idempotencyKey = null, ?Model $source = null, ?WarehouseBin $bin = null): StockIssue {
        return $this->ledger->issue(
            $variant, $warehouse, $qty,
            entered: $entered,
            allowNegative: $allowNegative,
            idempotencyKey: $idempotencyKey,
            actorUserId: $actorUserId,
            lot: $lot,
            requireLot: $requireLot,
            source: $source,
            bin: $bin,
            // Läuft nach der gesperrten Bestandsprüfung des Lagerbuchs: der Durchschnitt wird erst danach gelesen, sonst
            // schrieben zwei parallele Abgänge ihren Bestand gegen denselben veralteten Stand fort.
            costing: fn (?StockLot $partLot, string $partQty): array => $this->takeFromBook($variant, $warehouse, $partQty),
        );
    }

    /**
     * Abgang zum Durchschnitt des Quelllagers (TransferOut), Zugang im Ziellager zum selben Wert: dessen Durchschnitt
     * rechnet neu, der Bestandswert der Organisation bleibt gleich (MVP-1726).
     */
    public function transfer(ArticleVariant $variant, Warehouse $from, Warehouse $to, string $qty, ?int $actorUserId = null, ?StockLot $lot = null, ?EnteredQuantity $entered = null, ?string $idempotencyKey = null): StockIssue {
        return DB::transaction(function () use ($variant, $from, $to, $qty, $actorUserId, $lot, $entered, $idempotencyKey): StockIssue {
            // Feste Folge statt Quelle vor Ziel: gegenläufige Umlagerungen warten aufeinander, statt sich zu verklemmen (MVP-1738).
            foreach ($this->ledger->lockStock($variant, $from, $to) as $warehouse) {
                $this->valuationFor($variant, $warehouse, lock: true);
            }

            $leaving = $this->ledger->issue(
                $variant, $from, $qty,
                idempotencyKey: $idempotencyKey,
                actorUserId: $actorUserId,
                lot: $lot,
                costing: fn (?StockLot $partLot, string $partQty): array => $this->takeFromBook($variant, $from, $partQty),
                entered: $entered,
                type: StockMovementType::TransferOut,
            );

            foreach ($leaving as $out) {
                $arrival = $this->ledger->transferIn($variant, $out, $to, $entered);
                if ($arrival->wasRecentlyCreated) {
                    $this->addToBook($variant, $to, DecimalQty::positive((string) $arrival->qty_base), $arrival->cost_total?->getAmount() ?? '0', $arrival->cost_unit?->getAmount() ?? '0', $arrival->cost_total?->getCurrency()->value ?? 'EUR');
                }
            }

            return $leaving;
        });
    }

    /**
     * Mehrbestand rechnet den Durchschnitt mit dem Stückwert neu wie ein Eingang, Minderbestand geht zum Durchschnitt
     * ab (MVP-1727). Ein schon gebuchter Schlüssel liefert die frühere Bewegung, ohne das Buch erneut zu bewegen.
     */
    public function correct(StockPosting $posting, ?Money $unitCost = null): StockMovement {
        $posting->assertValuedStock();

        return DB::transaction(function () use ($posting, $unitCost): StockMovement {
            $earlier = $this->ledger->posted($posting);
            if ($earlier instanceof StockMovement) {
                return $earlier;
            }

            $variant = $posting->variant;
            $warehouse = $posting->warehouse;
            $this->ledger->availableForUpdate($variant, $warehouse);
            $qty = DecimalQty::positive($posting->signedQty);
            if (NumberHelper::isPositivePrecise($posting->signedQty, self::SCALE)) {
                $unit = $unitCost?->withScale(self::SCALE)->getAmount() ?? '0';
                $currency = $unitCost?->getCurrency()->value ?? ($this->averageCost($variant, $warehouse)?->getCurrency()->value ?? 'EUR');
                $value = bcmul($qty, $unit, self::SCALE);
                $this->addToBook($variant, $warehouse, $qty, $value, $unit, $currency);

                return $this->ledger->post($posting->withCost($unit, $value, $currency));
            }

            $cost = $this->takeFromBook($variant, $warehouse, $qty);

            return $this->ledger->post($posting->withCost($cost['unit'], $cost['total'], $cost['currency']));
        });
    }

    /** Fehlende Menge geht zum Stückwert ins Buch wie ein Eingang, nur ohne Bewegung (MVP-1736). */
    public function raiseBook(ArticleVariant $variant, Warehouse $warehouse, string $physicalQty, Money $unitCost): string {
        return DB::transaction(function () use ($variant, $warehouse, $physicalQty, $unitCost): string {
            $valuation = $this->valuationFor($variant, $warehouse, lock: true);
            $missing = bcsub($physicalQty, $valuation->exists ? $valuation->qty_on_hand : '0', self::SCALE);
            if (! NumberHelper::isPositivePrecise($missing, self::SCALE)) {
                return bcadd('0', '0', self::SCALE);
            }

            $unit = $unitCost->withScale(self::SCALE)->getAmount();
            $this->addToBook($variant, $warehouse, $missing, bcmul($missing, $unit, self::SCALE), $unit, $unitCost->getCurrency()->value);

            return $missing;
        });
    }

    /** @return numeric-string */
    public function average(ArticleVariant $variant, Warehouse $warehouse): string {
        $valuation = $this->valuationFor($variant, $warehouse);

        return $valuation->exists ? ($valuation->avg_cost?->getAmount() ?? '0') : '0';
    }

    /**
     * Bewertungen aller Varianten eines Lagers in einer Query (Vollscan
     * 2026-08-23, A4) — Rückgabe variant_id → [avg, value].
     *
     * @return array<int, array{avg: numeric-string, value: numeric-string}>
     */
    public function summariesForWarehouse(Warehouse $warehouse): array {
        $out = [];
        foreach (StockValuation::query()->where('warehouse_id', $warehouse->id)->get() as $valuation) {
            $avg = $valuation->avg_cost?->getAmount() ?? '0';
            $out[(int) $valuation->article_variant_id] = [
                'avg' => $avg,
                'value' => bcmul($valuation->qty_on_hand, $avg, self::SCALE),
            ];
        }

        return $out;
    }

    /**
     * Zugang ins Buch: neuer Durchschnitt = (Wert alt + Wert neu) / Menge; ohne Bestand der Stückwert.
     *
     * @param numeric-string $qty
     * @param numeric-string $value
     * @param numeric-string $unitCost
     */
    private function addToBook(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $value, string $unitCost, string $currency): void {
        $valuation = $this->valuationFor($variant, $warehouse, lock: true);
        $oldQty = $valuation->exists ? $valuation->qty_on_hand : '0';
        $oldAvg = $valuation->exists ? ($valuation->avg_cost?->getAmount() ?? '0') : '0';

        $newQty = bcadd($oldQty, $qty, self::SCALE);
        $newValue = bcadd(bcmul($oldQty, $oldAvg, self::SCALE), $value, self::SCALE);

        $valuation->fill([
            'organization_id' => $variant->organization_id,
            'avg_cost' => NumberHelper::divideOrDefault($newValue, $newQty, self::SCALE, $unitCost),
            'qty_on_hand' => $newQty,
            'currency' => $currency,
        ])->save();
    }

    /**
     * Abgang aus dem Buch zum aktuellen Durchschnitt; der Durchschnitt bleibt.
     *
     * @param numeric-string $qty
     * @return array{unit: numeric-string, total: numeric-string, currency: string}
     */
    private function takeFromBook(ArticleVariant $variant, Warehouse $warehouse, string $qty): array {
        $valuation = $this->valuationFor($variant, $warehouse, lock: true);
        $avg = $valuation->exists ? ($valuation->avg_cost?->getAmount() ?? '0') : '0';
        $currency = $valuation->exists ? $valuation->currency->value : 'EUR';
        $valuation->fill([
            'organization_id' => $variant->organization_id,
            'avg_cost' => $avg,
            'qty_on_hand' => bcsub($valuation->exists ? $valuation->qty_on_hand : '0', $qty, self::SCALE),
            'currency' => $currency,
        ])->save();

        return ['unit' => bcadd($avg, '0', self::SCALE), 'total' => bcmul($qty, $avg, self::SCALE), 'currency' => $currency];
    }

    private function valuationFor(ArticleVariant $variant, Warehouse $warehouse, bool $lock = false): StockValuation {
        /** @var StockValuation $valuation */
        $valuation = StockValuation::query()
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->firstOrNew([
                'article_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
            ]);

        return $valuation;
    }
}
