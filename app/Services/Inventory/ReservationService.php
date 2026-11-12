<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReservationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\{OwnershipType, ReservationStatus};
use App\Events\Inventory\StockReservationQuantityChanged;
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockReservation, Warehouse, WarehouseBin};
use App\Support\DecimalQty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reservierungen als eigene Entität (Feature 048, MVP-068). Reservierungen
 * werden transaktional gegen die verfügbare Menge geprüft; da `available`
 * bereits alle aktiven Reservierungen abzieht, kann eine jüngere Reservierung
 * eine bestätigte ältere NICHT still verdrängen. Der Reserved-Zustand des
 * Journals ({@see InventoryLedger}) bleibt die Quelle der Verfügbarkeit; diese
 * Entität ergänzt Lebenszyklus, Priorität und fachliche Quelle.
 */
class ReservationService {
    public const int SCALE = 4;

    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Reserviert die volle Menge oder wirft bei Unterdeckung.
     */
    public function reserve(
        ArticleVariant $variant,
        Warehouse $warehouse,
        string $qty,
        OwnershipType $ownership = OwnershipType::Own,
        int $priority = 100,
        ?Model $source = null,
        ?int $createdBy = null,
        ?WarehouseBin $bin = null,
    ): StockReservation {
        return DB::transaction(function () use ($variant, $warehouse, $qty, $ownership, $priority, $source, $createdBy, $bin): StockReservation {
            $qty = DecimalQty::positive($qty);
            if (bccomp($this->availableAt($variant, $warehouse, $bin), $qty, self::SCALE) < 0) {
                throw new RuntimeException('Reservierung übersteigt die verfügbare Menge.');
            }

            return $this->commit($variant, $warehouse, $qty, $ownership, $priority, $source, $createdBy, $bin);
        });
    }

    /**
     * Reserviert höchstens die verfügbare Menge (Teilreservierung); gibt null
     * zurück, wenn nichts verfügbar ist. Für „verfügbare Teilmenge reservieren
     * und Teilfertigung freigeben".
     */
    public function reserveUpToAvailable(
        ArticleVariant $variant,
        Warehouse $warehouse,
        string $qty,
        OwnershipType $ownership = OwnershipType::Own,
        int $priority = 100,
        ?Model $source = null,
        ?int $createdBy = null,
        ?WarehouseBin $bin = null,
    ): ?StockReservation {
        return DB::transaction(function () use ($variant, $warehouse, $qty, $ownership, $priority, $source, $createdBy, $bin): ?StockReservation {
            $available = $this->availableAt($variant, $warehouse, $bin);
            $want = DecimalQty::positive($qty);
            $take = bccomp($available, $want, self::SCALE) < 0 ? $available : $want;

            if (bccomp($take, '0', self::SCALE) <= 0) {
                return null;
            }

            return $this->commit($variant, $warehouse, $take, $ownership, $priority, $source, $createdBy, $bin);
        });
    }

    /** Erfüllt (verbraucht) einen Teil der Reservierung: reserviert → entnommen. */
    public function fulfill(StockReservation $reservation, string $qty): StockReservation {
        $qty = DecimalQty::positive($qty);
        if (bccomp($qty, $reservation->openQuantity(), self::SCALE) > 0) {
            throw new RuntimeException('Erfüllung übersteigt die offene reservierte Menge.');
        }

        return DB::transaction(function () use ($reservation, $qty): StockReservation {
            [$variant, $warehouse] = $this->endpoints($reservation);
            $bin = $reservation->bin;
            $this->ledger->releaseReservation($variant, $warehouse, $qty, $reservation->ownership_type, bin: $bin);
            $this->ledger->issue($variant, $warehouse, $qty, $reservation->ownership_type, allowNegative: true, bin: $bin);

            $reservation->consumed_qty = bcadd($reservation->consumed_qty, $qty, self::SCALE);
            if (bccomp($reservation->consumed_qty, $reservation->quantity, self::SCALE) >= 0) {
                $reservation->status = ReservationStatus::Fulfilled;
            }
            $reservation->save();

            return $reservation;
        });
    }

    /** Gibt offene reservierte Menge (oder einen Teil) wieder frei. */
    public function release(StockReservation $reservation, ?string $qty = null): StockReservation {
        return DB::transaction(function () use ($reservation, $qty): StockReservation {
            $open = $reservation->openQuantity();
            $amount = $qty === null ? $open : DecimalQty::positive($qty);
            if (bccomp($amount, $open, self::SCALE) > 0) {
                $amount = $open;
            }
            if (bccomp($amount, '0', self::SCALE) <= 0) {
                return $reservation;
            }

            [$variant, $warehouse] = $this->endpoints($reservation);
            $this->ledger->releaseReservation($variant, $warehouse, $amount, $reservation->ownership_type, bin: $reservation->bin);

            $this->reduce($reservation, $amount);

            return $reservation;
        });
    }

