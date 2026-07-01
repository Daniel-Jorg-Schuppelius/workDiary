<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryLedger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState};
use App\Models\{ArticleVariant, StockMovement, Warehouse};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lokale Lager-Engine (Feature 048, MVP-067): schreibt das append-only Journal
 * und leitet Salden ausschließlich daraus ab (Summe signierter Mengen je
 * Bucket). Verfügbarkeit = physisch − reserviert − gesperrt − QS. Abgänge und
 * Reservierungen prüfen die Verfügbarkeit; negativer Bestand ist nur mit
 * ausdrücklicher Freigabe möglich. Alle Beträge dezimal (bcmath).
 */
class InventoryLedger {
    public const SCALE = 4;

    /** Schreibt eine Buchung append-only; idempotent über (org, idempotency_key). */
    public function post(StockPosting $posting): StockMovement {
        $orgId = $posting->variant->organization_id;

        return DB::transaction(function () use ($posting, $orgId): StockMovement {
            // Idempotenzprüfung in derselben Transaktion wie der Insert: ein
            // paralleler Aufruf mit gleichem Schlüssel wird durch den Unique-Index
            // abgefangen (siehe catch unten), nicht durch ein TOCTOU-Fenster.
            if ($posting->idempotencyKey !== null && $orgId !== null) {
                $existing = $this->findByIdempotencyKey($orgId, $posting->idempotencyKey, lock: true);
                if ($existing !== null) {
                    return $existing; // Doppelbuchung verhindert
                }
            }

            try {
                return StockMovement::query()->create([
                    'organization_id' => $orgId,
                    'article_variant_id' => $posting->variant->id,
                    'warehouse_id' => $posting->warehouse->id,
                    'stock_lot_id' => $posting->stockLotId,
                    'stock_serial_id' => $posting->stockSerialId,
                    'stock_state' => $posting->state->value,
                    'ownership_type' => $posting->ownership->value,
                    'owner_ref' => $posting->ownerRef,
                    'movement_type' => $posting->type->value,
                    'qty_base' => $posting->signedQty,
                    'original_qty' => $posting->originalQty,
                    'original_unit' => $posting->originalUnit,
                    'occurred_at' => Carbon::now(),
                    'actor_user_id' => $posting->actorUserId,
                    'source_type' => $posting->source?->getMorphClass(),
                    'source_id' => $posting->source?->getKey(),
                    'idempotency_key' => $posting->idempotencyKey,
                    'cost_unit' => $posting->costUnit,
                    'cost_total' => $posting->costTotal,
                    'currency' => $posting->currency,
                ]);
            } catch (QueryException $e) {
                // Verlor das Rennen um den Unique-Index: bestehende Buchung
                // idempotent zurückgeben statt mit 500 durchzuschlagen.
                if ($posting->idempotencyKey !== null && $orgId !== null) {
                    $existing = $this->findByIdempotencyKey($orgId, $posting->idempotencyKey, lock: false);
                    if ($existing !== null) {
                        return $existing;
                    }
                }
                throw $e;
            }
        });
    }

    private function findByIdempotencyKey(int $orgId, string $key, bool $lock): ?StockMovement {
        $query = StockMovement::query()
            ->where('organization_id', $orgId)
            ->where('idempotency_key', $key);
        if ($lock) {
            $query->lockForUpdate();
        }

        /** @var StockMovement|null */
        return $query->first();
    }

    /**
     * Saldo eines Bestandszustands (optional je Eigentumsart) in Basiseinheit.
     *
     * @return numeric-string
     */
    public function balance(ArticleVariant $variant, Warehouse $warehouse, StockState $state, ?OwnershipType $ownership = null): string {
        $query = StockMovement::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('stock_state', $state->value);
        if ($ownership !== null) {
            $query->where('ownership_type', $ownership->value);
        }

        $sum = '0';
        foreach ($query->pluck('qty_base') as $value) {
            $sum = bcadd($sum, $this->numeric((string) $value), self::SCALE);
        }

        return bcadd($sum, '0', self::SCALE);
    }

    /**
     * Verfügbar = physisch − reserviert − gesperrt − QS (über alle Eigentumsarten).
     *
     * @return numeric-string
     */
    public function available(ArticleVariant $variant, Warehouse $warehouse): string {
        $physical = $this->balance($variant, $warehouse, StockState::Physical);
        $result = $physical;
        foreach ([StockState::Reserved, StockState::Blocked, StockState::Quality] as $state) {
            $result = bcsub($result, $this->balance($variant, $warehouse, $state), self::SCALE);
        }

        return $result;
    }

