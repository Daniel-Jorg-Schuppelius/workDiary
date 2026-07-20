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
use App\Models\{ArticleVariant, StockReservation, Warehouse};
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
    public const SCALE = 4;

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
    ): StockReservation {
        return DB::transaction(function () use ($variant, $warehouse, $qty, $ownership, $priority, $source, $createdBy): StockReservation {
            $qty = DecimalQty::positive($qty);
            if (bccomp($this->ledger->available($variant, $warehouse), $qty, self::SCALE) < 0) {
                throw new RuntimeException('Reservierung übersteigt die verfügbare Menge.');
            }

            return $this->commit($variant, $warehouse, $qty, $ownership, $priority, $source, $createdBy);
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
    ): ?StockReservation {
        return DB::transaction(function () use ($variant, $warehouse, $qty, $ownership, $priority, $source, $createdBy): ?StockReservation {
            $available = $this->ledger->available($variant, $warehouse);
            $want = DecimalQty::positive($qty);
            $take = bccomp($available, $want, self::SCALE) < 0 ? $available : $want;

            if (bccomp($take, '0', self::SCALE) <= 0) {
                return null;
            }

            return $this->commit($variant, $warehouse, $take, $ownership, $priority, $source, $createdBy);
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
            $this->ledger->releaseReservation($variant, $warehouse, $qty, $reservation->ownership_type);
            $this->ledger->issue($variant, $warehouse, $qty, $reservation->ownership_type, allowNegative: true);

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
            $this->ledger->releaseReservation($variant, $warehouse, $amount, $reservation->ownership_type);

            $reservation->quantity = bcsub($reservation->quantity, $amount, self::SCALE);
            if (bccomp($reservation->openQuantity(), '0', self::SCALE) <= 0) {
                $reservation->status = bccomp($reservation->consumed_qty, '0', self::SCALE) > 0
                    ? ReservationStatus::Fulfilled
                    : ReservationStatus::Released;
            }
            $reservation->save();

            return $reservation;
        });
    }

    private function commit(ArticleVariant $variant, Warehouse $warehouse, string $qty, OwnershipType $ownership, int $priority, ?Model $source, ?int $createdBy): StockReservation {
        $this->ledger->reserve($variant, $warehouse, $qty, $ownership);

        return StockReservation::query()->create([
            'organization_id' => $variant->organization_id,
            'article_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
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