    /**
     * Umverteilung (MVP-1626, Feature 048 „manuelle Umverteilung“): offene
     * Menge einer Reservierung geht an eine andere desselben Bestandstopfs
     * (Variante, Lager, Platz, Eigentum). Der reservierte Bestand bleibt
     * gleich, nur seine Zuordnung wandert — sichtbar und auditiert, nie still
     * nach Priorität. Leer = die ganze offene Menge.
     */
    public function redistribute(StockReservation $from, StockReservation $to, ?string $qty = null, ?int $actorUserId = null): StockReservation {
        return DB::transaction(function () use ($from, $to, $qty, $actorUserId): StockReservation {
            $rows = StockReservation::query()->whereKey([$from->id, $to->id])->lockForUpdate()->get()->keyBy('id');
            $source = $rows->get($from->id);
            $target = $rows->get($to->id);
            if (! $source instanceof StockReservation || ! $target instanceof StockReservation || $source->id === $target->id) {
                throw new RuntimeException((string) __('inventory.reservation.redistribute_invalid'));
            }
            if (! $source->status->isOpen() || ! $target->status->isOpen()
                || $source->article_variant_id !== $target->article_variant_id
                || $source->warehouse_id !== $target->warehouse_id
                || $source->bin_id !== $target->bin_id
                || $source->ownership_type !== $target->ownership_type) {
                throw new RuntimeException((string) __('inventory.reservation.redistribute_mismatch'));
            }

            $open = $source->openQuantity();
            $amount = $qty === null ? $open : DecimalQty::positive($qty);
            if (bccomp($amount, '0', self::SCALE) <= 0 || bccomp($amount, $open, self::SCALE) > 0) {
                throw new RuntimeException((string) __('inventory.reservation.redistribute_too_much'));
            }

            $this->reduce($source, $amount);
            $target->quantity = bcadd($target->quantity, $amount, self::SCALE);
            $target->save();
            StockReservationQuantityChanged::dispatch($target, $amount);

            $target->audit('inventory.reservationRedistributed', [
                'from_reservation_id' => $source->id,
                'from_priority' => $source->priority,
                'to_priority' => $target->priority,
                'qty' => $amount,
                'actor_id' => $actorUserId,
            ]);

            return $target;
        });
    }

    /** @param numeric-string $amount */
    private function reduce(StockReservation $reservation, string $amount): void {
        $reservation->quantity = bcsub($reservation->quantity, $amount, self::SCALE);
        if (bccomp($reservation->openQuantity(), '0', self::SCALE) <= 0) {
            $reservation->status = bccomp($reservation->consumed_qty, '0', self::SCALE) > 0
                ? ReservationStatus::Fulfilled
                : ReservationStatus::Released;
        }
        $reservation->save();
        StockReservationQuantityChanged::dispatch($reservation, bcsub('0', $amount, self::SCALE));
    }

    /**
     * Verfügbarkeit am Lagerort bzw. — mit Platz (MVP-706) — nur auf diesem Platz.
     *
     * @return numeric-string
     */
    private function availableAt(ArticleVariant $variant, Warehouse $warehouse, ?WarehouseBin $bin): string {
        return $bin === null
            ? $this->ledger->available($variant, $warehouse)
            : $this->ledger->availableInBin($variant, $warehouse, $bin);
    }

    private function commit(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership, int $priority, ?Model $source, ?int $createdBy, ?WarehouseBin $bin = null): StockReservation {
        $this->ledger->reserve($variant, $warehouse, $qty, $ownership, bin: $bin);

        return StockReservation::query()->create([
            'organization_id' => $variant->organization_id,
            'article_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'bin_id' => $bin?->id,
            'quantity' => $qty,
            'consumed_qty' => '0',
            'ownership_type' => $ownership->value,
            'status' => ReservationStatus::Active->value,
            'priority' => $priority,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'reserved_at' => Carbon::now(),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Variante + Lager der Reservierung (non-null garantiert über die FKs).
     *
     * @return array{0: ArticleVariant, 1: Warehouse}
     */
    private function endpoints(StockReservation $reservation): array {
        $variant = $reservation->variant;
        $warehouse = $reservation->warehouse;
        if ($variant === null || $warehouse === null) {
            throw new RuntimeException('Reservierung ohne Variante oder Lagerort.');
        }

        return [$variant, $warehouse];
    }
}