    /**
     * Wie {@see available()}, sperrt aber die zugrunde liegenden Bewegungszeilen
     * des Buckets (`SELECT … FOR UPDATE`). Nur innerhalb einer Transaktion sinnvoll:
     * konkurrierende Abgänge/Reservierungen serialisieren so über denselben Bestand
     * und können nicht beide gegen denselben Saldo buchen (kein Überverkauf).
     *
     * @return numeric-string
     */
    public function availableForUpdate(ArticleVariant $variant, Warehouse $warehouse): string {
        $rows = StockMovement::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereIn('stock_state', [
                StockState::Physical->value,
                StockState::Reserved->value,
                StockState::Blocked->value,
                StockState::Quality->value,
            ])
            ->lockForUpdate()
            ->get(['stock_state', 'qty_base']);

        $result = '0';
        foreach ($rows as $row) {
            $qty = $this->numeric((string) $row->qty_base);
            $result = $row->stock_state === StockState::Physical
                ? bcadd($result, $qty, self::SCALE)
                : bcsub($result, $qty, self::SCALE);
        }

        return bcadd($result, '0', self::SCALE);
    }

    // ── Semantische Buchungen ───────────────────────────────────────────

    public function receipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null): StockMovement {
        return $this->post(new StockPosting($variant, $warehouse, StockState::Physical, $this->positive($qty), StockMovementType::Receipt, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId));
    }

    public function issue(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, bool $allowNegative = false, ?string $idempotencyKey = null, ?int $actorUserId = null): StockMovement {
        // Prüfung und Buchung in einer Transaktion: availableForUpdate() sperrt den
        // Bestand, sodass parallele Abgänge nicht beide gegen denselben Saldo buchen.
        return DB::transaction(function () use ($variant, $warehouse, $qty, $ownership, $allowNegative, $idempotencyKey, $actorUserId): StockMovement {
            $this->guardSufficient($variant, $warehouse, $qty, $allowNegative);

            return $this->post(new StockPosting($variant, $warehouse, StockState::Physical, $this->negative($qty), StockMovementType::Issue, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId));
        });
    }

    public function reserve(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null): StockMovement {
        return DB::transaction(function () use ($variant, $warehouse, $qty, $ownership, $idempotencyKey, $actorUserId): StockMovement {
            $this->guardSufficient($variant, $warehouse, $qty, false);

            return $this->post(new StockPosting($variant, $warehouse, StockState::Reserved, $this->positive($qty), StockMovementType::Reserve, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId));
        });
    }

    public function releaseReservation(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null): StockMovement {
        return $this->post(new StockPosting($variant, $warehouse, StockState::Reserved, $this->negative($qty), StockMovementType::ReleaseReservation, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId));
    }

    public function finishedGoodReceipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null): StockMovement {
        return $this->post(new StockPosting($variant, $warehouse, StockState::Physical, $this->positive($qty), StockMovementType::FinishedGoodReceipt, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId));
    }

    /** Inventurdifferenz/Gegenbuchung: signierte Menge auf einen Zustand. */
    public function correction(ArticleVariant $variant, Warehouse $warehouse, StockState $state, string $signedQty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null): StockMovement {
        return $this->post(new StockPosting($variant, $warehouse, $state, $this->numeric($signedQty), StockMovementType::Correction, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId));
    }

    private function guardSufficient(ArticleVariant $variant, Warehouse $warehouse, string $qty, bool $allowNegative): void {
        if ($allowNegative) {
            return;
        }
        if (bccomp($this->availableForUpdate($variant, $warehouse), $this->positive($qty), self::SCALE) < 0) {
            throw new RuntimeException('Nicht genügend verfügbarer Bestand (negativer Bestand nicht freigegeben).');
        }
    }

    /** @return numeric-string */
    private function positive(string $qty): string {
        $qty = $this->numeric($qty);

        return bccomp($qty, '0', self::SCALE) < 0 ? bcmul($qty, '-1', self::SCALE) : $qty;
    }

    /** @return numeric-string */
    private function negative(string $qty): string {
        return bcmul($this->positive($qty), '-1', self::SCALE);
    }

    /** @return numeric-string */
    private function numeric(string $value): string {
        $value = NumberHelper::normalizeDecimalString($value);

        return $value === '' || ! is_numeric($value) ? '0' : $value;
    }
}
