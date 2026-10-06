<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotSplitService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{OwnershipType, StockLotStatus, StockMovementType, StockState};
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockMovement, StockValuationLayer, Warehouse, WarehouseBin};
use App\Services\Concerns\AssertsStatusTransition;
use App\Support\DecimalQty;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Los-Split und -Merge (Feature 047/048, E6/E7). Beide buchen den Bestand im
 * Lagerbuch über Gegenbuchungen um (Umlagerung Abgang/Zugang, ohne Kosten) und
 * verschieben die FIFO/FEFO-Bewertungsschichten mit ihren Einzelkosten. Split
 * nimmt die Menge Topf für Topf (Lager, Platz, Eigentum aufsteigend), Merge
 * führt zwei Chargen derselben Variante vollständig zusammen; die ausgeräumte
 * Charge wird als „merged" markiert.
 */
class LotSplitService {
    use AssertsStatusTransition;

    public const SCALE = 4;

    /** Idempotenzschlüssel der Umbuchungen; {@see ExternalStockMirror} spiegelt sie nicht. */
    public const MERGE_KEY_PREFIX = 'lot-merge:';

    public const SPLIT_KEY_PREFIX = 'lot-split:';

    public function __construct(
        private readonly LotStockReader $lotStock,
        private readonly InventoryLedger $ledger,
    ) {}

