<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleReservationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Exceptions\VehicleReservationConflictException;
use App\Models\{DiaryEntry, Vehicle, VehicleReservation};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verwaltet Fahrzeug-Reservierungen der Disposition (Feature 028).
 *
 * Kernregel: ein Fahrzeug darf in einem Zeitfenster nur einmal reserviert
 * sein. {@see reserve()} prüft auf Überschneidung und wirft bei Konflikt
 * {@see VehicleReservationConflictException}, statt eine Doppelreservierung
 * anzulegen.
 */
final class VehicleReservationService {
    /**
     * Reserviert ein Fahrzeug für ein Zeitfenster, optional an einen Auftrag
     * gebunden.
     *
     * @throws VehicleReservationConflictException
     */
    public function reserve(
        Vehicle $vehicle,
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
        int $reservedByUserId,
        ?DiaryEntry $diaryEntry = null,
        ?string $note = null,
    ): VehicleReservation {
        $fromTs = Carbon::parse($from instanceof \DateTimeInterface ? $from->format('Y-m-d H:i:s') : $from);
        $toTs = Carbon::parse($to instanceof \DateTimeInterface ? $to->format('Y-m-d H:i:s') : $to);

        return DB::transaction(function () use ($vehicle, $fromTs, $toTs, $reservedByUserId, $diaryEntry, $note): VehicleReservation {
            $conflict = $this->findConflict($vehicle, $fromTs, $toTs);
            if ($conflict !== null) {
                throw new VehicleReservationConflictException($conflict);
            }

            return VehicleReservation::create([
                'organization_id' => $vehicle->organization_id,
                'vehicle_id' => $vehicle->getKey(),
                'diary_entry_id' => $diaryEntry?->getKey(),
                'reserved_by_user_id' => $reservedByUserId,
                'reserved_from' => $fromTs,
                'reserved_to' => $toTs,
                'note' => $note,
            ]);
        });
    }

    /** Hebt eine Reservierung auf (Soft-Delete). */
    public function release(VehicleReservation $reservation): void {
        $reservation->delete();
    }

    /**
     * Liefert eine kollidierende Reservierung des Fahrzeugs im Zeitfenster
     * oder null. Optional eine zu ignorierende Reservierung (z. B. bei
     * Verschiebungen).
     */
    public function findConflict(
        Vehicle $vehicle,
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
        ?int $ignoreReservationId = null,
    ): ?VehicleReservation {
        $fromTs = Carbon::parse($from instanceof \DateTimeInterface ? $from->format('Y-m-d H:i:s') : $from);
        $toTs = Carbon::parse($to instanceof \DateTimeInterface ? $to->format('Y-m-d H:i:s') : $to);

        return VehicleReservation::query()
            ->forVehicle((int) $vehicle->getKey())
            ->overlapping($fromTs, $toTs)
            ->when($ignoreReservationId, fn($q) => $q->where('id', '!=', $ignoreReservationId))
            ->first();
    }

    public function isAvailable(
        Vehicle $vehicle,
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
        ?int $ignoreReservationId = null,
    ): bool {
        return $this->findConflict($vehicle, $from, $to, $ignoreReservationId) === null;
    }
}
