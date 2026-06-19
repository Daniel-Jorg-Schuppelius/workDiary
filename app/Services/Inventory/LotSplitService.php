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

use App\Models\{StockLot, StockValuationLayer};
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Los-Split und -Merge (Feature 047/048, E7). Verschiebt Bestand zwischen Chargen
 * über die FIFO/FEFO-Bewertungsschichten und erhält dabei die Einzelkosten je
 * Schicht (Split entnimmt älteste Schicht zuerst). Merge führt zwei Chargen
 * derselben Variante zusammen; die ausgeräumte Charge wird als „merged" markiert.
 */
class LotSplitService {
    public const SCALE = 4;

    /** Teilt `qty` aus einer Charge in eine neue Charge ab. */
    public function split(StockLot $source, string $qty, string $newLotNo, ?string $bestBefore = null): StockLot {
        $qty = $this->positive($qty);
        $newLotNo = trim($newLotNo);
        if ($newLotNo === '') {
            throw new RuntimeException('Leere Ziel-Chargennummer.');
        }
        if (bccomp($qty, $this->onHand($source), self::SCALE) > 0) {
            throw new RuntimeException('Split übersteigt den Chargenbestand.');
        }

        return DB::transaction(function () use ($source, $qty, $newLotNo, $bestBefore): StockLot {
            /** @var StockLot $target */
            $target = StockLot::query()->create([
                'organization_id' => $source->organization_id,
                'article_variant_id' => $source->article_variant_id,
                'lot_no' => $newLotNo,
                'mfg_date' => $source->mfg_date,
                'best_before' => $bestBefore ?? $source->best_before?->format('Y-m-d'),
                'status' => StockLot::STATUS_ACTIVE,
            ]);

            $remaining = $qty;
            $layers = StockValuationLayer::query()
                ->where('stock_lot_id', $source->id)
                ->where('qty_remaining', '>', 0)
                ->orderBy('acquired_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($layers as $layer) {
                if (bccomp($remaining, '0', self::SCALE) <= 0) {
                    break;
                }
                $take = bccomp($layer->qty_remaining, $remaining, self::SCALE) <= 0 ? $layer->qty_remaining : $remaining;
                $layer->qty_remaining = bcsub($layer->qty_remaining, $take, self::SCALE);
                $layer->save();

                StockValuationLayer::query()->create([
                    'organization_id' => $layer->organization_id,
                    'article_variant_id' => $layer->article_variant_id,
                    'warehouse_id' => $layer->warehouse_id,
                    'stock_lot_id' => $target->id,
                    'qty_remaining' => $take,
                    'unit_cost' => $layer->unit_cost,
                    'currency' => $layer->currency,
                    'source_movement_id' => $layer->source_movement_id,
                    'acquired_at' => $layer->acquired_at,
                    'best_before' => $target->best_before?->format('Y-m-d'),
                ]);

                $remaining = bcsub($remaining, $take, self::SCALE);
            }

            return $target;
        });
    }

    /** Führt die Quell-Charge vollständig in die Ziel-Charge zusammen. */
    public function merge(StockLot $from, StockLot $into): StockLot {
        if ($from->id === $into->id) {
            throw new RuntimeException('Charge kann nicht mit sich selbst zusammengeführt werden.');
        }
        if ((int) $from->article_variant_id !== (int) $into->article_variant_id) {
            throw new RuntimeException('Nur Chargen derselben Variante können zusammengeführt werden.');
        }

        return DB::transaction(function () use ($from, $into): StockLot {
            StockValuationLayer::query()->where('stock_lot_id', $from->id)->update(['stock_lot_id' => $into->id]);
            $from->forceFill(['status' => StockLot::STATUS_MERGED])->save();

            return $into;
        });
    }

    /** @return numeric-string */
    private function onHand(StockLot $lot): string {
        $sum = (string) StockValuationLayer::query()->where('stock_lot_id', $lot->id)->sum('qty_remaining');

        return bcadd($sum, '0', self::SCALE);
    }

    /** @return numeric-string */
    private function positive(string $value): string {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bccomp($value, '0', self::SCALE) < 0 ? bcmul($value, '-1', self::SCALE) : $value;
    }
}
