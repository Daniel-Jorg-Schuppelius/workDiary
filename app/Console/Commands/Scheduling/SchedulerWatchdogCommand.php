<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerWatchdogCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Scheduling;

use App\Models\{ScheduledJobOverride, ScheduledJobRun, ScheduledJobState};
use App\Scheduling\{JobRegistry, SchedulerRegistrar};
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Überfälligkeits-Wächter der Scheduler-Registry (Feature 067, MVP-177):
 * meldet Jobs, deren letzter Soll-Lauf ohne Erfolg verstrichen ist
 * (Grace = erwartete Laufzeit + Puffer), dedupliziert über
 * overdue_notified_at. Räumt zusätzlich alte Laufnachweise ab
 * (Setting scheduler.retention_days).
 *
 * Exit-Code bleibt im Scheduled-Betrieb 0 („Wächter lief"), --fail
 * liefert 1 bei Befunden (CI/Monitoring).
 */
class SchedulerWatchdogCommand extends Command {
    private const GRACE_MINUTES = 15;

    protected $signature = 'scheduler:watchdog {--fail : Exit-Code 1, wenn überfällige Jobs gefunden wurden}';

    protected $description = 'Prüft Registry-Jobs auf Überfälligkeit und räumt alte Laufnachweise ab';

    public function handle(JobRegistry $registry, SchedulerRegistrar $registrar): int {
        $now = CarbonImmutable::now();
        $overrides = ScheduledJobOverride::systemMap();
        $overdue = [];

        foreach ($registry->all() as $key => $definition) {
            if ($key === 'scheduler.watchdog') {
                continue;
            }
            if (($overrides[$key]['enabled'] ?? true) === false) {
                continue; // bewusst pausiert — sichtbar auf der Adminseite
            }

            $state = ScheduledJobState::query()->where('job_key', $key)->first();
            if ($state === null || ($state->last_started_at === null && $state->last_success_at === null)) {
                // Nie gestartet (z. B. Frischinstallation, selten laufende
                // Jobs wie payroll 2×/Jahr): kein belastbarer Beleg — der
                // Scheduler-Heartbeat deckt den Totalausfall ab.
                continue;
            }

            $cadence = $registrar->resolvedCadence($definition);
            $cron = new CronExpression($cadence->cronExpression());
            $due = CarbonImmutable::instance($cron->getPreviousRunDate($now->toDateTime()));
            $deadline = $due->addMinutes($definition->expectedRuntimeMinutes + self::GRACE_MINUTES);

            if ($now->lessThanOrEqualTo($deadline)) {
                continue;
            }
            if ($state->last_success_at !== null && $state->last_success_at->greaterThanOrEqualTo($due)) {
                continue;
            }
            if ($state->overdue_notified_at !== null && $state->overdue_notified_at->greaterThanOrEqualTo($due)) {
                continue; // bereits für diesen Soll-Lauf gemeldet
            }

            $overdue[] = $key;
            $state->overdue_notified_at = $now;
            $state->save();

            Log::warning('scheduler.job_overdue', [
                'job' => $key,
                'due_at' => $due->toIso8601String(),
                'last_success_at' => $state->last_success_at?->toIso8601String(),
                'criticality' => $definition->criticality->value,
            ]);
            $this->warn("Überfällig: {$key} (fällig {$due->toIso8601String()})");

            // Admin-Aufgabe + Benachrichtigung (Feature 041, MVP-058).
            app(\App\Services\Operations\OperationsAlertService::class)
                ->report(new \App\Services\Operations\OperationsSignal(
                    type: \App\Enums\Operations\OperationsTaskType::SchedulerOverdue,
                    dedupeKey: 'scheduler_overdue:' . $key,
                    severity: $definition->criticality === \App\Scheduling\JobCriticality::Core
                        ? \App\Enums\Operations\OperationsTaskSeverity::Critical
                        : \App\Enums\Operations\OperationsTaskSeverity::Warning,
                    titleKey: 'operations.task.scheduler_overdue',
                    params: ['job' => $definition->label(), 'due' => $due->format('d.m.Y H:i')],
                    linkRoute: 'admin.scheduler.index',
                ));
        }

        $this->purgeOldRuns($now);

        if ($overdue === []) {
            $this->info('Keine überfälligen Jobs.');
        }

        return ($this->option('fail') && $overdue !== []) ? self::FAILURE : self::SUCCESS;
    }

    private function purgeOldRuns(CarbonImmutable $now): void {
        $days = (int) Setting::get('scheduler.retention_days', 30);
        $deleted = ScheduledJobRun::query()
            ->where('started_at', '<', $now->subDays(max(1, $days)))
            ->delete();
        if ($deleted > 0) {
            $this->line("Laufnachweise bereinigt: {$deleted}");
        }
    }
}
