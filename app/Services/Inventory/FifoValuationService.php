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
use App\Models\Inventory\{StockLot, StockMovement, StockValuationLayer, Warehouse, WarehouseBin};
use App\Support\DecimalQty;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Decimal, Money};
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
    public const int SCALE = 4;

    public function __construct(private readonly InventoryLedger $ledger) {}

    public function method(): ValuationMethod {
        return ValuationMethod::Fifo;
    }

    public function receipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency = 'EUR', ?int $actorUserId = null, ?Model $source = null, ?EnteredQuantity $entered = null, ?WarehouseBin $bin = null, StockMovementType $type = StockMovementType::Receipt): StockMovement {
        return $this->applyReceipt($variant, $warehouse, $qty, $unitCost, $currency, $actorUserId, null, $source, $entered, $bin, $type);
    }

    /** Wareneingang einer konkreten Charge (E2): Schicht trägt Los + Verfallsdatum (FEFO). */
    public function receiptIntoLot(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, StockLot $lot, string $currency = 'EUR', ?int $actorUserId = null, ?EnteredQuantity $entered = null): StockMovement {
        return $this->applyReceipt($variant, $warehouse, $qty, $unitCost, $currency, $actorUserId, $lot, null, $entered);
    }

    private function applyReceipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency, ?int $actorUserId, ?StockLot $lot, ?Model $source = null, ?EnteredQuantity $entered = null, ?WarehouseBin $bin = null, StockMovementType $type = StockMovementType::Receipt): StockMovement {
        $qty = DecimalQty::positive($qty);
        $unitCost = DecimalQty::positive($unitCost);

        return DB::transaction(function () use ($variant, $warehouse, $qty, $unitCost, $currency, $actorUserId, $lot, $source, $entered, $bin, $type): StockMovement {
            $movement = $this->ledger->post(new StockPosting(
                $variant, $warehouse, StockState::Physical, $qty, $type,
                OwnershipType::Own, originalQty: $entered?->originalQty, originalUnit: $entered?->originalUnit,
                actorUserId: $actorUserId, source: $source,
                costUnit: $unitCost, costTotal: bcmul($qty, $unitCost, self::SCALE), currency: $currency,
                stockLotId: $lot?->id, bin: $bin,
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
    public function issue(ArticleVariant $variant, Warehouse $warehouse, string $qty, bool $allowNegative = false, ?int $actorUserId = null, ?StockLot $lot = null, bool $requireLot = false, ?EnteredQuantity $entered = null, ?string $idempotencyKey = null, ?Model $source = null, ?WarehouseBin $bin = null): StockIssue {
        return $this->ledger->issue(
            $variant, $warehouse, $qty,
            entered: $entered,
            allowNegative: $allowNegative,
            idempotencyKey: $idempotencyKey,
            actorUserId: $actorUserId,
            lot: $lot,
            costing: fn (?StockLot $partLot, string $partQty): array => $this->consumeLayers($variant, $warehouse, $partLot, $partQty),
            requireLot: $requireLot,
            source: $source,
            bin: $bin,
        );
    }

    /**
     * Rückbuchung eines Abgangs: Return-Bewegung in die Charge des Abgangs
     * (gesperrte Charge hält die Menge mit) und eine neue Schicht zum
     * Stückkostenwert des Abgangs. Eine wiederholte Buchung mit demselben
     * Schlüssel legt keine zweite Schicht an.
     */
    public function returnToStock(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, string $currency = 'EUR', ?int $actorUserId = null, ?Model $source = null, ?StockLot $lot = null, ?string $idempotencyKey = null): StockMovement {
        $qty = DecimalQty::positive($qty);
        $unitCost = DecimalQty::positive($unitCost);

        return DB::transaction(function () use ($variant, $warehouse, $qty, $unitCost, $currency, $actorUserId, $source, $lot, $idempotencyKey): StockMovement {
            // LotService hängt selbst an FEFO — erst hier auflösen, sonst kreist der Container.
            $movement = app(LotService::class)->holdArrival($lot, fn (): StockMovement => $this->ledger->post(new StockPosting(
                $variant, $warehouse, StockState::Physical, $qty, StockMovementType::Return, OwnershipType::Own,
                idempotencyKey: $idempotencyKey, actorUserId: $actorUserId, source: $source,
                costUnit: $unitCost, costTotal: bcmul($qty, $unitCost, self::SCALE), currency: $currency,
                stockLotId: $lot?->id,
            )));

            if ($movement->wasRecentlyCreated) {
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
            }

            return $movement;
        });
    }

    /**
     * Umlagerung (MVP-1726): der Abgang verbraucht die Schichten des Quelllagers nach Verfahren, das Ziellager erhält
     * je verbrauchter Schicht eine Schicht mit deren Stückwert, Zugangsdatum und MHD — die Reihenfolge bleibt.
     */
    public function transfer(ArticleVariant $variant, Warehouse $from, Warehouse $to, string $qty, ?int $actorUserId = null, ?StockLot $lot = null, ?EnteredQuantity $entered = null, ?string $idempotencyKey = null): StockIssue {
        return DB::transaction(function () use ($variant, $from, $to, $qty, $actorUserId, $lot, $entered, $idempotencyKey): StockIssue {
            $slices = [];
            $leaving = $this->ledger->issue(
                $variant, $from, $qty,
                idempotencyKey: $idempotencyKey,
                actorUserId: $actorUserId,
                lot: $lot,
                costing: function (?StockLot $partLot, string $partQty) use ($variant, $from, &$slices): array {
                    $taken = $this->takeLayers($variant, $from, $partLot, $partQty);
                    $slices[] = $taken;

                    return self::costOf($taken, $partQty);
                },
                entered: $entered,
                type: StockMovementType::TransferOut,
            );

            foreach ($leaving->movements as $index => $out) {
                $arrival = $this->ledger->transferIn($variant, $out, $to, $entered);
                if (! $arrival->wasRecentlyCreated) {
                    continue;
                }
                $partLot = $out->lot;
                foreach ($slices[$index] ?? [] as $slice) {
                    StockValuationLayer::query()->create([
                        'organization_id' => $variant->organization_id,
                        'article_variant_id' => $variant->id,
                        'warehouse_id' => $to->id,
                        'stock_lot_id' => $partLot?->id,
                        'qty_remaining' => $slice['qty'],
                        'unit_cost' => $slice['unit_cost'],
                        'currency' => $slice['currency'],
                        'source_movement_id' => $arrival->id,
                        'acquired_at' => $slice['acquired_at'] ?? Carbon::now(),
                        'best_before' => $partLot !== null ? $partLot->best_before : $slice['best_before'],
                    ]);
                }
            }

            return $leaving;
        });
    }

    /**
     * Mehrbestand legt eine Schicht zum Stückwert an wie ein Eingang, Minderbestand verbraucht Schichten nach Verfahren
     * (zuerst die der Charge der Buchung) — MVP-1727.
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
            $lot = $posting->stockLotId !== null ? StockLot::query()->find($posting->stockLotId) : null;
            $qty = DecimalQty::positive($posting->signedQty);
            if (! NumberHelper::isPositivePrecise($posting->signedQty, self::SCALE)) {
                $cost = self::costOf($this->takeLayers($variant, $warehouse, $lot, $qty), $qty);

                return $this->ledger->post($posting->withCost($cost['unit'], $cost['total'], $cost['currency']));
            }

            $unit = $unitCost?->withScale(self::SCALE)->getAmount() ?? '0';
            $currency = $unitCost?->getCurrency()->value ?? ($this->averageCost($variant, $warehouse)?->getCurrency()->value ?? 'EUR');
            $movement = $this->ledger->post($posting->withCost($unit, bcmul($qty, $unit, self::SCALE), $currency));
            StockValuationLayer::query()->create([
                'organization_id' => $variant->organization_id,
                'article_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'stock_lot_id' => $lot?->id,
                'qty_remaining' => $qty,
                'unit_cost' => $unit,
                'currency' => $currency,
                'source_movement_id' => $movement->id,
                'acquired_at' => Carbon::now(),
                'best_before' => $lot?->best_before,
            ]);

            return $movement;
        });
    }

    /**
     * Verbraucht Schichten für einen Teil des Abgangs und liefert dessen Kosten.
     *
     * @param  numeric-string  $qty
     * @return array{unit: numeric-string, total: numeric-string, currency: string}
     */
    private function consumeLayers(ArticleVariant $variant, Warehouse $warehouse, ?StockLot $lot, string $qty): array {
        return self::costOf($this->takeLayers($variant, $warehouse, $lot, $qty), $qty);
    }

    /**
     * Entnimmt einen Teil des Abgangs aus den Schichten — zuerst die seiner Charge, der Teil ohne Charge die
     * chargenlosen, danach die übrigen in Verfahrensfolge — und liefert die entnommenen Scheiben.
     *
     * @param  numeric-string  $qty
     * @return list<array{qty: numeric-string, unit_cost: numeric-string, currency: string, acquired_at: \Carbon\CarbonInterface|null, best_before: \Carbon\CarbonInterface|null}>
     */
    private function takeLayers(ArticleVariant $variant, Warehouse $warehouse, ?StockLot $lot, string $qty): array {
        $remaining = $qty;
        $lastCost = '0';
        $lastCurrency = 'EUR';
        $slices = [];

        $layers = $this->layerQuery($variant, $warehouse)->lockForUpdate()->get();
        $ofThisLot = fn (StockValuationLayer $layer): bool => $lot === null ? $layer->stock_lot_id === null : (int) $layer->stock_lot_id === $lot->id;

        foreach ($layers->filter($ofThisLot)->concat($layers->reject($ofThisLot)) as $layer) {
            if (bccomp($remaining, '0', self::SCALE) <= 0) {
                break;
            }
            $lastCost = $layer->unit_cost?->getAmount() ?? '0';
            $lastCurrency = $layer->currency->value;
            $take = bccomp($layer->qty_remaining, $remaining, self::SCALE) <= 0 ? $layer->qty_remaining : $remaining;
            $slices[] = ['qty' => bcadd($take, '0', self::SCALE), 'unit_cost' => $lastCost, 'currency' => $lastCurrency, 'acquired_at' => $layer->acquired_at, 'best_before' => $layer->best_before];
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
                    $lastCurrency = $historic->currency->value;
                }
            }
            $slices[] = ['qty' => bcadd($remaining, '0', self::SCALE), 'unit_cost' => $lastCost, 'currency' => $lastCurrency, 'acquired_at' => null, 'best_before' => $lot?->best_before];
        }

        return $slices;
    }

    /**
     * Kosten eines Abgangsteils aus seinen Scheiben.
     *
     * @param  list<array{qty: numeric-string, unit_cost: numeric-string, currency: string, acquired_at: \Carbon\CarbonInterface|null, best_before: \Carbon\CarbonInterface|null}>  $slices
     * @param  numeric-string  $qty
     * @return array{unit: numeric-string, total: numeric-string, currency: string}
     */
    private static function costOf(array $slices, string $qty): array {
        $total = '0';
        foreach ($slices as $slice) {
            $total = bcadd($total, bcmul($slice['qty'], $slice['unit_cost'], self::SCALE), self::SCALE);
        }

        return ['unit' => NumberHelper::divideOrDefault($total, $qty, self::SCALE), 'total' => $total, 'currency' => $slices[0]['currency'] ?? 'EUR'];
    }

    /** Wert der Restschichten durch ihre Menge, ohne Rest die jüngste Schicht; nur Schichten in der Währung der jüngsten. */
    public function averageCost(ArticleVariant $variant, Warehouse $warehouse): ?Money {
        $layers = StockValuationLayer::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->orderByDesc('acquired_at')
            ->orderByDesc('id')
            ->get(['id', 'qty_remaining', 'unit_cost', 'currency']);
        $latest = $layers->first();
        if (! $latest instanceof StockValuationLayer) {
            return null;
        }

        $currency = $latest->currency;
        $open = $layers->filter(static fn (StockValuationLayer $layer): bool => $layer->currency === $currency
            && NumberHelper::isPositivePrecise(NumberHelper::normalizeDecimalString((string) $layer->qty_remaining), self::SCALE));
        if ($open->isEmpty()) {
            return $latest->unit_cost ?? Money::zero($currency, self::SCALE);
        }

        $qty = Decimal::sum($open->map(static fn (StockValuationLayer $layer): Decimal => Decimal::of((string) $layer->qty_remaining, self::SCALE))->all(), self::SCALE);
        $value = Money::sum($open->map(static fn (StockValuationLayer $layer): Money => ($layer->unit_cost ?? Money::zero($currency, self::SCALE))->times((string) $layer->qty_remaining))->all(), $currency, self::SCALE);

        return $value->dividedBy($qty->getValue());
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

    /**
     * Schichtbewertung aller Varianten eines Lagers in einer Query: Wert = Restmenge × Stückkosten
     * je Schicht, Durchschnitt = Wert / Restmenge.
     *
     * @return array<int, array{avg: numeric-string, value: numeric-string}>
     */
    public function summariesForWarehouse(Warehouse $warehouse): array {
        $sums = [];
        foreach (StockValuationLayer::query()->where('warehouse_id', $warehouse->id)->where('qty_remaining', '>', 0)->get(['article_variant_id', 'qty_remaining', 'unit_cost']) as $layer) {
            $variantId = (int) $layer->article_variant_id;
            $sums[$variantId] ??= ['qty' => '0', 'value' => '0'];
            $sums[$variantId]['qty'] = bcadd($sums[$variantId]['qty'], $layer->qty_remaining, self::SCALE);
            $sums[$variantId]['value'] = bcadd($sums[$variantId]['value'], bcmul($layer->qty_remaining, $layer->unit_cost?->getAmount() ?? '0', self::SCALE), self::SCALE);
        }

        return array_map(static fn (array $sum): array => [
            'avg' => NumberHelper::divideOrDefault($sum['value'], $sum['qty'], self::SCALE),
            'value' => $sum['value'],
        ], $sums);
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
