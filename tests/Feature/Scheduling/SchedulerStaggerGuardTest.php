<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerStaggerGuardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Enums\Scheduling\JobRunStatus;
use App\Models\Platform\{PluginSetting, ScheduledJobRun, ScheduledJobState};
use App\Scheduling\{Cadence, CadenceType, JobOffset, JobRegistry, PluginBinding, SchedulerRegistrar};
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Leistungsanalyse 2026-11-02 (perf-01, perf-02, perf-04): Versatz der Takt-
 * Kadenzen, kein Prozess für Jobs nie konfigurierter Plugins, Vorprüfungen
 * im schedule:run-Prozess, Wächter schließt verwaiste Läufe und meldet
 * Fehlerserien.
 */
final class SchedulerStaggerGuardTest extends TestCase {
    use RefreshDatabase;

    public function test_offsets_stay_inside_their_slot_and_avoid_the_full_hour(): void {
        foreach (app(JobRegistry::class)->all() as $key => $definition) {
            foreach ([CadenceType::Hourly, CadenceType::EveryFifteenMinutes, CadenceType::EveryFiveMinutes, CadenceType::EveryThirtyMinutes] as $type) {
                $offset = JobOffset::minutes($key, $type);
                $this->assertGreaterThanOrEqual(1, $offset, "{$key}/{$type->value}");
                $this->assertLessThan($type->intervalMinutes(), $offset, "{$key}/{$type->value}");
                $this->assertSame($offset, JobOffset::minutes($key, $type), 'deterministisch');
            }
            $this->assertSame(0, JobOffset::minutes($key, CadenceType::DailyAt));
            $this->assertSame(0, JobOffset::minutes($key, CadenceType::EveryMinute));
        }
    }

    public function test_cron_expressions_carry_the_offset(): void {
        $this->assertSame('17 * * * *', (new Cadence(CadenceType::Hourly))->withOffset(17)->cronExpression());
        $this->assertSame('3,18,33,48 * * * *', (new Cadence(CadenceType::EveryFifteenMinutes))->withOffset(3)->cronExpression());
        $this->assertSame('2,7,12,17,22,27,32,37,42,47,52,57 * * * *', (new Cadence(CadenceType::EveryFiveMinutes))->withOffset(2)->cronExpression());
        $this->assertSame('*/30 * * * *', (new Cadence(CadenceType::EveryThirtyMinutes))->cronExpression());
        $this->assertSame([], array_diff_key((new Cadence(CadenceType::Hourly))->withOffset(5)->toArray(), ['type' => 1]), 'Versatz wird nicht gespeichert');
    }

    public function test_hourly_jobs_are_spread_and_the_watchdog_stays_on_the_full_hour(): void {
        $minutes = [];
        foreach ($this->registeredExpressions() as $command => $expression) {
            if (preg_match('/^(\d+) \* \* \* \*$/', $expression, $m) === 1) {
                $minutes[$command] = (int) $m[1];
            }
        }
        $this->assertSame(0, $minutes['scheduler:watchdog']);
        unset($minutes['scheduler:watchdog']);
        $this->assertGreaterThan(20, count($minutes), 'stündliche Jobs');
        $this->assertNotContains(0, $minutes, 'kein stündlicher Job mehr auf der vollen Stunde');
        $this->assertGreaterThan(10, count(array_unique($minutes)), 'auf viele Minuten verteilt');
        $this->assertLessThanOrEqual(5, max(array_count_values($minutes)), 'höchstens fünf Jobs je Minute');
    }

    public function test_jobs_of_inactive_plugins_get_no_process(): void {
        config(['scheduler.skip_inactive_plugins' => true]);
        PluginBinding::reset();

        $this->assertArrayNotHasKey('toggl:import', $this->registeredExpressions());
        $this->assertArrayHasKey('chat:send-reminders', $this->registeredExpressions(), 'ohne Bindung immer');

        PluginSetting::query()->create(['organization_id' => \App\Models\Platform\Organization::factory()->create()->id, 'plugin_id' => 'toggl', 'enabled' => true, 'settings' => []]);
        PluginBinding::reset();

        $this->assertArrayHasKey('toggl:import', $this->registeredExpressions());
    }

