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
use App\Models\ScheduledJobOverride;
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

    /**
     * Vollscan 2026-08-23, J8: Lange Läufe (archive:run, 30 min) blockierten die
     * minütlichen Jobs desselben Ticks. Im Hintergrund feuert
     * ScheduledTaskFinished schon beim Start — das Ergebnis kommt über
     * ScheduledBackgroundTaskFinished (schedule:finish).
     */
    public function test_background_run_is_closed_by_the_background_finished_event(): void {
        $task = $this->makeEvent('archive:run')->runInBackground();

        $this->recorder()->handleStarting(new ScheduledTaskStarting($task));
        $this->recorder()->handleFinished(new ScheduledTaskFinished($task, 0.01));

        $this->assertDatabaseHas('scheduled_job_runs', ['job_key' => 'archive.run', 'status' => ScheduledJobRun::STATUS_RUNNING]);

        $task->exitCode = 0;
        $this->recorder()->handleBackgroundFinished(new \Illuminate\Console\Events\ScheduledBackgroundTaskFinished($task));

        $run = ScheduledJobRun::query()->where('job_key', 'archive.run')->latest('id')->firstOrFail();
        $this->assertSame(ScheduledJobRun::STATUS_SUCCESS, $run->status);
        $this->assertNotNull($run->duration_ms);
    }

    public function test_long_running_registry_jobs_are_scheduled_in_the_background(): void {
        $schedule = new Schedule;
        app(\App\Scheduling\SchedulerRegistrar::class)->register($schedule);

        $byCommand = [];
        foreach ($schedule->events() as $event) {
            if (is_string($event->command)) {
                $byCommand[trim(\Illuminate\Support\Str::after($event->command, "'artisan' "))] = $event->runInBackground;
            }
        }

        $this->assertTrue($byCommand['archive:run'] ?? null, 'archive:run (30 min) muss im Hintergrund laufen');
        $this->assertFalse($byCommand['scheduler:watchdog'] ?? null, 'kurze Jobs bleiben im Vordergrund');
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

    /**
     * Ein dailyAt-Override „22:10" gilt in der Zeitplan-Zeitzone (Europe/Berlin).
     * Um 23:30 Ortszeit ist er fällig gewesen — in UTC gerechnet läge er noch
     * 40 Minuten in der Zukunft und der Ausfall bliebe unsichtbar.
     */
    public function test_watchdog_evaluates_due_times_in_the_schedule_timezone(): void {
        config(['app.schedule_timezone' => 'Europe/Berlin']);
        // 2026-09-02 21:30 UTC = 23:30 Europe/Berlin (Sommerzeit).
        $this->travelTo(CarbonImmutable::parse('2026-09-02 21:30:00', 'UTC'));

        ScheduledJobOverride::query()->create([
            'job_key' => 'plans.purge',
            'organization_id' => null,
            'enabled' => true,
            'cadence' => ['type' => 'dailyAt', 'time' => '22:10'],
        ]);
        // Letzter Erfolg heute 10:30 UTC: nach dem gestrigen, vor dem heutigen Soll-Lauf.
        ScheduledJobState::query()->create([
            'job_key' => 'plans.purge',
            'last_started_at' => CarbonImmutable::now()->subHours(11),
            'last_success_at' => CarbonImmutable::now()->subHours(11),
            'last_status' => ScheduledJobRun::STATUS_SUCCESS,
        ]);

        $this->assertSame(1, Artisan::call('scheduler:watchdog', ['--fail' => true]), '22:10 Ortszeit (20:10 UTC) ist um 23:30 Ortszeit überfällig');
        $notified = ScheduledJobState::query()->where('job_key', 'plans.purge')->firstOrFail()->overdue_notified_at;
        $this->assertNotNull($notified);
        $this->assertSame('2026-09-02 21:30:00', $notified->utc()->format('Y-m-d H:i:s'), 'in UTC gespeichert, nicht als Ortszeit-String');

        // Gegenprobe: in UTC gerechnet wäre der heutige Soll-Lauf 22:10 UTC noch nicht erreicht.
        ScheduledJobState::query()->where('job_key', 'plans.purge')->update(['overdue_notified_at' => null]);
        config(['app.schedule_timezone' => 'UTC']);
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

    /**
     * Der Wächter urteilt anhand von last_success_at. Hintergrundjobs melden
     * ihren Erfolg erst über schedule:finish — ein verspäteter Tick startet
     * sie also erst jenseits der Frist. Solange der Lauf des aktuellen
     * Soll-Slots noch läuft, ist ein Befund ein Fehlalarm.
     */
    public function test_watchdog_ignores_a_running_job_of_the_current_due_slot(): void {
        $this->enableTogglPlugin();
        $this->travelTo(now()->startOfHour()->addMinutes(45));
        ScheduledJobState::query()->create([
            'job_key' => 'toggl.import',
            'last_started_at' => CarbonImmutable::now()->startOfHour(),
            'last_success_at' => CarbonImmutable::now()->subHours(3),
            'last_status' => ScheduledJobRun::STATUS_RUNNING,
        ]);
        ScheduledJobRun::query()->create([
            'job_key' => 'toggl.import',
            'started_at' => CarbonImmutable::now()->startOfHour(),
            'status' => ScheduledJobRun::STATUS_RUNNING,
        ]);

        $this->assertSame(0, Artisan::call('scheduler:watchdog', ['--fail' => true]));
        $this->assertNull(ScheduledJobState::query()->where('job_key', 'toggl.import')->firstOrFail()->overdue_notified_at);

        // Ein hängender Lauf aus einem älteren Soll-Slot schützt bewusst nicht.
        ScheduledJobRun::query()->where('job_key', 'toggl.import')
            ->update(['started_at' => CarbonImmutable::now()->subHours(3)]);

        $this->assertSame(1, Artisan::call('scheduler:watchdog', ['--fail' => true]));
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
