<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PickListBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{ReservationStatus, StockState};
use App\Models\{ArticleVariant, StockLot, StockMovement, StockReservation, Warehouse, WarehouseBin};
use App\Support\DecimalQty;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Kommissionierliste (Feature 048, MVP-706): aus den aktiven Reservierungen
 * einer Quelle (Fertigungsauftrag …) oder expliziten Zeilen entstehen
 * Entnahmepositionen. Ohne festen Platz wird die Menge über die Plätze mit
 * physischem Bestand (Reihenfolge sort_order) verteilt, innerhalb eines
 * Platzes über Chargen nach FEFO (frühestes MHD zuerst); ein Rest ohne
 * Deckung bleibt als eigene Position sichtbar (Fehlmenge).
 */
final class PickListBuilder {
    public function __construct(private readonly InventoryLedger $ledger) {}

    /** Aus den aktiven Reservierungen einer fachlichen Quelle (source_type/source_id). */
    public function forSource(Model $source): PickList {
        $reservations = StockReservation::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('status', ReservationStatus::Active->value)
            ->with(['variant.article', 'warehouse', 'bin'])
            ->orderBy('priority')->orderBy('reserved_at')
            ->get();

        return $this->forReservations($reservations, $source);
    }

    /** @param Collection<int, StockReservation> $reservations */
    public function forReservations(Collection $reservations, ?Model $source = null): PickList {
        $requests = [];
        foreach ($reservations as $reservation) {
            $open = $reservation->openQuantity();
            $variant = $reservation->variant;
            $warehouse = $reservation->warehouse;
            if ($variant === null || $warehouse === null || bccomp($open, '0', InventoryLedger::SCALE) <= 0) {
                continue;
            }
            $requests[] = ['variant' => $variant, 'warehouse' => $warehouse, 'qty' => $open, 'bin' => $reservation->bin];
        }

        return $this->fromLines($requests, $source);
    }

    /**
     * Explizite Zeilen (Menge in Basiseinheit; Platz optional).
     *
     * @param list<array{variant: ArticleVariant, warehouse: Warehouse, qty: string, bin?: WarehouseBin|null}> $lines
     */
    public function fromLines(array $lines, ?Model $source = null): PickList {
        $result = [];
        foreach ($lines as $line) {
            $variant = $line['variant'];
            $warehouse = $line['warehouse'];
            $qty = DecimalQty::positive($line['qty']);
            $bin = $line['bin'] ?? null;

            foreach ($this->allocateBins($variant, $warehouse, $qty, $bin) as [$place, $placeQty]) {
                foreach ($this->allocateLots($variant, $warehouse, $place, $placeQty) as [$lot, $lotQty, $lotAvailable]) {
                    $result[] = new PickListLine(
                        $variant,
                        $warehouse,
                        $place,
                        $lot,
                        $lotQty,
                        (string) ($variant->article->base_unit ?? ''),
                        $lotAvailable ?? $this->availableAt($variant, $warehouse, $place),
                    );
                }
            }
        }

        usort($result, self::compare(...));

        return new PickList($result, $source);
    }

    /**
     * Verteilt die Menge auf Plätze: fester Platz → genau dieser; sonst die
     * Plätze mit physischem Bestand in sort_order, Rest ohne Platz.
     *
     * @param  numeric-string  $qty
     * @return list<array{0: WarehouseBin|null, 1: numeric-string}>
     */
    private function allocateBins(ArticleVariant $variant, Warehouse $warehouse, string $qty, ?WarehouseBin $bin): array {
        if ($bin !== null) {
            return [[$bin, $qty]];
        }

        $balances = $this->ledger->balancesByBin($variant, $warehouse, StockState::Physical);
        $binIds = array_values(array_filter(array_keys($balances), fn (int $id): bool => $id > 0));
        if ($binIds === []) {
            return [[null, $qty]];
        }

        $bins = WarehouseBin::query()->whereIn('id', $binIds)->orderBy('sort_order')->orderBy('code')->get();
        $remaining = $qty;
        $parts = [];
        foreach ($bins as $candidate) {
            $stock = $balances[(int) $candidate->id] ?? '0';
            if (bccomp($stock, '0', InventoryLedger::SCALE) <= 0 || ! $candidate->isUsable()) {
                continue;
            }
            $take = bccomp($stock, $remaining, InventoryLedger::SCALE) < 0 ? $stock : $remaining;
            $parts[] = [$candidate, $take];
            $remaining = bcsub($remaining, $take, InventoryLedger::SCALE);
            if (bccomp($remaining, '0', InventoryLedger::SCALE) <= 0) {
                break;
            }
        }
        if (bccomp($remaining, '0', InventoryLedger::SCALE) > 0) {
            $parts[] = [null, $remaining];
        }

        return $parts;
    }

