<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerOverrideService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

use App\Models\Platform\ScheduledJobOverride;
use Cron\CronExpression;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Einziger Schreibweg für Scheduler-Overrides (Feature 067, MVP-176):
 * validiert gegen die Allowlist der JobDefinition (erlaubte Kadenzen,
 * Cron-Syntax) — die UI kann damit prinzipbedingt keine unregistrierten
 * Jobs oder freien Kommandos planen. Audit über das Override-Model.
 */
class SchedulerOverrideService {
    public const int MAX_RETRIES = 3;

    public const int DEFAULT_RETRY_DELAY = 10;

    public function __construct(private readonly JobRegistry $registry) {}

    public function pause(string $jobKey, ?int $userId = null): void {
        $this->registry->definition($jobKey);
        ScheduledJobOverride::query()->updateOrCreate(
            ['job_key' => $jobKey, 'organization_id' => null],
            ['enabled' => false, 'updated_by_user_id' => $userId],
        );
    }

    public function resume(string $jobKey, ?int $userId = null): void {
        $this->registry->definition($jobKey);
        $override = ScheduledJobOverride::query()
            ->where('job_key', $jobKey)
            ->whereNull('organization_id')
            ->first();
        if ($override === null) {
            return;
        }
        if (! $override->hasSettings()) {
            $override->delete(); // kein Rest-Override → Default gilt wieder

            return;
        }
        $override->update(['enabled' => true, 'updated_by_user_id' => $userId]);
    }

    public function reschedule(string $jobKey, Cadence $cadence, ?int $userId = null): void {
        $definition = $this->registry->definition($jobKey);
        if (!$definition->allowsCadence($cadence->type)) {
            throw new InvalidArgumentException(
                "Job [{$jobKey}] erlaubt die Kadenz [{$cadence->type->value}] nicht.",
            );
        }
        if ($cadence->type === CadenceType::Cron && !CronExpression::isValidExpression((string) $cadence->expression)) {
            throw new InvalidArgumentException('Ungültiger Cron-Ausdruck.');
        }

        $override = ScheduledJobOverride::query()->firstOrNew(
            ['job_key' => $jobKey, 'organization_id' => null],
        );
        $override->enabled = $override->exists ? $override->enabled : true;
        $override->cadence = $cadence->toArray();
        $override->updated_by_user_id = $userId;
        $override->save();
    }

    /**
     * Zeitzone, Ruhezeit (von–bis, über Mitternacht erlaubt) und Wiederholung
     * nach Fehlschlag (MVP-1619). Leere Werte heben die Abweichung auf; ohne
     * eigene Ruhezeit und Zeitzone gilt das Betriebsfenster der Installation.
     */
    public function configureRun(string $jobKey, ?string $timezone, ?string $quietFrom, ?string $quietUntil, int $retryAttempts, ?int $retryDelayMinutes, ?int $userId = null): void {
        $this->registry->definition($jobKey);
        if ($timezone !== null && ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Unbekannte Zeitzone.');
        }
        if (($quietFrom === null) !== ($quietUntil === null) || ($quietFrom !== null && OperatingWindow::fromTimes($quietUntil, $quietFrom) === null)) {
            throw new InvalidArgumentException('Ruhezeit braucht Beginn und Ende (HH:MM, verschieden).');
        }
        if ($retryAttempts < 0 || $retryAttempts > self::MAX_RETRIES) {
            throw new InvalidArgumentException('Wiederholungen außerhalb des erlaubten Bereichs.');
        }

        $override = ScheduledJobOverride::query()->firstOrNew(
            ['job_key' => $jobKey, 'organization_id' => null],
        );
        $override->enabled = $override->exists ? $override->enabled : true;
        $override->fill([
            'timezone' => $timezone,
            'quiet_from' => $quietFrom,
            'quiet_until' => $quietUntil,
            'retry_attempts' => $retryAttempts,
            'retry_delay_minutes' => $retryAttempts > 0 ? max(1, $retryDelayMinutes ?? self::DEFAULT_RETRY_DELAY) : null,
            'updated_by_user_id' => $userId,
        ]);
        if (! $override->exists && ! $override->hasSettings()) {
            return;
        }
        $override->save();
    }

    /** Rollback auf den Registry-Default (Override komplett entfernen). */
    public function reset(string $jobKey): void {
        $this->registry->definition($jobKey);
        ScheduledJobOverride::query()
            ->where('job_key', $jobKey)
            ->whereNull('organization_id')
            ->get()
            ->each
            ->delete();
    }
}