    /** `migrate:fresh` auf leerer Datenbank registriert den Scheduler mit — die Bindung darf dann nicht abfragen müssen (Befund E2E 2026-11-02). */
    public function test_plugin_binding_fails_open_without_the_settings_table(): void {
        config(['scheduler.skip_inactive_plugins' => true]);
        $default = config('database.default');
        config(['database.connections.scheduler_empty' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        PluginBinding::reset();

        try {
            config(['database.default' => 'scheduler_empty']);
            $this->assertTrue(PluginBinding::active('toggl'));
            $this->assertTrue(PluginBinding::active('*'));
            $this->assertArrayHasKey('toggl:import', $this->registeredExpressions(), 'ohne Tabelle wie vor der Vorprüfung angemeldet');
        } finally {
            config(['database.default' => $default]);
            \Illuminate\Support\Facades\DB::purge('scheduler_empty');
            PluginBinding::reset();
        }
    }

    public function test_guard_decides_whether_a_job_gets_a_process(): void {
        $schedule = new Schedule;
        app(SchedulerRegistrar::class)->register($schedule);
        $event = null;
        foreach ($schedule->events() as $candidate) {
            if (is_string($candidate->command) && Str::endsWith($candidate->command, 'security:evaluate')) {
                $event = $candidate;
            }
        }
        $this->assertNotNull($event);
        $this->assertFalse($event->filtersPass(app()), 'ohne Sicherheitsereignis kein Prozess');

        \App\Models\Auth\SecurityEvent::query()->create([
            'event' => \App\Enums\Security\SecurityEventType::AuthFailed,
            'ip' => '203.0.113.5',
            'occurred_at' => CarbonImmutable::now()->subMinute(),
        ]);
        $this->assertTrue($event->filtersPass(app()));
    }

    public function test_watchdog_treats_a_skipped_slot_as_fulfilled(): void {
        config(['scheduler.skip_inactive_plugins' => false]);
        $slot = CarbonImmutable::now()->startOfHour()->addMinutes(JobOffset::minutes('operations.scan', CadenceType::Hourly));
        $this->travelTo($slot->addMinutes(50));
        ScheduledJobState::query()->create([
            'job_key' => 'operations.scan',
            'last_started_at' => $slot->subHours(3),
            'last_success_at' => $slot->subHours(3),
            'last_status' => JobRunStatus::Success,
        ]);
        ScheduledJobRun::query()->create(['job_key' => 'operations.scan', 'started_at' => $slot, 'finished_at' => $slot, 'status' => JobRunStatus::Skipped]);

        $this->assertSame(0, Artisan::call('scheduler:watchdog', ['--fail' => true]));
        $this->assertNull(ScheduledJobState::query()->where('job_key', 'operations.scan')->firstOrFail()->overdue_notified_at);
    }

    public function test_watchdog_closes_abandoned_runs(): void {
        $started = CarbonImmutable::now()->subHours(5);
        $run = ScheduledJobRun::query()->create(['job_key' => 'operations.scan', 'started_at' => $started, 'status' => JobRunStatus::Running]);
        $fresh = ScheduledJobRun::query()->create(['job_key' => 'operations.scan', 'started_at' => CarbonImmutable::now()->subMinutes(2), 'status' => JobRunStatus::Running]);

        Artisan::call('scheduler:watchdog');

        $this->assertSame(JobRunStatus::Failed, $run->fresh()?->status);
        $this->assertNotNull($run->fresh()?->finished_at);
        $this->assertSame(JobRunStatus::Running, $fresh->fresh()?->status, 'ein junger Lauf bleibt');
    }

    public function test_watchdog_reports_a_failure_streak_once_per_streak_length(): void {
        config(['scheduler.skip_inactive_plugins' => false]);
        \App\Models\Platform\Organization::factory()->create();
        ScheduledJobState::query()->create([
            'job_key' => 'operations.scan',
            'last_started_at' => CarbonImmutable::now()->subMinutes(5),
            'last_success_at' => CarbonImmutable::now()->subMinutes(5),
            'last_status' => JobRunStatus::Failed,
            'consecutive_failures' => 3,
        ]);

        Artisan::call('scheduler:watchdog');
        Artisan::call('scheduler:watchdog');

        $this->assertDatabaseCount('operations_tasks', 1);
        $this->assertDatabaseHas('operations_tasks', ['type' => 'scheduler_failing']);
    }

    /** @return array<string, string> Kommando → Cron-Ausdruck */
    private function registeredExpressions(): array {
        $schedule = new Schedule;
        app(SchedulerRegistrar::class)->register($schedule);
        $out = [];
        foreach ($schedule->events() as $event) {
            if (is_string($event->command) && $event->command !== '') {
                $out[trim(Str::after($event->command, "'artisan' "))] = $event->expression;
            }
        }

        return $out;
    }
}
