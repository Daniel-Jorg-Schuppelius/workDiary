<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotStockReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{OwnershipType, StockLotStatus, StockMovementType, StockState};
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockMovement, Warehouse, WarehouseBin};
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Database\Eloquent\{Builder, Collection};
use RuntimeException;

/**
 * Chargenbestand aus dem Lagerbuch (Feature 048, E2): Saldo der physischen
 * Bewegungen mit Charge, geführt bei der Charge, der der Bestand heute gehört —
 * eine zusammengeführte Charge zählt bei ihrer Zielcharge. Grundlage für
 * Pickzettel, Chargenliste und die Zuteilung von Abgängen. Die
 * Bewertungsschichten spielen hier keine Rolle.
 */
final class LotStockReader {
    /**
     * Chargensalden der Variante im Lager je Platz (0 = ohne Platz); mit Platz
     * nur dessen Bewegungen. Ist die Zielcharge einer zusammengeführten
     * Altcharge unbekannt, zählt ihr Bestand bei keiner Charge.
     *
     * @return array<int, array<int, array{0: StockLot, 1: Decimal}>> bin_id|0 → Charge → [Charge, Saldo]
     */
    public function balances(ArticleVariant $variant, Warehouse $warehouse, ?WarehouseBin $bin = null, bool $forUpdate = false, ?OwnershipType $ownership = null): array {
        $query = StockMovement::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('stock_state', StockState::Physical->value)
            ->whereNotNull('stock_lot_id')
            ->when($bin !== null, fn ($q) => $q->where('bin_id', $bin?->id))
            ->when($ownership !== null, fn ($q) => $q->where('ownership_type', $ownership?->value));
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $rows = $query->toBase()->get(['bin_id', 'stock_lot_id', 'qty_base']);
        if ($rows->isEmpty()) {
            return [];
        }

        $lots = StockLot::query()->whereIn('id', $rows->pluck('stock_lot_id')->unique()->all())->get()->keyBy('id');
        $stock = [];
        foreach ($rows as $row) {
            $lot = $this->currentLot($lots, (int) $row->stock_lot_id);
            if ($lot === null) {
                continue;
            }
            $binKey = (int) ($row->bin_id ?? 0);
            $qty = Decimal::of((string) $row->qty_base, InventoryLedger::SCALE);
            $entry = $stock[$binKey][(int) $lot->id] ?? null;
            $stock[$binKey][(int) $lot->id] = [$lot, $entry !== null ? $entry[1]->plus($qty) : $qty];
        }

        return $stock;
    }

    /**
     * Buchsaldo einer Charge über alle Lager bzw. in einem Lager.
     *
     * @return numeric-string
     */
    public function balanceOf(StockLot $lot, ?Warehouse $warehouse = null): string {
        return $this->balancesOf([$lot], $warehouse)[$lot->id];
    }

    /**
     * Buchsalden mehrerer Chargen in einem Durchlauf (Chargenliste). In eine
     * Charge zusammengeführte Altchargen zählen bei ihr; eine zusammengeführte
     * Charge selbst hat keinen Bestand mehr.
     *
     * @param  iterable<StockLot>  $lots
     * @return array<int, numeric-string> Charge → Saldo
     */
    public function balancesOf(iterable $lots, ?Warehouse $warehouse = null): array {
        $sums = [];
        $current = [];
        foreach ($lots as $lot) {
            $sums[$lot->id] = Decimal::zero(InventoryLedger::SCALE);
            if ($lot->status !== StockLotStatus::Merged) {
                $current[] = $lot->id;
            }
        }

        $owner = $this->withMergedSources($current);
        if ($owner !== []) {
            $rows = StockMovement::query()
                ->whereIn('stock_lot_id', array_keys($owner))
                ->where('stock_state', StockState::Physical->value)
                ->when($warehouse !== null, fn ($q) => $q->where('warehouse_id', $warehouse?->id))
                ->toBase()
                ->get(['stock_lot_id', 'qty_base']);
            foreach ($rows as $row) {
                $lotId = $owner[(int) $row->stock_lot_id];
                $sums[$lotId] = $sums[$lotId]->plus(Decimal::of((string) $row->qty_base, InventoryLedger::SCALE));
            }
        }

        return array_map(static fn (Decimal $sum): string => $sum->getValue(), $sums);
    }

