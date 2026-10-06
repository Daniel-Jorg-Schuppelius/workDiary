<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{StockLotStatus, StockMovementType, StockState};
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockMovement, StockValuationLayer, Warehouse};
use App\Models\Platform\User;
use App\Services\Concerns\AssertsStatusTransition;
use Closure;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Chargen-/Losverwaltung (Feature 047/048, E2): Chargen anlegen (eindeutig je
 * Variante), Wareneingang in eine Charge buchen (mit MHD für FEFO) sowie
 * Verfallsüberwachung und Sperre. Bewertung läuft über die FEFO-Schichten, den
 * Chargenbestand führt das Lagerbuch ({@see LotStockReader}). Die Sperre ist
 * eine Buchung in den Zustand „gesperrt“ je Topf; die Ware bleibt liegen.
 */
class LotService {
    use AssertsStatusTransition;

    public const BLOCK_KEY_PREFIX = 'lot-block:';

    public const RELEASE_KEY_PREFIX = 'lot-release:';

    /** Korrekturpaarung des Reparaturbefehls; {@see ExternalStockMirror} spiegelt sie nicht. */
    public const REPAIR_KEY_PREFIX = 'lot-repair:';

    public function __construct(
        private readonly FefoValuationService $fefo,
        private readonly LotStockReader $lotStock,
        private readonly InventoryLedger $ledger,
    ) {}

    /** Legt eine Charge an oder liefert die bestehende (eindeutig je Org+Variante+lot_no). */
    public function register(ArticleVariant $variant, string $lotNo, ?string $bestBefore = null, ?string $mfgDate = null, ?string $supplierRef = null): StockLot {
        $lotNo = trim($lotNo);
        if ($lotNo === '') {
            throw new RuntimeException('Leere Chargennummer.');
        }

        return StockLot::query()->firstOrCreate(
            ['organization_id' => $variant->organization_id, 'article_variant_id' => $variant->id, 'lot_no' => $lotNo],
            ['best_before' => $bestBefore, 'mfg_date' => $mfgDate, 'supplier_ref' => $supplierRef, 'status' => StockLotStatus::Active],
        );
    }

    /** Wareneingang in eine Charge (legt eine FEFO-Bewertungsschicht an). */
    public function receiveIntoLot(ArticleVariant $variant, Warehouse $warehouse, string $qty, string $unitCost, StockLot $lot, string $currency = 'EUR', ?int $actorUserId = null): StockMovement {
        return DB::transaction(function () use ($variant, $warehouse, $qty, $unitCost, $lot, $currency, $actorUserId): StockMovement {
            $this->lockAndRefresh($lot);
            // Ein Zugang an eine zusammengeführte Charge läge an einer Charge, die kein Pickzettel mehr vorschlägt.
            if ($lot->status === StockLotStatus::Merged) {
                throw new RuntimeException((string) __('inventory.lot.error.receipt_into_merged', ['lot' => $lot->lot_no]));
            }
            // Die Sperrbuchung entspricht dem Chargensaldo nur, solange die gesperrte Charge nichts annimmt (D5).
            if ($lot->status === StockLotStatus::Blocked) {
                throw new RuntimeException((string) __('inventory.lot.error.receipt_into_blocked', ['lot' => $lot->lot_no]));
            }

            return $this->fefo->receiptIntoLot($variant, $warehouse, $qty, $unitCost, $lot, $currency, $actorUserId);
        });
    }

    /**
     * Sperrt eine aktive Charge: ihr physischer Saldo je Topf wird in den
     * Zustand „gesperrt“ gebucht und zählt nicht mehr als verfügbar; die Ware
     * bleibt liegen, der Pickzettel schlägt die Charge nicht mehr vor.
     */
    public function block(StockLot $lot, string $reason, User $actor): StockLot {
        DB::transaction(function () use ($lot, $reason, $actor): void {
            $this->lockAndRefresh($lot);
            $this->assertStatusTransition($lot->status, StockLotStatus::Blocked);
            $reason = $this->requireReason($reason);

            $lot->forceFill([
                'status' => StockLotStatus::Blocked,
                'blocked_reason' => $reason,
                'blocked_at' => Carbon::now(),
                'blocked_by_user_id' => $actor->id,
            ])->save();
            $this->postHolds($lot, $this->lotStock->pots($lot, forUpdate: true), StockMovementType::LotBlock, $actor->id);
            $lot->audit('stock_lot.blocked', ['lot_no' => $lot->lot_no, 'reason' => $reason, 'actor_user_id' => $actor->id]);
        });

        return $lot;
    }

