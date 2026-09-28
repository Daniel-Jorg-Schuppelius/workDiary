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
use App\Models\Fleet\Vehicle;
use App\Models\Platform\User;
use App\Models\Time\TimeEntry;
use App\Models\Travel\TravelLog;
use App\Services\Asset\Contracts\AssetComplianceStatusProvider;
use App\Services\Routing\Contracts\TravelLogRecorder;
use App\Support\{Setting, Tz};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{CryptoHelper, DataUrlHelper};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Encapsulates persistence of {@see TravelLog} entries and, when configured,
 * synchronises a paired {@see TimeEntry} with `kind=travel` so the travel time
 * is visible on the daily dashboard and in reports.
 *
 * Feature 137 (steuerliches Fahrtenbuch): einzige Schreibstelle für Fahrten
 * im Logbook-Modus — Regelprüfung ({@see LogbookRules}), Festschreibung
 * (Tagesende bzw. explizit) und Stornofahrt statt Änderung.
 */
class TravelLogService implements TravelLogRecorder {
    public function available(): bool {
        return true;
    }

    public function __construct(
        private readonly MileageRateResolver $rates,
        private readonly LogbookRules $logbook,
        private readonly AssetComplianceStatusProvider $compliance,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws LogbookViolationException
     */
    public function create(array $attributes): TravelLog {
        return $this->persist($attributes, false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws LogbookViolationException
     */
    private function persist(array $attributes, bool $chainRepair): TravelLog {
        return DB::transaction(function () use ($attributes, $chainRepair): TravelLog {
            $attributes = $this->applyDefaults($attributes);
            $this->assertLogbookRules($attributes, null, $chainRepair);
            if (! $chainRepair && ($attributes['corrects_travel_log_id'] ?? null) === null) {
                $this->assertInspectionAllowsTrip($attributes);
            }
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
            $this->repairSuccessor($original, $correction, $actor);

            return $correction;
        });
    }

    /**
     * Neuberechnung nach einem Storno mitten in der Kette (MVP-992): Endet die
     * Stornofahrt bei einem anderen Stand als das Original, beginnt die direkte
     * Folgefahrt dort — festgeschrieben als Folgekorrektur, sonst direkt. Weiter
     * reicht die Wirkung nicht, die übrigen Fahrten schließen an die Folgefahrt an.
     */
    private function repairSuccessor(TravelLog $original, TravelLog $correction, ?User $actor): void {
        if ($original->vehicle_id === null || $original->odometer_end_km === null || $correction->odometer_end_km === null
            || (int) $original->odometer_end_km === (int) $correction->odometer_end_km) {
            return;
        }
        $successor = TravelLog::query()
            ->where('vehicle_id', $original->vehicle_id)
            ->effective()
            ->whereKeyNot([$original->id, $correction->id])
            ->where('odometer_start_km', $original->odometer_end_km)
            ->orderBy('date')
            ->orderBy('id')
            ->first();
        if (! $successor instanceof TravelLog || $successor->odometer_end_km === null || $successor->odometer_end_km < $correction->odometer_end_km) {
            return;
        }

        if (! $successor->isLocked()) {
            $successor->odometer_start_km = $correction->odometer_end_km;
            $successor->save();

            return;
        }

        $this->lock($successor, $actor);
        $repair = $this->persist(array_merge(
            array_intersect_key($successor->getAttributes(), array_flip([
                'organization_id', 'user_id', 'project_id', 'task_id', 'customer_id', 'attendance_id', 'vehicle', 'vehicle_id', 'vehicle_label',
                'date', 'started_at', 'ended_at', 'from_address', 'to_address', 'from_lat', 'from_lng', 'to_lat', 'to_lng', 'distance_km',
                'round_trip', 'reimbursable', 'purpose', 'rate_per_km', 'trip_kind', 'notes', 'odometer_end_km',
            ])),
            [
                'odometer_start_km' => $correction->odometer_end_km,
                'corrects_travel_log_id' => $successor->id,
                'correction_reason' => (string) __('Folgekorrektur zur Stornofahrt vom :date', ['date' => $correction->date?->format('d.m.Y') ?? '']),
            ],
        ), true);
        $successor->audit('travelLog.corrected', ['correction_id' => $repair->id, 'reason' => $repair->correction_reason, 'chain_repair' => true]);
    }

    /**
     * Fahrt mit Unterschrift abschließen (MVP-992): nur die fahrende Person,
     * nur Fahrtenbuch-Fahrten, genau einmal; die Unterschrift schreibt fest.
     */
    public function sign(TravelLog $log, User $actor, string $base64Png): TravelLog {
        if (! $log->isLogbook() || (int) $log->user_id !== (int) $actor->id || $log->isSigned()) {
            throw ValidationException::withMessages(['signature' => (string) __('Diese Fahrt kann nicht (mehr) von Ihnen unterschrieben werden.')]);
        }
        $binary = DataUrlHelper::decode($base64Png, ['image/png'], 1_000_000);
        if ($binary === false) {
            throw ValidationException::withMessages(['signature' => (string) __('Die Unterschrift konnte nicht gelesen werden. Bitte erneut unterschreiben.')]);
        }
        $path = 'travel-logs/signatures/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . '.png';
        Storage::disk('local')->put($path, $binary);

        return DB::transaction(function () use ($log, $actor, $path): TravelLog {
            if (! $log->isLocked()) {
                $this->lock($log, $actor);
            }
            $signedAt = now();
            $log->forceFill([
                'driver_signed_at' => $signedAt,
                'driver_signature_path' => $path,
                'driver_signature_hash' => CryptoHelper::hash(implode('|', [
                    (string) $log->id, (string) $log->date?->toDateString(), (string) $log->odometer_start_km, (string) $log->odometer_end_km,
                    (string) $log->distance_km, $log->trip_kind->value, (string) $log->purpose, (string) $actor->id, $signedAt->toIso8601String(),
                ])),
            ])->save();
            $log->audit('travelLog.signed', ['by' => $actor->id]);

            return $log->refresh();
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
     * Fahrtsperre (MVP-994, Einstellung `fleet.block_trips_on_overdue_inspection`):
     * keine neue Fahrt ab heute mit einem Fahrzeug, dessen Pflichtprüfung überfällig
     * oder gesperrt ist. Vergangene Fahrten bleiben dokumentierbar (Lückenlosigkeit).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws LogbookViolationException
     */
    private function assertInspectionAllowsTrip(array $attributes): void {
        $vehicleId = $attributes['vehicle_id'] ?? null;
        if ($vehicleId === null || $vehicleId === '' || ! (bool) Setting::get('fleet.block_trips_on_overdue_inspection', false)) {
            return;
        }
        $vehicle = Vehicle::query()->with('asset')->find((int) $vehicleId);
        $asset = $vehicle?->asset;
        $date = isset($attributes['date']) ? CarbonImmutable::parse((string) $attributes['date']) : CarbonImmutable::today();
        if ($asset === null || $date->lessThan(Tz::startOfDay())) {
            return;
        }
        $status = $this->compliance->statusFor($asset);
        if ($status->blocksTrips()) {
            throw new LogbookViolationException(['vehicle_id' => (string) __('Keine neue Fahrt mit diesem Fahrzeug — Prüfstatus: :status.', ['status' => $status->label()])]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws LogbookViolationException
     */
    private function assertLogbookRules(array $attributes, ?TravelLog $existing, bool $chainRepair = false): void {
        $vehicleId = $attributes['vehicle_id'] ?? null;
        if ($vehicleId === null || $vehicleId === '') {
            return;
        }
        $vehicle = Vehicle::query()->find((int) $vehicleId);
        if (! $vehicle instanceof Vehicle) {
            return;
        }

        $errors = $this->logbook->violations($attributes, $vehicle, $existing, $chainRepair);
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

        $tz = $log->organization !== null ? Tz::ofOrganization($log->organization) : Tz::current();
        $payload = [
            'organization_id' => $log->organization_id,
            'user_id' => $log->user_id,
            'project_id' => $log->project_id,
            'task_id' => $log->task_id,
            'customer_id' => $log->customer_id,
            'attendance_id' => $log->attendance_id,
            'travel_log_id' => $log->id,
            'date' => $log->date,
            // Fahrtenbuch führt Ortszeit, Zeiteinträge UTC (UI-Fuzz 2026-09-21).
            'started_at' => CarbonImmutable::parse($log->started_at->format('Y-m-d H:i:s'), $tz)->utc(),
            'ended_at' => CarbonImmutable::parse($log->ended_at->format('Y-m-d H:i:s'), $tz)->utc(),
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