    /**
     * Physischer Saldo einer Charge je Topf (Lager, Platz, Eigentum) in fester
     * Folge — Lager, Platz (ohne Platz zuerst), Eigentumsart, Eigentümer
     * aufsteigend; in sie zusammengeführte Altchargen zählen mit. Ihre Summe
     * ist der Buchsaldo ({@see balanceOf()}). `$forUpdate` sperrt die
     * Bewegungen — erst nach der Chargenzeile, wie beim Zusammenführen.
     *
     * @return list<array{warehouse: Warehouse, bin: WarehouseBin|null, ownership: OwnershipType, ownerRef: string|null, qty: Decimal}>
     */
    public function pots(StockLot $lot, bool $forUpdate = false): array {
        if ($lot->status === StockLotStatus::Merged) {
            return [];
        }

        return $this->sumPots(
            StockMovement::query()
                ->whereIn('stock_lot_id', array_keys($this->withMergedSources([(int) $lot->id])))
                ->where('stock_state', StockState::Physical->value),
            $forUpdate,
        );
    }

    /**
     * Gesperrter Saldo einer Charge je Topf, Folge wie {@see pots()}: nur ihre
     * Sperrbuchungen, nicht Quarantäne-Zugänge im Zustand „gesperrt“.
     *
     * @return list<array{warehouse: Warehouse, bin: WarehouseBin|null, ownership: OwnershipType, ownerRef: string|null, qty: Decimal}>
     */
    public function holds(StockLot $lot, bool $forUpdate = false): array {
        return $this->sumPots(
            StockMovement::query()
                ->where('stock_lot_id', $lot->id)
                ->where('stock_state', StockState::Blocked->value)
                ->whereIn('movement_type', [StockMovementType::LotBlock->value, StockMovementType::LotRelease->value]),
            $forUpdate,
        );
    }

    /**
     * Aktive Chargen mit positivem Saldo in einem Lager, je Variante in
     * FEFO-Folge — Auswahl der manuellen Entnahme.
     *
     * @return list<array{0: StockLot, 1: Decimal}>
     */
    public function issuableIn(Warehouse $warehouse): array {
        $rows = StockMovement::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('stock_state', StockState::Physical->value)
            ->whereNotNull('stock_lot_id')
            ->toBase()
            ->get(['stock_lot_id', 'qty_base']);
        if ($rows->isEmpty()) {
            return [];
        }

        $lots = StockLot::query()->whereIn('id', $rows->pluck('stock_lot_id')->unique()->all())->get()->keyBy('id');
        $sums = [];
        foreach ($rows as $row) {
            $lot = $this->currentLot($lots, (int) $row->stock_lot_id);
            if ($lot === null) {
                continue;
            }
            $qty = Decimal::of((string) $row->qty_base, InventoryLedger::SCALE);
            $sums[(int) $lot->id] = [$lot, isset($sums[(int) $lot->id]) ? $sums[(int) $lot->id][1]->plus($qty) : $qty];
        }

        $issuable = array_values(array_filter($sums, static fn (array $entry): bool => $entry[0]->status === StockLotStatus::Active && $entry[1]->isPositive()));
        usort($issuable, static fn (array $a, array $b): int => [(int) $a[0]->article_variant_id, self::fefoKey($a[0])] <=> [(int) $b[0]->article_variant_id, self::fefoKey($b[0])]);

        return $issuable;
    }

    /** Die Charge, der der Bestand einer (womöglich zusammengeführten) Charge heute gehört. */
    public function currentOf(StockLot $lot): ?StockLot {
        return $this->currentLot(new Collection([(int) $lot->id => $lot]), (int) $lot->id);
    }

    /**
     * Entnehmbare Chargen an einem Platz in FEFO-Folge — MHD aufsteigend, ohne
     * MHD zuletzt, dann Chargennummer — und die Summe gesperrter Chargen.
     * Chargen ohne positiven Saldo fehlen.
     *
     * @param  array<int, array<int, array{0: StockLot, 1: Decimal}>>  $balances  aus {@see balances()}
     * @return array{0: list<array{0: StockLot, 1: Decimal}>, 1: Decimal}
     */
    public function pickable(array $balances, int $binKey): array {
        $pickable = [];
        $blocked = Decimal::zero(InventoryLedger::SCALE);
        foreach ($balances[$binKey] ?? [] as [$lot, $balance]) {
            if (! $balance->isPositive()) {
                continue;
            }
            if ($lot->status === StockLotStatus::Blocked) {
                $blocked = $blocked->plus($balance);
            } else {
                $pickable[] = [$lot, $balance];
            }
        }
        usort($pickable, static fn (array $a, array $b): int => self::fefoKey($a[0]) <=> self::fefoKey($b[0]));

        return [$pickable, $blocked];
    }

