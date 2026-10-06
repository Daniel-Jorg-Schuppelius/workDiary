<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FifoValuationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\InventoryValuationStrategy;
use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState, ValuationMethod};
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockMovement, StockValuationLayer, Warehouse};
use App\Support\DecimalQty;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FIFO-Bestandsbewertung über Zugangsschichten (Feature 048, E3). Jeder
 * Wareneingang legt eine {@see StockValuationLayer} an; ein Abgang verbraucht die
 * ältesten Schichten zuerst (acquired_at, dann id) und schreibt die exakten
 * Schicht-Kosten als unveränderlichen Snapshot an seine Bewegungen (je Charge
 * eine, {@see InventoryLedger::issue()}). Historie
 * bleibt unverändert; Verfahren je Organisation wählbar
 * ({@see \App\Services\Inventory\InventoryValuationManager}).
 */
class FifoValuationService implements InventoryValuationStrategy {
    public const SCALE = 4;

    public function __construct(private readonly InventoryLedger $ledger) {}

    public function method(): ValuationMethod {
        return ValuationMethod::Fifo;
    }

    public function receipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency = 'EUR', ?int $actorUserId = null, ?Model $source = null): StockMovement {
        return $this->applyReceipt($variant, $warehouse, $qty, $unitCost, $currency, $actorUserId, null, $source);
    }

    /** Wareneingang einer konkreten Charge (E2): Schicht trägt Los + Verfallsdatum (FEFO). */
    public function receiptIntoLot(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, StockLot $lot, string $currency = 'EUR', ?int $actorUserId = null): StockMovement {
        return $this->applyReceipt($variant, $warehouse, $qty, $unitCost, $currency, $actorUserId, $lot);
    }

    private function applyReceipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency, ?int $actorUserId, ?StockLot $lot, ?Model $source = null): StockMovement {
        $qty = DecimalQty::positive($qty);
        $unitCost = DecimalQty::positive($unitCost);

        return DB::transaction(function () use ($variant, $warehouse, $qty, $unitCost, $currency, $actorUserId, $lot, $source): StockMovement {
            $movement = $this->ledger->post(new StockPosting(
                $variant, $warehouse, StockState::Physical, $qty, StockMovementType::Receipt,
                OwnershipType::Own, actorUserId: $actorUserId, source: $source,
                costUnit: $unitCost, costTotal: bcmul($qty, $unitCost, self::SCALE), currency: $currency,
                stockLotId: $lot?->id,
            ));

            StockValuationLayer::query()->create([
                'organization_id' => $variant->organization_id,
                'article_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'stock_lot_id' => $lot?->id,
                'qty_remaining' => $qty,
                'unit_cost' => $unitCost,
                'currency' => $currency,
                'source_movement_id' => $movement->id,
                'acquired_at' => Carbon::now(),
                'best_before' => $lot?->best_before,
            ]);

            return $movement;
        });
    }

    /**
     * Abgang über das Lagerbuch (Charge gewählt oder FEFO-zugeteilt); jeder
     * Teil verbraucht zuerst die Schichten seiner Charge — der Teil ohne
     * Charge die chargenlosen —, danach die übrigen in Verfahrensfolge.
     */
    public function issue(ArticleVariant $variant, Warehouse $warehouse, string $qty, bool $allowNegative = false, ?int $actorUserId = null, ?StockLot $lot = null, bool $requireLot = false): StockIssue {
        return $this->ledger->issue(
            $variant, $warehouse, $qty,
            allowNegative: $allowNegative,
            actorUserId: $actorUserId,
            lot: $lot,
            costing: fn (?StockLot $partLot, string $partQty): array => $this->consumeLayers($variant, $warehouse, $partLot, $partQty),
            requireLot: $requireLot,
        );
    }

    /**
     * Verbraucht Schichten für einen Teil des Abgangs und liefert dessen Kosten.
     *
     * @param  numeric-string  $qty
     * @return array{unit: numeric-string, total: numeric-string}
     */
    private function consumeLayers(ArticleVariant $variant, Warehouse $warehouse, ?StockLot $lot, string $qty): array {
        $remaining = $qty;
        $costTotal = '0';
        $lastCost = '0';

        $layers = $this->layerQuery($variant, $warehouse)->lockForUpdate()->get();
        $ofThisLot = fn (StockValuationLayer $layer): bool => $lot === null ? $layer->stock_lot_id === null : (int) $layer->stock_lot_id === $lot->id;

        foreach ($layers->filter($ofThisLot)->concat($layers->reject($ofThisLot)) as $layer) {
            if (bccomp($remaining, '0', self::SCALE) <= 0) {
                break;
            }
            $lastCost = $layer->unit_cost?->getAmount() ?? '0';
            $take = bccomp($layer->qty_remaining, $remaining, self::SCALE) <= 0 ? $layer->qty_remaining : $remaining;
            $costTotal = bcadd($costTotal, bcmul($take, $layer->unit_cost?->getAmount() ?? '0', self::SCALE), self::SCALE);
            $layer->qty_remaining = bcsub($layer->qty_remaining, $take, self::SCALE);
            $layer->save();
            $remaining = bcsub($remaining, $take, self::SCALE);
        }

        // Restmenge ohne deckende Schicht (Negativbestand) zum zuletzt bekannten Einzelpreis bewerten;
        // ohne durchlaufene Schicht den Preis der jüngsten historischen Schicht (sonst 0-Bewertung, Verzerrung).
        if (bccomp($remaining, '0', self::SCALE) > 0) {
            if (bccomp($lastCost, '0', self::SCALE) === 0) {
                $historic = StockValuationLayer::query()
                    ->where('article_variant_id', $variant->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->orderByDesc('acquired_at')
                    ->orderByDesc('id')
                    ->first();
                if ($historic instanceof StockValuationLayer) {
                    $lastCost = $historic->unit_cost?->getAmount() ?? '0';
                }
            }
            $costTotal = bcadd($costTotal, bcmul($remaining, $lastCost, self::SCALE), self::SCALE);
        }

        return ['unit' => NumberHelper::divideOrDefault($costTotal, $qty, self::SCALE), 'total' => $costTotal];
    }

    /** Ist-Stückkosten = Kosten der nächsten zu entnehmenden Schicht (FIFO/FEFO). @return numeric-string */
    public function unitCost(ArticleVariant $variant, Warehouse $warehouse): string {
        $layer = $this->layerQuery($variant, $warehouse)->first();

        return $layer instanceof StockValuationLayer ? bcadd($layer->unit_cost?->getAmount() ?? '0', '0', self::SCALE) : '0';
    }

    public function onHand(ArticleVariant $variant, Warehouse $warehouse): string {
        $sum = (string) StockValuationLayer::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->sum('qty_remaining');

        return bcadd($sum, '0', self::SCALE);
    }

    public function totalValue(ArticleVariant $variant, Warehouse $warehouse): string {
        $layers = StockValuationLayer::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->get(['qty_remaining', 'unit_cost']);

        $total = '0';
        foreach ($layers as $layer) {
            $total = bcadd($total, bcmul($layer->qty_remaining, $layer->unit_cost?->getAmount() ?? '0', self::SCALE), self::SCALE);
        }

        return $total;
    }

    /**
     * Schicht-Reihenfolge für die Entnahme (FIFO: ältester Zugang zuerst).
     * Unterklassen (FEFO) überschreiben die Sortierung.
     *
     * @return Builder<StockValuationLayer>
     */
    protected function layerQuery(ArticleVariant $variant, Warehouse $warehouse): Builder {
        return StockValuationLayer::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('qty_remaining', '>', 0)
            ->orderBy('acquired_at')
            ->orderBy('id');
    }
}