    /**
     * Verteilt die Platzmenge über Chargen nach FEFO; ohne Chargenbestand eine
     * Zeile ohne Charge. Verfügbar je Chargenzeile = physischer Chargenbestand am Ort.
     *
     * @param  numeric-string  $qty
     * @return list<array{0: StockLot|null, 1: numeric-string, 2: numeric-string|null}>
     */
    private function allocateLots(ArticleVariant $variant, Warehouse $warehouse, ?WarehouseBin $bin, string $qty): array {
        $lotBalances = [];
        $rows = StockMovement::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('stock_state', StockState::Physical->value)
            ->whereNotNull('stock_lot_id')
            ->when($bin !== null, fn ($q) => $q->where('bin_id', $bin?->id))
            ->toBase()
            ->get(['stock_lot_id', 'qty_base']);
        foreach ($rows as $row) {
            $lotId = (int) $row->stock_lot_id;
            $lotBalances[$lotId] = bcadd($lotBalances[$lotId] ?? '0', NumberHelper::normalizeDecimalString((string) $row->qty_base), InventoryLedger::SCALE);
        }
        $lotBalances = array_filter($lotBalances, fn (string $sum): bool => bccomp($sum, '0', InventoryLedger::SCALE) > 0);
        if ($lotBalances === []) {
            return [[null, $qty, null]];
        }

        $lots = StockLot::query()->whereIn('id', array_keys($lotBalances))
            ->orderByRaw('best_before IS NULL')->orderBy('best_before')->orderBy('lot_no')
            ->get();
        $remaining = $qty;
        $parts = [];
        foreach ($lots as $lot) {
            $stock = $lotBalances[(int) $lot->id];
            $take = bccomp($stock, $remaining, InventoryLedger::SCALE) < 0 ? $stock : $remaining;
            $parts[] = [$lot, $take, $stock];
            $remaining = bcsub($remaining, $take, InventoryLedger::SCALE);
            if (bccomp($remaining, '0', InventoryLedger::SCALE) <= 0) {
                break;
            }
        }
        if (bccomp($remaining, '0', InventoryLedger::SCALE) > 0) {
            $parts[] = [null, $remaining, null];
        }

        return $parts;
    }

    /** @return numeric-string */
    private function availableAt(ArticleVariant $variant, Warehouse $warehouse, ?WarehouseBin $bin): string {
        return $bin === null
            ? $this->ledger->available($variant, $warehouse)
            : $this->ledger->availableInBin($variant, $warehouse, $bin);
    }

    /** Sortierung Lager → Platz (sort_order, ohne Platz zuletzt) → Charge FEFO → SKU. */
    private static function compare(PickListLine $a, PickListLine $b): int {
        return [$a->warehouse->name, $a->warehouse->id]
            <=> [$b->warehouse->name, $b->warehouse->id]
            ?: [$a->bin === null ? 1 : 0, $a->bin->sort_order ?? 0, $a->bin->code ?? '']
            <=> [$b->bin === null ? 1 : 0, $b->bin->sort_order ?? 0, $b->bin->code ?? '']
            ?: [$a->lot === null ? 1 : 0, $a->lot?->best_before?->toDateString() ?? '9999-12-31', $a->lot->lot_no ?? '']
            <=> [$b->lot === null ? 1 : 0, $b->lot?->best_before?->toDateString() ?? '9999-12-31', $b->lot->lot_no ?? '']
            ?: $a->sku() <=> $b->sku();
    }
}
