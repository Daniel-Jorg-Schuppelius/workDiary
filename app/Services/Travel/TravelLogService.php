<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelLogService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Travel;

use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Enums\Travel\TravelLogVehicle;
use App\Exceptions\{LogbookViolationException, TravelLogLockedException};
use App\Models\{TimeEntry, TravelLog, User, Vehicle};
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates persistence of {@see TravelLog} entries and, when configured,
 * synchronises a paired {@see TimeEntry} with `kind=travel` so the travel time
 * is visible on the daily dashboard and in reports.
 *
 * Feature 137 (steuerliches Fahrtenbuch): einzige Schreibstelle für Fahrten
 * im Logbook-Modus — Regelprüfung ({@see LogbookRules}), Festschreibung
 * (Tagesende bzw. explizit) und Stornofahrt statt Änderung.
 */
class TravelLogService {
    public function __construct(
        private readonly MileageRateResolver $rates,
        private readonly LogbookRules $logbook,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws LogbookViolationException
     */
    public function create(array $attributes): TravelLog {
        return DB::transaction(function () use ($attributes): TravelLog {
            $attributes = $this->applyDefaults($attributes);
            $this->assertLogbookRules($attributes, null);
            $log = TravelLog::create($attributes);
            $this->syncTimeEntry($log);
            $this->mirrorOdometer($log);

            return $log->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws TravelLogLockedException|LogbookViolationException
     */
    public function update(TravelLog $log, array $attributes): TravelLog {
        // Vor der Transaktion: eine nachgezogene Tagesende-Sperre muss bestehen bleiben.
        $this->ensureEditable($log);

        return DB::transaction(function () use ($log, $attributes): TravelLog {
            $attributes = $this->applyDefaults($attributes, $log);
            $this->assertLogbookRules(array_merge($log->getAttributes(), $attributes), $log);
            $log->fill($attributes);
            $log->save();
            $this->syncTimeEntry($log);
            $this->mirrorOdometer($log);

            return $log->refresh();
        });
    }

    /** @throws TravelLogLockedException */
    public function delete(TravelLog $log): void {
        $this->ensureEditable($log);

        DB::transaction(function () use ($log): void {
            TimeEntry::query()->where('travel_log_id', $log->id)->delete();
            $log->delete();
        });
    }

    /**
     * Stornofahrt (Feature 137): Die festgeschriebene Original-Fahrt bleibt
     * unverändert stehen; die Korrektur trägt Referenz + Grund und ersetzt
     * das Original in Kette und Auswertung.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws LogbookViolationException
     */
    public function correct(TravelLog $original, array $attributes, string $reason, ?User $actor = null): TravelLog {
        return DB::transaction(function () use ($original, $attributes, $reason, $actor): TravelLog {
            if ($original->isCorrection() === false && $original->corrections()->exists()) {
                throw new LogbookViolationException(['corrects_travel_log_id' => (string) __('Diese Fahrt wurde bereits durch eine Stornofahrt ersetzt.')]);
            }
            if (! $original->isLocked()) {
                $this->lock($original, $actor);
            }

            $attributes['corrects_travel_log_id'] = $original->id;
            $attributes['correction_reason'] = trim($reason);
            $attributes['organization_id'] ??= $original->organization_id;
            $attributes['user_id'] ??= $original->user_id;

            $correction = $this->create($attributes);

            $original->audit('travelLog.corrected', [
                'correction_id' => $correction->id,
                'reason' => $correction->correction_reason,
            ]);

            return $correction;
        });
    }

    /**
     * Festschreiben (explizit oder per Tagesende-Lauf): ab jetzt greift der
     * Modell-Guard; nur Fahrten im Fahrtenbuch-Modus sind festschreibbar.
     */
    public function lock(TravelLog $log, ?User $actor = null): TravelLog {
        if ($log->isLocked()) {
            return $log;
        }
        if (! $log->isLogbook()) {
            throw new \InvalidArgumentException((string) __('Nur Fahrten eines Fahrzeugs im Fahrtenbuch-Modus werden festgeschrieben.'));
        }

        $log->locked_at = now();
        $log->save();
        $log->audit('travelLog.locked', [
            'by' => $actor?->id,
            'odometer_start_km' => $log->odometer_start_km,
            'odometer_end_km' => $log->odometer_end_km,
        ]);

        return $log;
    }

    /**
     * Tagesende-Festschreibung: alle Logbook-Fahrten vergangener Tage, die
     * noch offen sind. Zeilenweise über das Modell (Guard bleibt wirksam).
     */
    public function lockDue(): int {
        $count = 0;
        TravelLog::query()
            ->withoutGlobalScopes()
            ->whereNull('locked_at')
            ->whereDate('date', '<', now()->toDateString())
            ->whereHas('vehicleEntity', fn ($q) => $q->withoutGlobalScopes()->where('logbook_mode', true))
            ->with('vehicleEntity')
            ->orderBy('id')
            ->chunkById(200, function ($logs) use (&$count): void {
                foreach ($logs as $log) {
                    $this->lock($log);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Fahrtenbuch-Fahrten vergangener Tage gelten als festgeschrieben, auch
     * wenn der nächtliche Lauf noch nicht materialisiert hat — die Sperre
     * wird beim ersten Schreibversuch nachgezogen.
     *
     * @throws TravelLogLockedException
     */
    private function ensureEditable(TravelLog $log): void {
        if (! $log->isLocked() && $log->isLogbook() && $log->date !== null && $log->date->copy()->endOfDay()->isPast()) {
            $this->lock($log);
        }
        if ($log->isLocked()) {
            throw new TravelLogLockedException($log);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws LogbookViolationException
     */
    private function assertLogbookRules(array $attributes, ?TravelLog $existing): void {
        $vehicleId = $attributes['vehicle_id'] ?? null;
        if ($vehicleId === null || $vehicleId === '') {
            return;
        }
        $vehicle = Vehicle::query()->find((int) $vehicleId);
        if (! $vehicle instanceof Vehicle) {
            return;
        }

        $errors = $this->logbook->violations($attributes, $vehicle, $existing);
        if ($errors !== []) {
            throw new LogbookViolationException($errors);
        }
    }

    /** Tachostand des Fahrzeugs nachziehen (nur aufwärts). */
    private function mirrorOdometer(TravelLog $log): void {
        if ($log->odometer_end_km === null || $log->vehicle_id === null) {
            return;
        }
        $vehicle = $log->vehicleEntity;
        if ($vehicle instanceof Vehicle && ($vehicle->odometer_km === null || $vehicle->odometer_km < $log->odometer_end_km)) {
            $vehicle->odometer_km = $log->odometer_end_km;
            $vehicle->save();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyDefaults(array $attributes, ?TravelLog $existing = null): array {
        $vehicle = (string) ($attributes['vehicle'] ?? ($existing !== null ? $existing->vehicle->value : TravelLogVehicle::Private_->value));

        if (! array_key_exists('rate_per_km', $attributes) || $attributes['rate_per_km'] === null || $attributes['rate_per_km'] === '') {
            $vehicleId = $attributes['vehicle_id'] ?? $existing?->vehicle_id;
            $vehicleEntity = $vehicleId !== null ? Vehicle::query()->find((int) $vehicleId) : null;
            if ($vehicleEntity instanceof Vehicle && $vehicleEntity->default_rate_per_km !== null) {
                $attributes['rate_per_km'] = (string) $vehicleEntity->default_rate_per_km;
            } else {
                $attributes['rate_per_km'] = $this->rates->rateFor($vehicle, $attributes['organization_id'] ?? $existing?->organization_id);
            }
        }

        return $attributes;
    }

    private function syncTimeEntry(TravelLog $log): void {
        if (! config('timesheet.travel.auto_create_time_entry', true)) {
            return;
        }
        if (! $log->started_at || ! $log->ended_at || $log->duration_minutes <= 0) {
            // Without start/end timestamps we cannot place the entry on a timeline.
            TimeEntry::query()->where('travel_log_id', $log->id)->delete();

            return;
        }

        $payload = [
            'organization_id' => $log->organization_id,
            'user_id' => $log->user_id,
            'project_id' => $log->project_id,
            'task_id' => $log->task_id,
            'customer_id' => $log->customer_id,
            'attendance_id' => $log->attendance_id,
            'travel_log_id' => $log->id,
            'date' => $log->date,
            'started_at' => $log->started_at,
            'ended_at' => $log->ended_at,
            'minutes' => $log->duration_minutes,
            'kind' => TimeEntryKind::Travel->value,
            'activity_type' => TimeEntryActivityType::Travel->value,
            'description' => $log->purpose,
            'billable' => false,
        ];

        $existing = TimeEntry::query()->where('travel_log_id', $log->id)->first();
        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return;
        }

        TimeEntry::create($payload);
    }
}
