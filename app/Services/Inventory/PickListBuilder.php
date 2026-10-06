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
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockReservation, Warehouse, WarehouseBin};
use App\Support\DecimalQty;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Kommissionierliste (Feature 048, MVP-706): aus den aktiven Reservierungen
 * einer Quelle (Fertigungsauftrag …) oder expliziten Zeilen entstehen
 * Entnahmepositionen. Ohne festen Platz wird die Menge über die Plätze mit
 * physischem Bestand (Reihenfolge sort_order) verteilt, innerhalb eines
 * Platzes über Chargen nach FEFO (frühestes MHD zuerst); ein Rest ohne
 * Deckung bleibt als eigene Position sichtbar (Fehlmenge). Vorgeschlagen
 * werden nur aktive Chargen: Bestand gesperrter Chargen bleibt liegen, der
 * einer zusammengeführten Altcharge zählt bei ihrer Zielcharge.
 */
final class PickListBuilder {
    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly LotStockReader $lotStock,
    ) {}

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

            $physical = $this->ledger->balancesByBin($variant, $warehouse, StockState::Physical);
            $lotStock = $this->lotStock->balances($variant, $warehouse);

            foreach ($this->allocateBins($qty, $bin, $physical, $lotStock) as [$place, $placeQty]) {
                foreach ($this->allocateLots($place, $placeQty, $physical, $lotStock) as [$lot, $lotQty, $lotAvailable]) {
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
     * Plätze mit physischem Bestand in sort_order, Rest ohne Platz. Bestand
     * gesperrter Chargen zählt am Platz nicht mit.
     *
     * @param  numeric-string  $qty
     * @param  array<int, numeric-string>  $physical  bin_id|0 → physischer Saldo
     * @param  array<int, array<int, array{0: StockLot, 1: Decimal}>>  $lotStock
     * @return list<array{0: WarehouseBin|null, 1: numeric-string}>
     */
    private function allocateBins(string $qty, ?WarehouseBin $bin, array $physical, array $lotStock): array {
        if ($bin !== null) {
            return [[$bin, $qty]];
        }

        $binIds = array_values(array_filter(array_keys($physical), fn (int $id): bool => $id > 0));
        if ($binIds === []) {
            return [[null, $qty]];
        }

        $bins = WarehouseBin::query()->whereIn('id', $binIds)->orderBy('sort_order')->orderBy('code')->get();
        $remaining = $qty;
        $parts = [];
        foreach ($bins as $candidate) {
            [$pickable, $blocked] = $this->lotStock->pickable($lotStock, (int) $candidate->id);
            $onHand = Decimal::of($physical[(int) $candidate->id] ?? '0', InventoryLedger::SCALE);
            if ($blocked->isPositive()) {
                // Altbestand: frühere Abgänge trugen keine Charge, der gebuchte Saldo einer gesperrten Charge kann über
                // dem liegen, was von ihr noch da ist. Was aktiven Chargen gebucht ist, bleibt deshalb entnehmbar.
                $onHand = Decimal::min($onHand, Decimal::max($onHand->minus($blocked), Decimal::sum(array_column($pickable, 1), InventoryLedger::SCALE)));
            }
            $stock = $onHand->getValue();
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
     * Verteilt die Platzmenge über die aktiven Chargen nach FEFO; ohne
     * Chargenbestand im Lager eine Zeile ohne Charge. Verfügbar je Chargenzeile
     * = physischer Chargenbestand am Ort; der Rest ohne Charge hat nur, was am
     * Ort weder einer aktiven noch einer gesperrten Charge gehört.
     *
     * @param  numeric-string  $qty
     * @param  array<int, numeric-string>  $physical  bin_id|0 → physischer Saldo
     * @param  array<int, array<int, array{0: StockLot, 1: Decimal}>>  $lotStock
     * @return list<array{0: StockLot|null, 1: numeric-string, 2: numeric-string|null}>
     */
    private function allocateLots(?WarehouseBin $bin, string $qty, array $physical, array $lotStock): array {
        if ($lotStock === []) {
            return [[null, $qty, null]];
        }

        $binKey = (int) ($bin->id ?? 0);
        [$lots, $blocked] = $this->lotStock->pickable($lotStock, $binKey);

        $remaining = $qty;
        $parts = [];
        foreach ($lots as [$lot, $balance]) {
            $stock = $balance->getValue();
            $take = bccomp($stock, $remaining, InventoryLedger::SCALE) < 0 ? $stock : $remaining;
            $parts[] = [$lot, $take, $stock];
            $remaining = bcsub($remaining, $take, InventoryLedger::SCALE);
            if (bccomp($remaining, '0', InventoryLedger::SCALE) <= 0) {
                break;
            }
        }
        if (bccomp($remaining, '0', InventoryLedger::SCALE) > 0) {
            $unlotted = Decimal::of($physical[$binKey] ?? '0', InventoryLedger::SCALE)
                ->minus(Decimal::sum(array_column($lots, 1), InventoryLedger::SCALE))
                ->minus($blocked);
            $parts[] = [null, $remaining, Decimal::max($unlotted, Decimal::zero(InventoryLedger::SCALE))->getValue()];
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
