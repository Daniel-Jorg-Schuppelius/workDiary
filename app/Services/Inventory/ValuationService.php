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
use App\Models\{ArticleVariant, StockMovement, StockValuation, Warehouse};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bestandsbewertung mit gleitendem Durchschnittspreis (Feature 048, MVP-070).
 * Jede bewertungsrelevante Bewegung erhält einen UNVERÄNDERLICHEN Kostensnapshot
 * (cost_unit/cost_total an der append-only stock_movements-Zeile). Der laufende
 * Durchschnitt wird je Variante/Lagerort fortgeschrieben; Abgänge werden zum
 * aktuellen Durchschnitt bewertet (der Durchschnitt bleibt dabei unverändert).
 * Spätere Preisänderungen verändern historische Bewegungen nicht.
 */
class ValuationService implements InventoryValuationStrategy {
    public const SCALE = 4;

    public function __construct(private readonly InventoryLedger $ledger) {}

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

    /** Wareneingang mit Einzelkosten: aktualisiert den gleitenden Durchschnitt. */
    public function receipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency = 'EUR', ?int $actorUserId = null, ?Model $source = null): StockMovement {
        $qty = $this->positive($qty);
        $unitCost = $this->positive($unitCost);

        return DB::transaction(function () use ($variant, $warehouse, $qty, $unitCost, $currency, $actorUserId, $source): StockMovement {
            $valuation = $this->valuationFor($variant, $warehouse);
            $oldQty = $valuation->exists ? $valuation->qty_on_hand : '0';
            $oldAvg = $valuation->exists ? $valuation->avg_cost : '0';

            $newQty = bcadd($oldQty, $qty, self::SCALE);
            $oldValue = bcmul($oldQty, $oldAvg, self::SCALE);
            $addValue = bcmul($qty, $unitCost, self::SCALE);
            $newValue = bcadd($oldValue, $addValue, self::SCALE);
            $newAvg = NumberHelper::divideOrDefault($newValue, $newQty, self::SCALE, $unitCost);

            $valuation->fill([
                'organization_id' => $variant->organization_id,
                'avg_cost' => $newAvg,
                'qty_on_hand' => $newQty,
                'currency' => $currency,
            ])->save();

            return $this->ledger->post(new StockPosting(
                $variant, $warehouse, StockState::Physical, $qty, StockMovementType::Receipt,
                OwnershipType::Own, actorUserId: $actorUserId, source: $source,
                costUnit: $unitCost, costTotal: $addValue, currency: $currency,
            ));
        });
    }

    /** Abgang zum aktuellen Durchschnitt bewertet (Durchschnitt unverändert). */
    public function issue(ArticleVariant $variant, Warehouse $warehouse, string $qty, bool $allowNegative = false, ?int $actorUserId = null): StockMovement {
        $qty = $this->positive($qty);

        return DB::transaction(function () use ($variant, $warehouse, $qty, $allowNegative, $actorUserId): StockMovement {
            // Verfügbarkeit UNTER Zeilensperre in der Transaktion prüfen (wie FifoValuationService): der ungesperrte
            // Check davor war TOCTOU — zwei parallele Abgänge buchten zusammen ins Minus (Moving-Average-Verzerrung).
            if (! $allowNegative && bccomp($this->ledger->availableForUpdate($variant, $warehouse), $qty, self::SCALE) < 0) {
                throw new RuntimeException('Abgang übersteigt den verfügbaren Bestand.');
            }

            $valuation = $this->valuationFor($variant, $warehouse);
            $avg = $valuation->exists ? $valuation->avg_cost : '0';
            $costTotal = bcmul($qty, $avg, self::SCALE);

            $valuation->fill([
                'organization_id' => $variant->organization_id,
                'avg_cost' => $avg,
                'qty_on_hand' => bcsub($valuation->exists ? $valuation->qty_on_hand : '0', $qty, self::SCALE),
                'currency' => $valuation->exists ? $valuation->currency : 'EUR',
            ])->save();

            return $this->ledger->post(new StockPosting(
                $variant, $warehouse, StockState::Physical, bcmul($qty, '-1', self::SCALE), StockMovementType::Issue,
                OwnershipType::Own, actorUserId: $actorUserId,
                costUnit: $avg, costTotal: $costTotal,
            ));
        });
    }

    /** @return numeric-string */
    public function average(ArticleVariant $variant, Warehouse $warehouse): string {
        $valuation = $this->valuationFor($variant, $warehouse);

        return $valuation->exists ? $valuation->avg_cost : '0';
    }

    /** Bewerteter Gesamtbestand = Durchschnitt × Menge. @return numeric-string */
    public function totalValue(ArticleVariant $variant, Warehouse $warehouse): string {
        $valuation = $this->valuationFor($variant, $warehouse);
        if (! $valuation->exists) {
            return '0';
        }

        return bcmul($valuation->qty_on_hand, $valuation->avg_cost, self::SCALE);
    }

    private function valuationFor(ArticleVariant $variant, Warehouse $warehouse): StockValuation {
        /** @var StockValuation $valuation */
        $valuation = StockValuation::query()->firstOrNew([
            'article_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
        ]);

        return $valuation;
    }

    /** @return numeric-string */
    private function positive(string $value): string {
        $value = NumberHelper::normalizeDecimalString($value);
        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bccomp($value, '0', self::SCALE) < 0 ? bcmul($value, '-1', self::SCALE) : $value;
    }
}
