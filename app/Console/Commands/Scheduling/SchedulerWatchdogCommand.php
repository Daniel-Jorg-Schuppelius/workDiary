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

use App\Enums\Scheduling\JobRunStatus;
use App\Models\Platform\{ScheduledJobOverride, ScheduledJobRun, ScheduledJobState};
use App\Scheduling\{JobRegistry, PluginBinding, SchedulerRegistrar};
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
    private const int GRACE_MINUTES = 15;

    private const int FAILURE_STREAK = 3;

    protected $signature = 'scheduler:watchdog {--fail : Exit-Code 1, wenn überfällige Jobs gefunden wurden}';

    protected $description = 'Prüft Registry-Jobs auf Überfälligkeit und räumt alte Laufnachweise ab';

    public function handle(JobRegistry $registry, SchedulerRegistrar $registrar): int {
        // Command-Instanzen werden im Container wiederverwendet (Tests, schedule:work) — Plugin-Cache gilt nur pro Lauf.
        PluginBinding::reset();
        // Fälligkeit in der Zeitzone des Zeitplans berechnen — sonst gilt ein
        // „22:10"-Job um 22:10 UTC als fällig, obwohl er 22:10 Ortszeit meint.
        $now = CarbonImmutable::now((string) config('app.schedule_timezone', config('app.timezone')));
        $overrides = ScheduledJobOverride::systemMap();
        $overdue = [];

        $this->closeStaleRuns($registry, $now);

        foreach ($registry->all() as $key => $definition) {
            if ($key === SchedulerRegistrar::WATCHDOG_KEY) {
                continue;
            }
            if (($overrides[$key]['enabled'] ?? true) === false) {
                continue; // bewusst pausiert — sichtbar auf der Adminseite
            }

            $state = ScheduledJobState::query()->where('job_key', $key)->first();
            if ($state === null || ($state->last_started_at === null && $state->last_success_at === null)) {
                // Nie gestartet (Frischinstallation, selten laufende Jobs wie payroll 2×/Jahr): kein belastbarer
                // Beleg — der Scheduler-Heartbeat deckt den Totalausfall ab.
                continue;
            }

            $this->reportFailureStreak($definition, $state);

            $cadence = $registrar->resolvedCadence($definition);
            $cron = new CronExpression($cadence->cronExpression());
            // Fälligkeit in der Zeitplan-Zeitzone ermitteln, für Datenbank-
            // Vergleiche aber in UTC binden: Eloquent formatiert eine Carbon-
            // Instanz in IHRER Zeitzone, die Spalten liegen in UTC.
            $due = CarbonImmutable::instance($cron->getPreviousRunDate($now->toDateTime()));
            $dueUtc = $due->utc();
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
            if ($this->runSince($key, $dueUtc, JobRunStatus::Running)) {
                // Der Lauf dieses Soll-Slots ist noch unterwegs: Hintergrund-
                // jobs melden ihren Erfolg erst über schedule:finish, und ein
                // verspäteter Tick startet sie erst jenseits der Frist. Ein
                // Befund wäre hier ein Fehlalarm — ein Lauf aus einem älteren
                // Slot schützt bewusst nicht (started_at >= $due).
                continue;
            }
            if ($this->runSince($key, $dueUtc, JobRunStatus::Skipped)) {
                // Vorprüfung (perf-02), Überlappungssperre oder Wartungsfenster
                // haben den Slot bewusst ausgelassen — kein Ausfall.
                continue;
            }
            if ($this->gatedByInactivePlugin($definition)) {
                // Plugin-gebundener Job, dessen Plugin nirgends aktiviert ist: läuft bewusst nicht — eine
                // Überfälligkeits-Meldung wäre Rauschen (z. B. Lexoffice/JTL auf Instanzen ohne diese Anbindungen).
                continue;
            }

            $overdue[] = $key;
            $state->overdue_notified_at = $now->utc();
            $state->save();

            Log::warning('scheduler.job_overdue', [
                'job' => $key,
                'due_at' => $due->toIso8601String(),
                'last_success_at' => $state->last_success_at?->toIso8601String(),
                // Unterscheidet die Ursachen: skipped = Überlappungssperre oder
                // Wartungsfenster, running = Lauf hängt, failed = Kommando bricht ab.
                'last_status' => $state->last_status?->value,
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
                    // Roh statt formatiert: Label-Key und ISO-Zeitpunkt werden erst beim Anzeigen in
                    // Sprache/Zeitzone/Format des Betrachters aufgelöst (NotificationText).
                    params: [
                        'job' => ['key' => 'scheduler.job.' . $key, 'fallback' => $key],
                        'due' => $due->toIso8601String(),
                    ],
                    linkRoute: 'admin.scheduler.index',
                ));
        }

        $this->purgeOldRuns($now);

        if ($overdue === []) {
            $this->info('Keine überfälligen Jobs.');
        }

        return ($this->option('fail') && $overdue !== []) ? self::FAILURE : self::SUCCESS;
    }

    /** Gibt es für diesen Job einen Lauf des aktuellen Soll-Slots im genannten Zustand? */
    private function runSince(string $jobKey, CarbonImmutable $due, JobRunStatus $status): bool {
        return ScheduledJobRun::query()
            ->where('job_key', $jobKey)
            ->where('status', $status)
            ->where('started_at', '>=', $due)
            ->exists();
    }

    /**
     * Läufe, deren Prozess ohne Abschluss verschwand (OOM, Neustart, SIGKILL),
     * blieben für immer `running` und verfälschten Laufzeiten und Überfälligkeit
     * (Leistungsanalyse 2026-11-02, perf-04). Nach dem Sechsfachen der erwarteten
     * Laufzeit plus Puffer gilt der Lauf als gescheitert.
     */
    private function closeStaleRuns(JobRegistry $registry, CarbonImmutable $now): void {
        foreach ($registry->all() as $key => $definition) {
            $limit = $now->utc()->subMinutes($definition->expectedRuntimeMinutes * 6 + self::GRACE_MINUTES);
            $stale = ScheduledJobRun::query()
                ->where('job_key', $key)
                ->where('status', JobRunStatus::Running)
                ->where('started_at', '<', $limit)
                ->get();
            foreach ($stale as $run) {
                $run->update([
                    'finished_at' => $now->utc(),
                    'status' => JobRunStatus::Failed,
                    'duration_ms' => (int) $run->started_at->diffInMilliseconds($now, true),
                ]);
                Log::warning('scheduler.run_abandoned', ['job' => $key, 'started_at' => $run->started_at->toIso8601String()]);
                $this->warn("Lauf ohne Abschluss geschlossen: {$key} (gestartet {$run->started_at->toIso8601String()})");
            }
        }
    }

    /**
     * Fehlerserien (perf-04): Ein Job, der dreimal in Folge scheitert, läuft
     * weiter pünktlich und war dem Überfälligkeits-Wächter unsichtbar. Der
     * Dedupe-Schlüssel trägt die Serienlänge, damit jede weitere Verdopplung
     * erneut meldet, nicht jeder Lauf.
     */
    private function reportFailureStreak(\App\Scheduling\JobDefinition $definition, ScheduledJobState $state): void {
        $failures = (int) $state->consecutive_failures;
        if ($failures < self::FAILURE_STREAK || $this->gatedByInactivePlugin($definition)) {
            return;
        }
        if ($failures !== self::FAILURE_STREAK && ($failures & ($failures - 1)) !== 0) {
            return;
        }

        app(\App\Services\Operations\OperationsAlertService::class)
            ->report(new \App\Services\Operations\OperationsSignal(
                type: \App\Enums\Operations\OperationsTaskType::SchedulerFailing,
                dedupeKey: 'scheduler_failing:' . $definition->key . ':' . $failures,
                severity: $definition->criticality === \App\Scheduling\JobCriticality::Core
                    ? \App\Enums\Operations\OperationsTaskSeverity::Critical
                    : \App\Enums\Operations\OperationsTaskSeverity::Warning,
                titleKey: 'operations.task.scheduler_failing',
                params: [
                    'job' => ['key' => 'scheduler.job.' . $definition->key, 'fallback' => $definition->key],
                    'failures' => $failures,
                    'last' => $state->last_started_at?->toIso8601String() ?? '',
                ],
                linkRoute: 'admin.scheduler.index',
            ));
        $this->warn("Fehlerserie: {$definition->key} ({$failures}× in Folge)");
    }

    /** Dieselbe Regel wie im Registrar: ohne den Schalter meldet der Registrar auch Jobs inaktiver Plugins an, dann urteilt der Wächter mit. */
    private function gatedByInactivePlugin(\App\Scheduling\JobDefinition $definition): bool {
        return (bool) config('scheduler.skip_inactive_plugins', true) && ! PluginBinding::active($definition->plugin);
    }

    private function purgeOldRuns(CarbonImmutable $now): void {
        $days = (int) Setting::get('scheduler.retention_days', 30);
        $deleted = ScheduledJobRun::query()
            ->where('started_at', '<', $now->utc()->subDays(max(1, $days)))
            ->delete();
        if ($deleted > 0) {
            $this->line("Laufnachweise bereinigt: {$deleted}");
        }
    }
}
