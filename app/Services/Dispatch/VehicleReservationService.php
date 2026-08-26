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

use App\Enums\Asset\AssetBlockReason;
use App\Exceptions\{AssetNotUsableException, DriverLicenseCheckOverdueException, VehicleInspectionOverdueException, VehicleReservationConflictException};
use App\Models\{DiaryEntry, Vehicle, VehicleReservation};
use App\Services\Asset\AssetUsageGuard;
use App\Services\AssetCompliance\AssetComplianceService;
use App\Services\Fleet\DriverLicenseCheckService;
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
    /** Kontext der Einsatzprüfung (D12) — Ausnahmefreigaben gelten je Kontext. */
    public const USAGE_CONTEXT = 'vehicle_reservation';

    /**
     * Reserviert ein Fahrzeug für ein Zeitfenster, optional an einen Auftrag
     * gebunden.
     *
     * @throws VehicleReservationConflictException
     * @throws DriverLicenseCheckOverdueException MVP-417: überfällige Führerscheinkontrolle
     *                                            des Reservierenden sperrt (nutzerbezogener
     *                                            Guard — bewusst KEIN asset_block, der das
     *                                            Fahrzeug für alle sperren würde).
     * @throws VehicleInspectionOverdueException Feature 138: Fahrzeug-Asset wegen überfälliger
     *                                           Pflichtprüfung gesperrt (fahrzeugbezogen, D12).
     * @throws AssetNotUsableException           sonstige Asset-Sperre/blockierender Defekt.
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

        if (app(DriverLicenseCheckService::class)->isOverdue($reservedByUserId)) {
            throw new DriverLicenseCheckOverdueException();
        }

        $this->assertInspectionsValid($vehicle);

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

    /**
     * Feature 138 (MVP-703): Mit Asset-Zuordnung greift das gemeinsame
     * Sperrmodell (D12). Überfälligkeits-Sperren werden vorher synchron
     * nachgezogen (gleiche Entscheidung wie der Scan, idempotent), damit die
     * Reservierung nicht vom Zeitpunkt des nächtlichen Laufs abhängt — die
     * Ausnahmefreigabe je Kontext bleibt der Notfallweg.
     *
     * @throws VehicleInspectionOverdueException|AssetNotUsableException
     */
    public function assertInspectionsValid(Vehicle $vehicle): void {
        $asset = $vehicle->asset;
        if ($asset === null) {
            return;
        }

        app(AssetComplianceService::class)->syncOverdueBlocks($asset);

        try {
            app(AssetUsageGuard::class)->ensureUsable($asset, self::USAGE_CONTEXT);
        } catch (AssetNotUsableException $e) {
            $reason = $e->block?->reason;
            if ($reason === AssetBlockReason::InspectionOverdue || $reason === AssetBlockReason::InspectionFailed) {
                throw new VehicleInspectionOverdueException($vehicle, $e->block);
            }

            throw $e;
        }
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
