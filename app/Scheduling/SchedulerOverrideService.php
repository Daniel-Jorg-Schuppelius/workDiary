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

use App\Models\ScheduledJobOverride;
use Cron\CronExpression;
use InvalidArgumentException;

/**
 * Einziger Schreibweg für Scheduler-Overrides (Feature 067, MVP-176):
 * validiert gegen die Allowlist der JobDefinition (erlaubte Kadenzen,
 * Cron-Syntax) — die UI kann damit prinzipbedingt keine unregistrierten
 * Jobs oder freien Kommandos planen. Audit über das Override-Model.
 */
class SchedulerOverrideService {
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
        if ($override->cadence === null) {
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
