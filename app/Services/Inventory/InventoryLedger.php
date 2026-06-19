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

        if ($posting->idempotencyKey !== null && $orgId !== null) {
            /** @var StockMovement|null $existing */
            $existing = StockMovement::query()
                ->where('organization_id', $orgId)
                ->where('idempotency_key', $posting->idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing; // Doppelbuchung verhindert
            }
        }

        return DB::transaction(fn (): StockMovement => StockMovement::query()->create([
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
        ]));
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

    // ── Semantische Buchungen ───────────────────────────────────────────

    public function receipt(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null): StockMovement {
        return $this->post(new StockPosting($variant, $warehouse, StockState::Physical, $this->positive($qty), StockMovementType::Receipt, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId));
    }

    public function issue(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, bool $allowNegative = false, ?string $idempotencyKey = null, ?int $actorUserId = null): StockMovement {
        $this->guardSufficient($variant, $warehouse, $qty, $allowNegative);

        return $this->post(new StockPosting($variant, $warehouse, StockState::Physical, $this->negative($qty), StockMovementType::Issue, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId));
    }

    public function reserve(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership = OwnershipType::Own, ?string $idempotencyKey = null, ?int $actorUserId = null): StockMovement {
        $this->guardSufficient($variant, $warehouse, $qty, false);

        return $this->post(new StockPosting($variant, $warehouse, StockState::Reserved, $this->positive($qty), StockMovementType::Reserve, $ownership, idempotencyKey: $idempotencyKey, actorUserId: $actorUserId));
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
        if (bccomp($this->available($variant, $warehouse), $this->positive($qty), self::SCALE) < 0) {
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
        $value = str_replace(',', '.', trim($value));

        return $value === '' || ! is_numeric($value) ? '0' : $value;
    }
}
