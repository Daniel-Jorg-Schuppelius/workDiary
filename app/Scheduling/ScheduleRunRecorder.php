<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduleRunRecorder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

use App\Models\{ScheduledJobRun, ScheduledJobState};
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\{ScheduledBackgroundTaskFinished, ScheduledTaskFailed, ScheduledTaskFinished, ScheduledTaskSkipped, ScheduledTaskStarting};
use Illuminate\Support\Facades\Log;

/**
 * Laufzeit-Nachweise je Registry-Job (Feature 067, MVP-177): schreibt
 * scheduled_job_runs (Einzelläufe) und scheduled_job_states (Aggregat,
 * Fehlerzähler). Vollständig gekapselt — Fehler hier dürfen den
 * Scheduler nie stoppen.
 */
class ScheduleRunRecorder {
    public function __construct(private readonly JobRegistry $registry) {}

    public function handleStarting(ScheduledTaskStarting $event): void {
        $this->safely(function () use ($event): void {
            $jobKey = $this->registry->keyForEventCommand($event->task->command);
            if ($jobKey === null) {
                return;
            }
            $now = CarbonImmutable::now();
            ScheduledJobRun::create([
                'job_key' => $jobKey,
                'started_at' => $now,
                'status' => ScheduledJobRun::STATUS_RUNNING,
            ]);
            $state = ScheduledJobState::forJob($jobKey);
            $state->last_started_at = $now;
            $state->last_status = ScheduledJobRun::STATUS_RUNNING;
            $state->save();
        });
    }

    public function handleFinished(ScheduledTaskFinished $event): void {
        $this->safely(function () use ($event): void {
            $jobKey = $this->registry->keyForEventCommand($event->task->command);
            if ($jobKey === null) {
                return;
            }
            // Hintergrund-Jobs (J8): dieses Event feuert schon beim Start des
            // Prozesses — das Ergebnis liefert ScheduledBackgroundTaskFinished.
            if ($event->task->runInBackground) {
                return;
            }
            $exitCode = $event->task->exitCode;
            $success = $exitCode === null || $exitCode === 0;
            $this->finishRun(
                $jobKey,
                $success ? ScheduledJobRun::STATUS_SUCCESS : ScheduledJobRun::STATUS_FAILED,
                (int) round($event->runtime * 1000),
                $exitCode,
            );
        });
    }

    /** Ende eines Hintergrund-Jobs (schedule:finish) — Laufzeit aus dem Laufnachweis. */
    public function handleBackgroundFinished(ScheduledBackgroundTaskFinished $event): void {
        $this->safely(function () use ($event): void {
            $jobKey = $this->registry->keyForEventCommand($event->task->command);
            if ($jobKey === null) {
                return;
            }
            $exitCode = $event->task->exitCode;
            $success = $exitCode === null || $exitCode === 0;
            $run = ScheduledJobRun::query()
                ->where('job_key', $jobKey)
                ->where('status', ScheduledJobRun::STATUS_RUNNING)
                ->latest('id')
                ->first();
            $durationMs = $run?->started_at !== null
                ? (int) $run->started_at->diffInMilliseconds(CarbonImmutable::now(), true)
                : null;
            $this->finishRun(
                $jobKey,
                $success ? ScheduledJobRun::STATUS_SUCCESS : ScheduledJobRun::STATUS_FAILED,
                $durationMs,
                $exitCode,
            );
        });
    }

    public function handleFailed(ScheduledTaskFailed $event): void {
        $this->safely(function () use ($event): void {
            $jobKey = $this->registry->keyForEventCommand($event->task->command);
            if ($jobKey === null) {
                return;
            }
            $this->finishRun($jobKey, ScheduledJobRun::STATUS_FAILED, null, null);
        });
    }

    public function handleSkipped(ScheduledTaskSkipped $event): void {
        $this->safely(function () use ($event): void {
            $jobKey = $this->registry->keyForEventCommand($event->task->command);
            if ($jobKey === null) {
                return;
            }
            ScheduledJobRun::create([
                'job_key' => $jobKey,
                'started_at' => CarbonImmutable::now(),
                'finished_at' => CarbonImmutable::now(),
                'status' => ScheduledJobRun::STATUS_SKIPPED,
            ]);
        });
    }

    private function finishRun(string $jobKey, string $status, ?int $durationMs, ?int $exitCode): void {
        $now = CarbonImmutable::now();

        $run = ScheduledJobRun::query()
            ->where('job_key', $jobKey)
            ->where('status', ScheduledJobRun::STATUS_RUNNING)
            ->latest('id')
            ->first();
        if ($run !== null) {
            $run->update([
                'finished_at' => $now,
                'status' => $status,
                'duration_ms' => $durationMs,
                'exit_code' => $exitCode,
            ]);
        }

        $state = ScheduledJobState::forJob($jobKey);
        $state->last_status = $status;
        $state->last_duration_ms = $durationMs;
        if ($status === ScheduledJobRun::STATUS_SUCCESS) {
            $state->last_success_at = $now;
            $state->consecutive_failures = 0;
            $state->overdue_notified_at = null;
            // Überfälligkeits-Aufgabe automatisch schließen (MVP-058).
            app(\App\Services\Operations\OperationsAlertService::class)
                ->resolve('scheduler_overdue:' . $jobKey);
        } else {
            $state->last_failure_at = $now;
            $state->consecutive_failures = ($state->consecutive_failures ?? 0) + 1;
        }
        $state->save();
    }

    private function safely(callable $callback): void {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('scheduler.run_recording_failed', ['message' => $e->getMessage()]);
        }
    }
}