    /**
     * Teilt `qty` aus einer Charge in eine neue Charge ab: geprüft gegen den
     * Buchsaldo, umgebucht Topf für Topf, die Schichten folgen den gebuchten
     * Lagern. Scheitert eine Buchung, scheitert das Teilen als Ganzes.
     */
    public function split(StockLot $source, string $qty, string $newLotNo, ?string $bestBefore = null, ?int $actorUserId = null): StockLot {
        $wanted = Decimal::of(DecimalQty::positive($qty), self::SCALE);
        $newLotNo = trim($newLotNo);
        if ($newLotNo === '') {
            throw new RuntimeException('Leere Ziel-Chargennummer.');
        }

        return DB::transaction(function () use ($source, $wanted, $newLotNo, $bestBefore, $actorUserId): StockLot {
            // Chargenzeile vor den Bewegungen sperren — dieselbe Folge wie beim Zusammenführen und Sperren.
            $locked = StockLot::query()->lockForUpdate()->find($source->id);
            // Die abgeteilte Charge entsteht aktiv — aus einer gesperrten käme Bestand ohne Freigabe in den Umlauf.
            if (! $locked instanceof StockLot || $locked->status !== StockLotStatus::Active) {
                throw new RuntimeException((string) __('inventory.lot.error.not_active', ['lot' => $source->lot_no]));
            }
            $pots = $this->lotStock->pots($locked, forUpdate: true);
            if ($wanted->greaterThan(Decimal::sum(array_column($pots, 'qty'), self::SCALE))) {
                throw new RuntimeException('Split übersteigt den Chargenbestand.');
            }

            /** @var StockLot $target */
            $target = StockLot::query()->create([
                'organization_id' => $locked->organization_id,
                'article_variant_id' => $locked->article_variant_id,
                'lot_no' => $newLotNo,
                'mfg_date' => $locked->mfg_date,
                'best_before' => $bestBefore ?? $locked->best_before?->format('Y-m-d'),
                'status' => StockLotStatus::Active,
            ]);

            $moved = $this->splitLedgerStock($locked, $target, $pots, $wanted, $actorUserId);
            $layers = StockValuationLayer::query()
                ->where('stock_lot_id', $locked->id)
                ->where('qty_remaining', '>', 0)
                ->orderBy('acquired_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $rest = Decimal::zero(self::SCALE);
            foreach ($moved as $warehouseId => $warehouseQty) {
                $rest = $rest->plus($this->moveLayers($layers->where('warehouse_id', $warehouseId), $warehouseQty, $target));
            }
            $this->moveLayers($layers, $rest, $target);

            return $target;
        });
    }

    /**
     * Bucht `$wanted` aus den Töpfen der Quelle (positive Salden, in Topffolge)
     * auf die Zielcharge um.
     *
     * @param  list<array{warehouse: Warehouse, bin: WarehouseBin|null, ownership: OwnershipType, ownerRef: string|null, qty: Decimal}>  $pots
     * @return array<int, Decimal> Lager → umgebuchte Menge, in Buchungsfolge
     */
    private function splitLedgerStock(StockLot $from, StockLot $into, array $pots, Decimal $wanted, ?int $actorUserId): array {
        $variant = $from->variant;
        if (! $variant instanceof ArticleVariant) {
            return [];
        }

        $moved = [];
        $remaining = $wanted;
        $index = 0;
        foreach ($pots as $pot) {
            if (! $remaining->isPositive()) {
                break;
            }
            if (! $pot['qty']->isPositive()) {
                continue;
            }
            $take = Decimal::min($pot['qty'], $remaining);
            $index++;
            foreach ([[$from, $take->negated(), StockMovementType::TransferOut, 'out'], [$into, $take, StockMovementType::TransferIn, 'in']] as [$lot, $signed, $type, $side]) {
                $this->ledger->post(new StockPosting(
                    $variant,
                    $pot['warehouse'],
                    StockState::Physical,
                    $signed->getValue(),
                    $type,
                    $pot['ownership'],
                    ownerRef: $pot['ownerRef'],
                    idempotencyKey: self::SPLIT_KEY_PREFIX . $from->id . ':' . $into->id . ':' . $index . ':' . $side,
                    actorUserId: $actorUserId,
                    source: $from,
                    stockLotId: $lot->id,
                    bin: $pot['bin'],
                ));
            }
            $warehouseId = (int) $pot['warehouse']->id;
            $moved[$warehouseId] = isset($moved[$warehouseId]) ? $moved[$warehouseId]->plus($take) : $take;
            $remaining = $remaining->minus($take);
        }

        return $moved;
    }

    /**
     * Verschiebt bis zu `$qty` aus den Schichten (in ihrer Folge) auf die
     * Zielcharge, Einzelkosten und Lager bleiben; liefert, was keine Schicht deckte.
     *
     * @param  Collection<int, StockValuationLayer>  $layers
     */
    private function moveLayers(Collection $layers, Decimal $qty, StockLot $into): Decimal {
        foreach ($layers as $layer) {
            if (! $qty->isPositive()) {
                break;
            }
            $left = Decimal::of((string) $layer->qty_remaining, self::SCALE);
            if (! $left->isPositive()) {
                continue;
            }
            $take = Decimal::min($left, $qty);
            $layer->qty_remaining = $left->minus($take)->getValue();
            $layer->save();

            StockValuationLayer::query()->create([
                'organization_id' => $layer->organization_id,
                'article_variant_id' => $layer->article_variant_id,
                'warehouse_id' => $layer->warehouse_id,
                'stock_lot_id' => $into->id,
                'qty_remaining' => $take->getValue(),
                'unit_cost' => $layer->unit_cost,
                'currency' => $layer->currency,
                'source_movement_id' => $layer->source_movement_id,
                'acquired_at' => $layer->acquired_at,
                'best_before' => $into->best_before?->format('Y-m-d'),
            ]);
            $qty = $qty->minus($take);
        }

        return $qty;
    }

    /** Führt die Quell-Charge vollständig in die Ziel-Charge zusammen. */
    public function merge(StockLot $from, StockLot $into, ?int $actorUserId = null): StockLot {
        if ($from->id === $into->id) {
            throw new RuntimeException('Charge kann nicht mit sich selbst zusammengeführt werden.');
        }
        if ((int) $from->article_variant_id !== (int) $into->article_variant_id) {
            throw new RuntimeException('Nur Chargen derselben Variante können zusammengeführt werden.');
        }

        return DB::transaction(function () use ($from, $into, $actorUserId): StockLot {
            // Gesperrt neu lesen: ein zweiter, gleichzeitiger Aufruf buchte den Bestand sonst doppelt um.
            $locked = StockLot::query()->whereKey([$from->id, $into->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $locked->get($from->id);
            $target = $locked->get($into->id);
            if (! $source instanceof StockLot || ! $target instanceof StockLot) {
                throw new RuntimeException((string) __('inventory.lot.flash.unknown'));
            }

            $this->assertStatusTransition($source->status, StockLotStatus::Merged);
            if ($target->status !== StockLotStatus::Active) {
                throw new RuntimeException((string) __('inventory.lot.error.not_active', ['lot' => $target->lot_no]));
            }

            $this->moveLedgerStock($source, $target, $actorUserId);
            StockValuationLayer::query()->where('stock_lot_id', $source->id)->update(['stock_lot_id' => $target->id]);
            $source->forceFill(['status' => StockLotStatus::Merged, 'merged_into_lot_id' => $target->id])->save();
            $source->audit('stock_lot.merged', ['lot_no' => $source->lot_no, 'into' => $target->lot_no]);

            return $target;
        });
    }

    /**
     * Bucht den Bestand der Quellcharge je Lagerort, Platz, Zustand und Eigentum
     * auf die Zielcharge um: Abgang an der Quelle, Zugang am Ziel, ohne Kosten —
     * die Bewertung hängt an den Schichten. Bestehende Bewegungen bleiben unberührt.
     */
    private function moveLedgerStock(StockLot $from, StockLot $into, ?int $actorUserId): void {
        $variant = $from->variant;
        if (! $variant instanceof ArticleVariant) {
            return;
        }

        $buckets = [];
        $rows = StockMovement::query()
            ->where('stock_lot_id', $from->id)
            ->lockForUpdate()
            ->toBase()
            ->get(['warehouse_id', 'bin_id', 'stock_state', 'ownership_type', 'owner_ref', 'qty_base']);
        foreach ($rows as $row) {
            $key = implode('|', [(int) $row->warehouse_id, (int) ($row->bin_id ?? 0), (string) $row->stock_state, (string) $row->ownership_type, (string) ($row->owner_ref ?? '')]);
            $buckets[$key]['row'] ??= $row;
            $buckets[$key]['qty'][] = Decimal::of((string) $row->qty_base, InventoryLedger::SCALE);
        }
        ksort($buckets);

        $index = 0;
        foreach ($buckets as $bucket) {
            $balance = Decimal::sum($bucket['qty'], InventoryLedger::SCALE);
            if ($balance->isZero()) {
                continue;
            }

            $row = $bucket['row'];
            $warehouse = Warehouse::query()->findOrFail((int) $row->warehouse_id);
            $bin = $row->bin_id !== null ? WarehouseBin::query()->findOrFail((int) $row->bin_id) : null;
            // Ein negativer Saldo wandert mit umgekehrten Vorzeichen, damit die Quelle in jedem Fall bei null endet.
            [$leaving, $arriving] = $balance->isPositive()
                ? [StockMovementType::TransferOut, StockMovementType::TransferIn]
                : [StockMovementType::TransferIn, StockMovementType::TransferOut];

            foreach ([[$from, $balance->negated(), $leaving, 'out'], [$into, $balance, $arriving, 'in']] as [$lot, $qty, $type, $side]) {
                $this->ledger->post(new StockPosting(
                    $variant,
                    $warehouse,
                    StockState::from((string) $row->stock_state),
                    $qty->getValue(),
                    $type,
                    OwnershipType::from((string) $row->ownership_type),
                    ownerRef: $row->owner_ref !== null ? (string) $row->owner_ref : null,
                    idempotencyKey: self::MERGE_KEY_PREFIX . $from->id . ':' . $into->id . ':' . $index . ':' . $side,
                    actorUserId: $actorUserId,
                    source: $from,
                    stockLotId: $lot->id,
                    bin: $bin,
                ));
            }
            $index++;
        }
    }
}
