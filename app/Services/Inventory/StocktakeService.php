<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StocktakeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{StockCountStatus, StockCountType};
use App\Models\{ArticleVariant, StockCount, StockCountLine, StockMovement, Warehouse};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stichtagsbezogene Inventur (Feature 048, MVP-069): friert den Sollbestand je
 * Bucket (Variante, Bestandszustand, Eigentumsart) zum Zählzeitpunkt ein,
 * nimmt Zählmengen auf und bucht freigegebene Differenzen als eigene,
 * auditierte Korrekturbuchungen (Gegenbuchung) über den {@see InventoryLedger}.
 * Bewegungen nach dem Zählzeitpunkt laufen separat weiter. Neben der
 * Vollzählung ({@see open()}) gibt es die zyklische Zählung einer Teilmenge
 * ({@see openCycle()}, E6) und die Scan-gestützte Erfassung ({@see recordByScan()}).
 */
class StocktakeService {
    public const SCALE = 4;

    public function __construct(
        private readonly InventoryLedger $ledger,
        private readonly BarcodeResolver $resolver,
    ) {}

    /** Eröffnet eine Vollinventur und friert den Sollbestand aller belegten Buckets ein. */
    public function open(Warehouse $warehouse, ?int $createdBy = null): StockCount {
        return DB::transaction(function () use ($warehouse, $createdBy): StockCount {
            $count = $this->createCount($warehouse, StockCountType::Full, $createdBy);
            $this->freezeBuckets($count, $warehouse, null);

            return $count->load('lines');
        });
    }

    /**
     * Eröffnet eine zyklische Inventur über eine Teilmenge (Stichprobe/ABC-Zyklus):
     * friert nur die Buckets der angegebenen Varianten ein (E6).
     *
     * @param list<int> $variantIds
     */
    public function openCycle(Warehouse $warehouse, array $variantIds, ?int $createdBy = null): StockCount {
        return DB::transaction(function () use ($warehouse, $variantIds, $createdBy): StockCount {
            $count = $this->createCount($warehouse, StockCountType::Cycle, $createdBy);
            $this->freezeBuckets($count, $warehouse, array_map('intval', $variantIds));

            return $count->load('lines');
        });
    }

    /** Erfasst eine Zählmenge per Scan: löst den Code zur Variante auf und trifft die passende Zeile. */
    public function recordByScan(StockCount $count, string $code, string $countedQty, ?int $countedBy = null): StockCountLine {
        $variant = $this->resolver->resolve($code)->variant;
        if (! $variant instanceof ArticleVariant) {
            throw new RuntimeException('Unbekannter oder nicht bestandsführender Code: ' . trim($code));
        }

        $line = $count->lines()
            ->where('article_variant_id', $variant->id)
            ->orderByRaw("stock_state = 'physical' desc")
            ->first();
        if (! $line instanceof StockCountLine) {
            throw new RuntimeException('Variante ist nicht Teil dieser Inventur.');
        }

        return $this->recordCount($line, $countedQty, $countedBy);
    }

    private function createCount(Warehouse $warehouse, StockCountType $type, ?int $createdBy): StockCount {
        /** @var StockCount $count */
        $count = StockCount::query()->create([
            'organization_id' => $warehouse->organization_id,
            'warehouse_id' => $warehouse->id,
            'status' => StockCountStatus::Counting->value,
            'count_type' => $type->value,
            'counted_at' => Carbon::now(),
            'created_by' => $createdBy,
        ]);

        return $count;
    }

    /**
     * Friert die Soll-Buckets eines Lagers ein – optional auf eine Variantenmenge
     * beschränkt (zyklische Inventur).
     *
     * @param list<int>|null $variantIds
     */
    private function freezeBuckets(StockCount $count, Warehouse $warehouse, ?array $variantIds): void {
        $query = StockMovement::query()
            ->where('warehouse_id', $warehouse->id)
            ->select('article_variant_id', 'stock_state', 'ownership_type')
            ->distinct();
        if ($variantIds !== null) {
            $query->whereIn('article_variant_id', $variantIds);
        }
        $buckets = $query->get();

        $variants = ArticleVariant::query()
            ->whereIn('id', $buckets->pluck('article_variant_id')->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($buckets as $bucket) {
            /** @var ArticleVariant|null $variant */
            $variant = $variants->get($bucket->article_variant_id);
            if ($variant === null) {
                continue;
            }

            $count->lines()->create([
                'article_variant_id' => $variant->id,
                'stock_state' => $bucket->stock_state->value,
                'ownership_type' => $bucket->ownership_type->value,
                'book_qty' => $this->ledger->balance($variant, $warehouse, $bucket->stock_state, $bucket->ownership_type),
            ]);
        }
    }

    /** Erfasst die gezählte Menge einer Zeile. */
    public function recordCount(StockCountLine $line, string $countedQty, ?int $countedBy = null): StockCountLine {
        $line->counted_qty = NumberHelper::normalizeDecimalString($countedQty);
        $line->counted_by = $countedBy;
        $line->save();

        return $line;
    }

    /**
     * Gibt die geprüften Differenzen frei und bucht sie als Korrekturen. Jede
     * Differenz (counted − book) wird gegen den eingefrorenen Sollbestand als
     * eigene Korrekturbuchung gebucht.
     */
    public function applyDifferences(StockCount $count, ?int $reviewedBy = null): StockCount {
        $warehouse = $count->warehouse;
        if ($warehouse === null) {
            throw new RuntimeException('Inventur ohne Lagerort.');
        }

        return DB::transaction(function () use ($count, $warehouse, $reviewedBy): StockCount {
            foreach ($count->lines()->with('variant')->get() as $line) {
                if ($line->counted_qty === null || $line->applied) {
                    continue;
                }
                $variant = $line->variant;
                $difference = $line->difference();
                if ($variant !== null && $difference !== null && bccomp($difference, '0', self::SCALE) !== 0) {
                    $this->ledger->correction($variant, $warehouse, $line->stock_state, $difference, $line->ownership_type);
                }
                $line->applied = true;
                $line->save();
            }

            $count->status = StockCountStatus::Completed;
            $count->reviewed_by = $reviewedBy;
            $count->completed_at = Carbon::now();
            $count->save();

            return $count;
        });
    }
}