    /**
     * Teilt einen Abgang Chargen zu (E2). Eine gewählte Charge trägt die ganze
     * Menge; sonst FEFO über die entnehmbaren Chargen wie auf dem Pickzettel,
     * der Rest ohne Charge. Ohne Platz zählt der Chargenbestand des ganzen
     * Lagers, denn auch die Bestandsprüfung des Abgangs gilt dann dem Lager.
     * Liest gesperrt: nur innerhalb der Abgangs-Transaktion aufrufen.
     *
     * @param  numeric-string  $qty  Abgangsmenge in Basiseinheit
     */
    public function allocate(ArticleVariant $variant, Warehouse $warehouse, string $qty, ?WarehouseBin $bin = null, ?StockLot $explicit = null, bool $allowNegative = false, OwnershipType $ownership = OwnershipType::Own): LotAllocation {
        $wanted = Decimal::of($qty, InventoryLedger::SCALE);
        $balances = $this->balances($variant, $warehouse, $bin, forUpdate: true, ownership: $ownership);
        $binKey = (int) ($bin->id ?? 0);
        if ($bin === null) {
            $balances = [0 => $this->acrossPlaces($balances)];
        }

        if ($explicit !== null) {
            $lot = $this->issuableLot($variant, $explicit);
            $onHand = $balances[$binKey][(int) $lot->id][1] ?? Decimal::zero(InventoryLedger::SCALE);
            if (! $allowNegative && $onHand->lessThan($wanted)) {
                throw new RuntimeException((string) __('inventory.lot.error.insufficient', ['lot' => $lot->lot_no]));
            }

            return new LotAllocation([['lot' => $lot, 'qty' => $wanted->getValue()]]);
        }

        $parts = [];
        $remaining = $wanted;
        foreach ($this->pickable($balances, $binKey)[0] as [$lot, $balance]) {
            if (! $remaining->isPositive()) {
                break;
            }
            $take = Decimal::min($balance, $remaining);
            $parts[] = ['lot' => $lot, 'qty' => $take->getValue()];
            $remaining = $remaining->minus($take);
        }
        if ($parts === [] || $remaining->isPositive()) {
            $parts[] = ['lot' => null, 'qty' => $remaining->getValue()];
        }

        return new LotAllocation($parts);
    }

    /**
     * Gewählte Charge frisch lesen: sie muss zur Variante gehören und aktiv sein. Ohne Zeilensperre —
     * Zusammenführen sperrt erst Chargen, dann Bewegungen; hier ist die Folge umgekehrt (Deadlock).
     */
    private function issuableLot(ArticleVariant $variant, StockLot $explicit): StockLot {
        $lot = StockLot::query()->find($explicit->id);
        if (! $lot instanceof StockLot
            || (int) $lot->article_variant_id !== (int) $variant->id
            || (int) $lot->organization_id !== (int) $variant->organization_id) {
            throw new RuntimeException((string) __('inventory.lot.error.foreign', ['lot' => $explicit->lot_no]));
        }
        if ($lot->status !== StockLotStatus::Active) {
            throw new RuntimeException((string) __('inventory.lot.error.not_active', ['lot' => $lot->lot_no]));
        }

        return $lot;
    }

    /**
     * Die Chargen und alle transitiv in sie zusammengeführten Altchargen →
     * Charge, bei der ihr Bestand zählt.
     *
     * @param  list<int>  $lotIds  nicht zusammengeführte Chargen
     * @return array<int, int>
     */
    private function withMergedSources(array $lotIds): array {
        $owner = array_combine($lotIds, $lotIds);
        $frontier = $lotIds;
        while ($frontier !== []) {
            $merged = StockLot::query()
                ->where('status', StockLotStatus::Merged->value)
                ->whereIn('merged_into_lot_id', $frontier)
                ->whereNotIn('id', array_keys($owner))
                ->toBase()
                ->get(['id', 'merged_into_lot_id']);
            $frontier = [];
            foreach ($merged as $row) {
                $owner[(int) $row->id] = $owner[(int) $row->merged_into_lot_id];
                $frontier[] = (int) $row->id;
            }
        }

        return $owner;
    }

