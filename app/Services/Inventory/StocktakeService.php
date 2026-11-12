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
use App\Models\Article\ArticleVariant;
use App\Models\Audit\AuditLog;
use App\Models\Inventory\{StockCount, StockCountLine, StockMovement, Warehouse};
use App\Models\Platform\Organization;
use App\Services\Concerns\AssertsStatusTransition;
use App\Settings\SettingsRegistry;
use App\Support\{DecimalQty, MorphMap};
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
    use AssertsStatusTransition;

    public const int SCALE = 4;

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

    /**
     * Erfasst eine Zählmenge per Scan: löst den Code zur Variante auf und trifft
     * die passende Zeile. `$unit` ist eine Einheit des Artikels (leer =
     * Basiseinheit); gespeichert wird die Menge in Basiseinheit (MVP-1584).
     */
    public function recordByScan(StockCount $count, string $code, string $countedQty, ?int $countedBy = null, ?string $unit = null): StockCountLine {
        $line = $this->lineForCode($count, $code);

        return $this->recordCount($line, $this->inBase($line, $countedQty, $unit), $countedBy);
    }

    /**
     * Mobile Zählung (MVP-898): jeder Scan addiert zur bisher gezählten Menge
     * der Zeile. Die Zeilensperre hält parallele Scanner auseinander.
     */
    public function addByScan(StockCount $count, string $code, string $qty = '1', ?int $countedBy = null, ?string $unit = null): StockCountLine {
        if (! $count->status->isCounting()) {
            throw new RuntimeException((string) __('inventory.count_ui.closed'));
        }
        if (bccomp(NumberHelper::normalizeDecimalString($qty), '0', self::SCALE) <= 0) {
            throw new RuntimeException((string) __('inventory.count_ui.qty_positive'));
        }

        return DB::transaction(function () use ($count, $code, $qty, $unit, $countedBy): StockCountLine {
            /** @var StockCountLine $line */
            $line = StockCountLine::query()->whereKey($this->lineForCode($count, $code)->id)->lockForUpdate()->firstOrFail();

            return $this->recordCount($line, bcadd($line->counted_qty ?? '0', $this->inBase($line, $qty, $unit), self::SCALE), $countedBy);
        });
    }

    /**
     * Zählmenge einer Zeile in Basiseinheit; ohne Einheit bleibt sie, wie sie ist.
     *
     * @return numeric-string
     */
    public function inBase(StockCountLine $line, string $qty, ?string $unit): string {
        $variant = $line->variant;
        if (trim((string) $unit) === '' || ! $variant instanceof ArticleVariant) {
            return NumberHelper::normalizeDecimalString($qty);
        }

        return EnteredQuantity::of($variant, $qty, $unit)->base;
    }

    private function lineForCode(StockCount $count, string $code): StockCountLine {
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

        return $line;
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

    /**
     * Zählung abschließen (→ Prüfung), zur Korrektur wieder öffnen (→ Zählung)
     * oder verwerfen (→ abgebrochen, ohne Buchung) — MVP-1620.
     */
    public function transition(StockCount $count, StockCountStatus $target, ?int $actorId): StockCount {
        return DB::transaction(function () use ($count, $target, $actorId): StockCount {
            /** @var StockCount $locked */
            $locked = StockCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            $this->assertStatusTransition($locked->status, $target);
            if ($target === StockCountStatus::Review && ! $locked->lines()->whereNotNull('counted_qty')->exists()) {
                throw new RuntimeException((string) __('inventory.count_ui.nothing_counted'));
            }
            $from = $locked->status;
            $locked->status = $target;
            $locked->save();
            AuditLog::query()->create([
                'organization_id' => $locked->organization_id,
                'user_id' => $actorId,
                'event' => 'inventory.countStatusChanged',
                'auditable_type' => MorphMap::stableKey(StockCount::class),
                'auditable_id' => $locked->id,
                'changes' => ['from' => $from->value, 'to' => $target->value],
            ]);

            return $locked;
        });
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

        $fourEyes = $this->assertFourEyes($count, $warehouse, $reviewedBy);

        return DB::transaction(function () use ($count, $warehouse, $reviewedBy, $fourEyes): StockCount {
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

            // Grenzwert und Freigebende gehören ins Audit (Feature 048, Berechtigungen und Freigaben).
            AuditLog::query()->create([
                'organization_id' => $count->organization_id,
                'user_id' => $reviewedBy,
                'event' => 'inventory.countApproved',
                'auditable_type' => MorphMap::stableKey(StockCount::class),
                'auditable_id' => $count->id,
                'changes' => ['approved_by' => $reviewedBy] + $fourEyes,
            ]);

            return $count;
        });
    }

    /**
     * Vier-Augen-Freigabe (MVP-1585): ist sie für die Organisation eingeschaltet,
     * gibt Differenzen ab dem Grenzwert nicht frei, wer sie gezählt hat. Der
     * Grenzwert misst den Bestandswert der Differenz je Zeile.
     *
     * @return array{four_eyes: bool, threshold: string, flagged_lines: int, counted_by: list<int>}
     */
    private function assertFourEyes(StockCount $count, Warehouse $warehouse, ?int $reviewedBy): array {
        $organization = $count->organization;
        $registry = app(SettingsRegistry::class);
        $enabled = (bool) $registry->effective('inventory.four_eyes.count', $organization)->value;
        $threshold = NumberHelper::normalizeDecimalString((string) ($registry->effective('inventory.four_eyes.count_threshold', $organization)->value ?? '0'));
        $state = ['four_eyes' => $enabled, 'threshold' => $threshold, 'flagged_lines' => 0, 'counted_by' => []];
        if (! $enabled || ! $organization instanceof Organization) {
            return $state;
        }

        $valuation = app(InventoryValuationManager::class);
        foreach ($count->lines()->with('variant.article')->get() as $line) {
            $difference = $line->difference();
            if ($line->applied || $difference === null || bccomp($difference, '0', self::SCALE) === 0 || ! $line->variant instanceof ArticleVariant) {
                continue;
            }
            $value = bcmul(DecimalQty::positive($difference), $valuation->forVariant($line->variant, $organization)->unitCost($line->variant, $warehouse), self::SCALE);
            if (bccomp($value, $threshold, self::SCALE) >= 0) {
                $state['flagged_lines']++;
                if ($line->counted_by !== null) {
                    $state['counted_by'][] = (int) $line->counted_by;
                }
            }
        }
        $state['counted_by'] = array_values(array_unique($state['counted_by']));

        if ($state['flagged_lines'] > 0 && ($reviewedBy === null || in_array($reviewedBy, $state['counted_by'], true))) {
            throw new RuntimeException((string) __('inventory.count_ui.four_eyes_blocked'));
        }

        return $state;
    }
}
