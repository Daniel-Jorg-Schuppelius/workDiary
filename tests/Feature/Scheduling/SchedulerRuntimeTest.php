<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerRuntimeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Scheduling;

use App\Models\{Organization, PluginSetting, ScheduledJobRun, ScheduledJobState};
use App\Scheduling\ScheduleRunRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\{ScheduledTaskFinished, ScheduledTaskStarting};
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerRuntimeTest extends TestCase {
    use RefreshDatabase;

    private function makeEvent(string $command): \Illuminate\Console\Scheduling\Event {
        $schedule = new Schedule;

        return $schedule->command($command);
    }

    private function recorder(): ScheduleRunRecorder {
        return app(ScheduleRunRecorder::class);
    }

    /** toggl.import ist plugin-gebunden — für Watchdog-Tests Plugin irgendwo aktivieren. */
    private function enableTogglPlugin(): void {
        PluginSetting::query()->create([
            'organization_id' => Organization::factory()->create()->id,
            'plugin_id' => 'toggl',
            'enabled' => true,
            'settings' => [],
        ]);
    }

    public function test_successful_run_is_recorded_with_state_aggregation(): void {
        $task = $this->makeEvent('toggl:import');

        $this->recorder()->handleStarting(new ScheduledTaskStarting($task));

        $this->assertDatabaseHas('scheduled_job_runs', [
            'job_key' => 'toggl.import',
            'status' => ScheduledJobRun::STATUS_RUNNING,
        ]);

        $task->exitCode = 0;
        $this->recorder()->handleFinished(new ScheduledTaskFinished($task, 1.5));

        $run = ScheduledJobRun::query()->where('job_key', 'toggl.import')->latest('id')->firstOrFail();
        $this->assertSame(ScheduledJobRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(1500, $run->duration_ms);
        $this->assertSame(0, $run->exit_code);

        $state = ScheduledJobState::query()->where('job_key', 'toggl.import')->firstOrFail();
        $this->assertSame(ScheduledJobRun::STATUS_SUCCESS, $state->last_status);
        $this->assertNotNull($state->last_success_at);
        $this->assertSame(0, $state->consecutive_failures);
    }

    public function test_failed_run_increments_consecutive_failures_and_success_resets(): void {
        $task = $this->makeEvent('toggl:import');

        for ($i = 0; $i < 2; $i++) {
            $this->recorder()->handleStarting(new ScheduledTaskStarting($task));
            $task->exitCode = 1;
            $this->recorder()->handleFinished(new ScheduledTaskFinished($task, 0.2));
        }

        $state = ScheduledJobState::query()->where('job_key', 'toggl.import')->firstOrFail();
        $this->assertSame(2, $state->consecutive_failures);
        $this->assertNotNull($state->last_failure_at);

        $this->recorder()->handleStarting(new ScheduledTaskStarting($task));
        $task->exitCode = 0;
        $this->recorder()->handleFinished(new ScheduledTaskFinished($task, 0.2));

        $this->assertSame(0, ScheduledJobState::query()->where('job_key', 'toggl.import')->firstOrFail()->consecutive_failures);
    }

    public function test_unknown_commands_are_ignored(): void {
        $task = $this->makeEvent('irgendwas:fremdes');

        $this->recorder()->handleStarting(new ScheduledTaskStarting($task));

        $this->assertDatabaseCount('scheduled_job_runs', 0);
    }

    public function test_watchdog_flags_overdue_job_once_per_due_run(): void {
        $this->enableTogglPlugin();
        // Zeit einfrieren: 45 min nach der vollen Stunde — der Hourly-Soll-
        // Lauf (:00) ist damit sicher jenseits von Laufzeit+Grace (30 min).
        $this->travelTo(now()->startOfHour()->addMinutes(45));
        // Hourly-Job, letzter Erfolg vor 3 Stunden, danach gestartet aber
        // nie wieder erfolgreich → überfällig.
        ScheduledJobState::query()->create([
            'job_key' => 'toggl.import',
            'last_started_at' => CarbonImmutable::now()->subHours(3),
            'last_success_at' => CarbonImmutable::now()->subHours(3),
            'last_status' => ScheduledJobRun::STATUS_FAILED,
        ]);

        $this->assertSame(1, Artisan::call('scheduler:watchdog', ['--fail' => true]));

        $state = ScheduledJobState::query()->where('job_key', 'toggl.import')->firstOrFail();
        $this->assertNotNull($state->overdue_notified_at);

        // Zweiter Lauf im selben Soll-Fenster: Dedup, kein erneuter Befund.
        $this->assertSame(0, Artisan::call('scheduler:watchdog', ['--fail' => true]));
    }

    public function test_watchdog_ignores_fresh_and_never_started_jobs(): void {
        $this->enableTogglPlugin();
        // Frischer Erfolg → kein Befund; nie gestartete Jobs → kein Befund.
        ScheduledJobState::query()->create([
            'job_key' => 'toggl.import',
            'last_success_at' => CarbonImmutable::now(),
            'last_status' => ScheduledJobRun::STATUS_SUCCESS,
        ]);

        $this->assertSame(0, Artisan::call('scheduler:watchdog', ['--fail' => true]));
    }

    public function test_watchdog_skips_paused_jobs_and_purges_old_runs(): void {
        $this->enableTogglPlugin();
        ScheduledJobState::query()->create([
            'job_key' => 'toggl.import',
            'last_started_at' => CarbonImmutable::now()->subHours(5),
            'last_success_at' => CarbonImmutable::now()->subHours(5),
        ]);
        app(\App\Scheduling\SchedulerOverrideService::class)->pause('toggl.import');

        ScheduledJobRun::query()->create([
            'job_key' => 'toggl.import',
            'started_at' => CarbonImmutable::now()->subDays(60),
            'status' => ScheduledJobRun::STATUS_SUCCESS,
        ]);

        $this->assertSame(0, Artisan::call('scheduler:watchdog', ['--fail' => true]));
        $this->assertDatabaseCount('scheduled_job_runs', 0);
    }

    public function test_watchdog_skips_jobs_of_inactive_plugins(): void {
        // Kein plugin_settings-Eintrag: toggl ist nirgends aktiviert. Der
        // eigentlich überfällige Sync darf dann weder alarmieren noch den
        // Dedup-Marker setzen — sonst meldet der Wächter Anbindungen, die
        // bewusst nicht laufen.
        $this->travelTo(now()->startOfHour()->addMinutes(45));
        ScheduledJobState::query()->create([
            'job_key' => 'toggl.import',
            'last_started_at' => CarbonImmutable::now()->subHours(3),
            'last_success_at' => CarbonImmutable::now()->subHours(3),
            'last_status' => ScheduledJobRun::STATUS_FAILED,
        ]);

        $this->assertSame(0, Artisan::call('scheduler:watchdog', ['--fail' => true]));

        $state = ScheduledJobState::query()->where('job_key', 'toggl.import')->firstOrFail();
        $this->assertNull($state->overdue_notified_at);
        $this->assertDatabaseCount('operations_tasks', 0);

        // Sobald das Plugin irgendwo aktiv ist, greift die Überwachung wieder.
        $this->enableTogglPlugin();
        $this->assertSame(1, Artisan::call('scheduler:watchdog', ['--fail' => true]));
    }
}