    /** Gibt eine gesperrte Charge wieder frei und bucht ihren gesperrten Saldo zurück; die Begründung steht im Audit. */
    public function unblock(StockLot $lot, string $reason, User $actor): StockLot {
        DB::transaction(function () use ($lot, $reason, $actor): void {
            $this->lockAndRefresh($lot);
            $this->assertStatusTransition($lot->status, StockLotStatus::Active);
            $reason = $this->requireReason($reason);

            $lot->forceFill([
                'status' => StockLotStatus::Active,
                'blocked_reason' => null,
                'blocked_at' => null,
                'blocked_by_user_id' => null,
            ])->save();
            $this->postHolds($lot, $this->lotStock->holds($lot, forUpdate: true), StockMovementType::LotRelease, $actor->id);
            $lot->audit('stock_lot.released', ['lot_no' => $lot->lot_no, 'reason' => $reason, 'actor_user_id' => $actor->id]);
        });

        return $lot;
    }

    /**
     * Bucht einen Zugang in eine Charge, der nicht scheitern darf (Rückbuchung,
     * Wiedereinlagerung): ist die Charge gesperrt, wird die Menge mitgesperrt —
     * sonst läge der gesperrte Saldo unter dem Chargensaldo (D5). Die Charge
     * wird vor den Bewegungen gesperrt gelesen, wie beim Sperren: entweder
     * erfasst die Sperre den Zugang oder der Zugang die Sperre.
     *
     * @param  Closure(): StockMovement  $post  bucht die Bewegungen, die letzte ist der Zugang
     */
    public function holdArrival(?StockLot $lot, Closure $post): StockMovement {
        return DB::transaction(function () use ($lot, $post): StockMovement {
            $current = $lot instanceof StockLot ? $this->lotStock->currentOf($lot) : null;
            $current = $current instanceof StockLot ? StockLot::query()->lockForUpdate()->find($current->id) : null;
            $arrival = $post();
            if ($current?->status === StockLotStatus::Blocked) {
                $this->postArrivalHold($current, $arrival);
            }

            return $arrival;
        });
    }

    private function postArrivalHold(StockLot $lot, StockMovement $arrival): void {
        $variant = $arrival->variant;
        $warehouse = $arrival->warehouse;
        if (! $variant instanceof ArticleVariant || ! $warehouse instanceof Warehouse) {
            return;
        }

        $this->ledger->post(new StockPosting(
            $variant,
            $warehouse,
            StockState::Blocked,
            Decimal::of((string) $arrival->qty_base, InventoryLedger::SCALE)->abs()->getValue(),
            StockMovementType::LotBlock,
            $arrival->ownership_type,
            ownerRef: $arrival->owner_ref,
            idempotencyKey: self::BLOCK_KEY_PREFIX . $lot->id . ':arrival:' . $arrival->id,
            actorUserId: $arrival->actor_user_id,
            source: $lot,
            stockLotId: $lot->id,
            bin: $arrival->bin,
        ));
    }

