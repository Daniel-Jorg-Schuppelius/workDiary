<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CheckpointCheckinService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\Attendance\AttendanceSource;
use App\Models\{Attendance, AttendanceCheckpoint, User};
use CommonToolkit\Helper\Geo\GeoHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kommen/Gehen über einen Check-in-Punkt (MVP-800). Läuft durch denselben
 * `AttendanceClockService` wie Stempeluhr und Terminal — Auswertungen,
 * Korrekturwege und ArbZG-Prüfungen gelten unverändert.
 *
 * Die Richtung kommt ausdrücklich vom Formular, nicht aus einem Umschalter:
 * Ein doppelt getippter Knopf darf nicht Kommen und gleich wieder Gehen buchen.
 */
class CheckpointCheckinService {
    public function __construct(private readonly AttendanceClockService $clock) {}

    /** @param 'in'|'out' $action */
    public function stamp(AttendanceCheckpoint $checkpoint, User $user, string $action, ?float $latitude = null, ?float $longitude = null): Attendance {
        $this->assertWithinRadius($checkpoint, $latitude, $longitude);

        $context = [
            'source' => AttendanceSource::Checkin->value,
            'device' => mb_substr($checkpoint->name, 0, 64),
        ];

        return DB::transaction(function () use ($checkpoint, $user, $action, $context): Attendance {
            $open = $this->clock->current($user);

            if ($action === 'in') {
                if ($open !== null) {
                    throw ValidationException::withMessages(['action' => (string) __('attendance.checkin.error.already_in')]);
                }
                $attendance = $this->clock->clockIn($user, $context);
                $attendance->forceFill(['started_checkpoint_id' => $checkpoint->id])->save();

                return $attendance;
            }

            $attendance = $open !== null ? $this->clock->clockOut($user, $context) : null;
            if ($attendance === null) {
                throw ValidationException::withMessages(['action' => (string) __('attendance.checkin.error.not_in')]);
            }
            $attendance->forceFill(['ended_checkpoint_id' => $checkpoint->id])->save();

            return $attendance;
        });
    }

    /**
     * Mit Radius muss das Gerät seine Position senden. Sie dient nur dieser
     * Prüfung und wird nicht gespeichert.
     */
    private function assertWithinRadius(AttendanceCheckpoint $checkpoint, ?float $latitude, ?float $longitude): void {
        if (! $checkpoint->requiresLocation()) {
            return;
        }

        $center = $checkpoint->center();
        if ($center === null) {
            // Radius ohne Mittelpunkt ist eine Fehlkonfiguration — lieber sichtbar
            // ablehnen als die Ortsprüfung still auszulassen.
            throw ValidationException::withMessages(['location' => (string) __('attendance.checkin.error.no_center')]);
        }
        if ($latitude === null || $longitude === null) {
            throw ValidationException::withMessages(['location' => (string) __('attendance.checkin.error.location_required')]);
        }

        $distance = GeoHelper::haversineMeters($center[0], $center[1], $latitude, $longitude);
        if ($distance > (int) $checkpoint->radius_m) {
            throw ValidationException::withMessages(['location' => (string) __('attendance.checkin.error.too_far', [
                'distance' => (int) round($distance),
                'radius' => (int) $checkpoint->radius_m,
            ])]);
        }
    }
}
