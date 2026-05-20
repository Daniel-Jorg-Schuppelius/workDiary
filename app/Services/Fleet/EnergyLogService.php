<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnergyLogService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Fleet;

use App\Models\EnergyLog;
use Illuminate\Support\Facades\DB;

/**
 * Persists fuel and charging records. On create/update, recomputes
 * `distance_since_last` from the previous odometer reading of the same
 * vehicle. All callers must pre-resolve `vehicle_id` and `user_id`.
 */
class EnergyLogService {
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): EnergyLog {
        return DB::transaction(function () use ($attributes): EnergyLog {
            $log = EnergyLog::create($attributes);
            $this->recomputeDistance($log);

            return $log->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(EnergyLog $log, array $attributes): EnergyLog {
        return DB::transaction(function () use ($log, $attributes): EnergyLog {
            $log->fill($attributes);
            $log->save();
            $this->recomputeDistance($log);

            return $log->refresh();
        });
    }

    public function delete(EnergyLog $log): void {
        DB::transaction(function () use ($log): void {
            $log->delete();
        });
    }

    private function recomputeDistance(EnergyLog $log): void {
        if ($log->odometer_km === null) {
            if ($log->distance_since_last !== null) {
                $log->forceFill(['distance_since_last' => null])->saveQuietly();
            }

            return;
        }

        /** @var EnergyLog|null $previous */
        $previous = EnergyLog::query()
            ->where('vehicle_id', $log->vehicle_id)
            ->where('id', '!=', $log->id)
            ->where('started_at', '<=', $log->started_at)
            ->whereNotNull('odometer_km')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        if (! $previous instanceof EnergyLog || $previous->odometer_km === null) {
            return;
        }

        $diff = (int) $log->odometer_km - (int) $previous->odometer_km;
        if ($diff < 0) {
            $diff = 0;
        }
        $log->forceFill(['distance_since_last' => $diff])->saveQuietly();
    }
}