    /**
     * Bucht `$qty` des Chargensaldos nach „ohne Charge“ um — je Topf in
     * Topffolge ein Paar Korrekturen (−mit Charge, +ohne Charge); der physische
     * Bestand bleibt gleich (Reparaturbefehl).
     *
     * @param  numeric-string  $qty
     */
    public function detach(StockLot $lot, string $qty, ?int $actorUserId = null): void {
        $variant = $lot->variant;
        if (! $variant instanceof ArticleVariant) {
            return;
        }

        DB::transaction(function () use ($lot, $qty, $actorUserId, $variant): void {
            $this->lockAndRefresh($lot);
            // Bei einer gesperrten Charge bliebe die Sperrbuchung über dem Chargensaldo stehen.
            if ($lot->status !== StockLotStatus::Active) {
                throw new RuntimeException((string) __('inventory.lot.error.not_active', ['lot' => $lot->lot_no]));
            }
            $remaining = Decimal::of($qty, InventoryLedger::SCALE);
            $n = StockMovement::query()->where('stock_lot_id', $lot->id)->whereLikeEscaped('idempotency_key', self::REPAIR_KEY_PREFIX . $lot->id . ':', 'prefix')->count();
            foreach ($this->lotStock->pots($lot, forUpdate: true) as $pot) {
                if (! $remaining->isPositive()) {
                    break;
                }
                if (! $pot['qty']->isPositive()) {
                    continue;
                }
                $take = Decimal::min($pot['qty'], $remaining);
                $n++;
                foreach ([[$take->negated(), $lot->id, 'out'], [$take, null, 'in']] as [$signed, $lotId, $side]) {
                    $this->ledger->post(new StockPosting(
                        $variant,
                        $pot['warehouse'],
                        StockState::Physical,
                        $signed->getValue(),
                        StockMovementType::Correction,
                        $pot['ownership'],
                        ownerRef: $pot['ownerRef'],
                        idempotencyKey: self::REPAIR_KEY_PREFIX . $lot->id . ':' . $n . ':' . $side,
                        actorUserId: $actorUserId,
                        source: $lot,
                        stockLotId: $lotId,
                        bin: $pot['bin'],
                    ));
                }
                $remaining = $remaining->minus($take);
            }
        });
    }

    /** Liest die Charge unter Zeilensperre neu ein — vor den Bewegungen, dieselbe Folge wie beim Zusammenführen. */
    private function lockAndRefresh(StockLot $lot): void {
        $locked = StockLot::query()->lockForUpdate()->find($lot->id);
        if (! $locked instanceof StockLot) {
            throw new RuntimeException((string) __('inventory.lot.flash.unknown'));
        }
        $lot->setRawAttributes($locked->getAttributes(), true);
    }

    /**
     * Sperr- bzw. Freigabebuchung je Topf mit positivem Saldo. Die laufende
     * Nummer im Schlüssel setzt frühere Sperren fort (unter der Chargensperre gezählt).
     *
     * @param  list<array{warehouse: Warehouse, bin: \App\Models\Inventory\WarehouseBin|null, ownership: \App\Enums\Inventory\OwnershipType, ownerRef: string|null, qty: Decimal}>  $pots
     */
    private function postHolds(StockLot $lot, array $pots, StockMovementType $type, int $actorUserId): void {
        $variant = $lot->variant;
        if (! $variant instanceof ArticleVariant) {
            return;
        }

        $prefix = $type === StockMovementType::LotBlock ? self::BLOCK_KEY_PREFIX : self::RELEASE_KEY_PREFIX;
        $n = StockMovement::query()->where('stock_lot_id', $lot->id)->where('movement_type', $type->value)->count();
        foreach ($pots as $pot) {
            if (! $pot['qty']->isPositive()) {
                continue;
            }
            $this->ledger->post(new StockPosting(
                $variant,
                $pot['warehouse'],
                StockState::Blocked,
                ($type === StockMovementType::LotBlock ? $pot['qty'] : $pot['qty']->negated())->getValue(),
                $type,
                $pot['ownership'],
                ownerRef: $pot['ownerRef'],
                idempotencyKey: $prefix . $lot->id . ':' . (++$n),
                actorUserId: $actorUserId,
                source: $lot,
                stockLotId: $lot->id,
                bin: $pot['bin'],
            ));
        }
    }

    private function requireReason(string $reason): string {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException((string) __('inventory.lot.error.reason_required'));
        }

        return $reason;
    }

    /**
     * Bewertungsschichten mit Restbestand, deren MHD bis zum Stichtag fällt
     * (MHD-Überwachung, frühestes Verfallsdatum zuerst).
     *
     * @return Collection<int, StockValuationLayer>
     */
    public function expiringUntil(Carbon $date, ?ArticleVariant $variant = null): Collection {
        $variantId = $variant?->id;

        return StockValuationLayer::query()
            ->whereNotNull('best_before')
            ->where('best_before', '<=', $date)
            ->where('qty_remaining', '>', 0)
            ->when($variantId !== null, fn ($q) => $q->where('article_variant_id', $variantId))
            ->orderBy('best_before')
            ->get();
    }
}
