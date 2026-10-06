<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_10_100900_post_lot_block_balances.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Chargensperre als Buchung (Feature 048, E5/E8.1): Bisher war „gesperrt“ nur
 * der Status der Charge, ihr Bestand zählte überall als verfügbar. Für jede
 * gesperrte Charge wird der positive physische Saldo je Topf (Lager, Platz,
 * Eigentum) als Bewegung `lot_block` in den Zustand „gesperrt“ nachgetragen —
 * wie es `LotService::block()` seither tut, in dieselbe Charge
 * zusammengeführte Altchargen eingeschlossen. Akteur leer; eine Charge, die
 * schon eine Sperrbuchung hat, bleibt unberührt (zweiter Lauf bucht nichts).
 */
return new class extends Migration {
    public function up(): void {
        $lots = DB::table('stock_lots')->where('status', 'blocked')->orderBy('id')->get(['id', 'organization_id', 'article_variant_id']);
        $now = Carbon::now();

        foreach ($lots as $lot) {
            $held = DB::table('stock_movements')->where('stock_lot_id', $lot->id)->where('movement_type', 'lot_block')->exists();
            if ($held) {
                continue;
            }

            $n = 0;
            foreach ($this->positivePots($this->withMergedSources((int) $lot->id)) as $pot) {
                $n++;
                DB::table('stock_movements')->insert([
                    'organization_id' => $lot->organization_id,
                    'article_variant_id' => $lot->article_variant_id,
                    'warehouse_id' => $pot['warehouse_id'],
                    'bin_id' => $pot['bin_id'],
                    'stock_lot_id' => $lot->id,
                    'stock_state' => 'blocked',
                    'ownership_type' => $pot['ownership_type'],
                    'owner_ref' => $pot['owner_ref'],
                    'movement_type' => 'lot_block',
                    'qty_base' => $pot['qty']->getValue(),
                    'occurred_at' => $now,
                    'actor_user_id' => null,
                    // Morph-Alias von StockLot, eingefroren.
                    'source_type' => 'stock_lots',
                    'source_id' => $lot->id,
                    'idempotency_key' => 'lot-block:' . $lot->id . ':' . $n,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /** Datenmigration im append-only Lagerbuch: die Sperrbuchungen bleiben stehen. */
    public function down(): void {}

    /**
     * Die Charge und alle transitiv in sie zusammengeführten Altchargen.
     *
     * @return list<int>
     */
    private function withMergedSources(int $lotId): array {
        $ids = [$lotId];
        $frontier = [$lotId];
        while ($frontier !== []) {
            $frontier = DB::table('stock_lots')
                ->where('status', 'merged')
                ->whereIn('merged_into_lot_id', $frontier)
                ->whereNotIn('id', $ids)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    /**
     * Positive physische Salden je Topf, aufsteigend nach Lager, Platz (ohne
     * Platz zuerst), Eigentumsart und Eigentümer.
     *
     * @param  list<int>  $lotIds
     * @return list<array{warehouse_id: int, bin_id: int|null, ownership_type: string, owner_ref: string|null, qty: Decimal}>
     */
    private function positivePots(array $lotIds): array {
        $pots = [];
        $rows = DB::table('stock_movements')
            ->whereIn('stock_lot_id', $lotIds)
            ->where('stock_state', 'physical')
            ->get(['warehouse_id', 'bin_id', 'ownership_type', 'owner_ref', 'qty_base']);
        foreach ($rows as $row) {
            $sort = [(int) $row->warehouse_id, (int) ($row->bin_id ?? 0), (string) $row->ownership_type, (string) ($row->owner_ref ?? '')];
            $key = implode("\0", $sort);
            $qty = Decimal::of((string) $row->qty_base, 4);
            $pots[$key] ??= [
                'sort' => $sort,
                'warehouse_id' => (int) $row->warehouse_id,
                'bin_id' => $row->bin_id !== null ? (int) $row->bin_id : null,
                'ownership_type' => (string) $row->ownership_type,
                'owner_ref' => $row->owner_ref !== null ? (string) $row->owner_ref : null,
                'qty' => Decimal::zero(4),
            ];
            $pots[$key]['qty'] = $pots[$key]['qty']->plus($qty);
        }

        $pots = array_values(array_filter($pots, static fn (array $pot): bool => $pot['qty']->isPositive()));
        usort($pots, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_map(static function (array $pot): array {
            unset($pot['sort']);

            return $pot;
        }, $pots);
    }
};