    /**
     * Summiert Bewegungen je Topf; Töpfe mit Saldo null fallen weg.
     *
     * @param  Builder<StockMovement>  $query
     * @return list<array{warehouse: Warehouse, bin: WarehouseBin|null, ownership: OwnershipType, ownerRef: string|null, qty: Decimal}>
     */
    private function sumPots(Builder $query, bool $forUpdate): array {
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $sums = [];
        foreach ($query->toBase()->get(['warehouse_id', 'bin_id', 'ownership_type', 'owner_ref', 'qty_base']) as $row) {
            $pot = [(int) $row->warehouse_id, (int) ($row->bin_id ?? 0), (string) $row->ownership_type, (string) ($row->owner_ref ?? '')];
            $key = implode("\0", $pot);
            $qty = Decimal::of((string) $row->qty_base, InventoryLedger::SCALE);
            $sums[$key] = ['pot' => $pot, 'ownerRef' => $row->owner_ref !== null ? (string) $row->owner_ref : null, 'qty' => isset($sums[$key]) ? $sums[$key]['qty']->plus($qty) : $qty];
        }
        $sums = array_values(array_filter($sums, static fn (array $sum): bool => ! $sum['qty']->isZero()));
        if ($sums === []) {
            return [];
        }
        usort($sums, static fn (array $a, array $b): int => $a['pot'] <=> $b['pot']);

        $warehouses = Warehouse::query()->whereIn('id', array_unique(array_map(static fn (array $sum): int => $sum['pot'][0], $sums)))->get()->keyBy('id');
        $bins = WarehouseBin::query()->whereIn('id', array_unique(array_map(static fn (array $sum): int => $sum['pot'][1], $sums)))->get()->keyBy('id');

        return array_map(static fn (array $sum): array => [
            'warehouse' => $warehouses->get($sum['pot'][0]) ?? throw new RuntimeException('Lagerort #' . $sum['pot'][0] . ' fehlt.'),
            'bin' => $sum['pot'][1] > 0 ? ($bins->get($sum['pot'][1]) ?? throw new RuntimeException('Lagerplatz #' . $sum['pot'][1] . ' fehlt.')) : null,
            'ownership' => OwnershipType::from($sum['pot'][2]),
            'ownerRef' => $sum['ownerRef'],
            'qty' => $sum['qty'],
        ], $sums);
    }

    /**
     * Chargensalden über alle Plätze summiert.
     *
     * @param  array<int, array<int, array{0: StockLot, 1: Decimal}>>  $balances
     * @return array<int, array{0: StockLot, 1: Decimal}>
     */
    private function acrossPlaces(array $balances): array {
        $total = [];
        foreach ($balances as $lots) {
            foreach ($lots as $lotId => [$lot, $balance]) {
                $total[$lotId] = [$lot, isset($total[$lotId]) ? $total[$lotId][1]->plus($balance) : $balance];
            }
        }

        return $total;
    }

    /**
     * Folgt der Kette der Zusammenführungen bis zur heutigen Charge.
     *
     * @param  Collection<int, StockLot>  $lots  nach id; Zielchargen werden nachgeladen
     */
    private function currentLot(Collection $lots, int $lotId): ?StockLot {
        $lot = $lots->get($lotId);
        $seen = [];
        while ($lot instanceof StockLot && $lot->status === StockLotStatus::Merged) {
            $targetId = $lot->merged_into_lot_id;
            if ($targetId === null || isset($seen[$targetId])) {
                return null;
            }
            $seen[$targetId] = true;
            if (! $lots->has($targetId)) {
                $target = StockLot::query()->find($targetId);
                if (! $target instanceof StockLot) {
                    return null;
                }
                $lots->put($targetId, $target);
            }
            $lot = $lots->get($targetId);
        }

        return $lot;
    }

    /** @return array{0: int, 1: string, 2: string} */
    private static function fefoKey(StockLot $lot): array {
        return [$lot->best_before === null ? 1 : 0, $lot->best_before?->toDateString() ?? '', $lot->lot_no];
    }
}
