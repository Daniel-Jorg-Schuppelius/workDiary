<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerOverrideTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Scheduling;

use App\Models\Audit\AuditLog;
use App\Models\Platform\ScheduledJobOverride;
use App\Scheduling\{Cadence, CadenceType, JobOffset, SchedulerOverrideService, SchedulerRegistrar};
use App\Support\MorphMap;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class SchedulerOverrideTest extends TestCase {
    use RefreshDatabase;

    private SchedulerOverrideService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = app(SchedulerOverrideService::class);
    }

    /** @return array<string, string> command => cron expression */
    private function registeredExpressions(): array {
        $schedule = new Schedule;
        app(SchedulerRegistrar::class)->register($schedule);

        $map = [];
        foreach ($schedule->events() as $event) {
            if (is_string($event->command) && $event->command !== '') {
                $map[trim(Str::after($event->command, "'artisan' "))] = $event->expression;
            }
        }

        return $map;
    }

    public function test_pause_removes_job_from_schedule_and_resume_restores_it(): void {
        $this->assertArrayHasKey('toggl:import', $this->registeredExpressions());

        $this->service->pause('toggl.import');
        $this->assertArrayNotHasKey('toggl:import', $this->registeredExpressions());

        $this->service->resume('toggl.import');
        $this->assertArrayHasKey('toggl:import', $this->registeredExpressions());
        // Ohne Kadenz-Override wird die Zeile komplett entfernt (Default gilt).
        $this->assertDatabaseCount('scheduled_job_overrides', 0);
    }

    public function test_reschedule_within_allowed_cadences_takes_effect(): void {
        $this->service->reschedule('toggl.import', new Cadence(CadenceType::EveryFifteenMinutes));

        $this->assertSame($this->withOffset(CadenceType::EveryFifteenMinutes), $this->registeredExpressions()['toggl:import']);
    }

    public function test_reschedule_rejects_disallowed_cadence(): void {
        $this->expectException(InvalidArgumentException::class);
        // toggl.import erlaubt kein everyMinute (Allowlist-Grenze).
        $this->service->reschedule('toggl.import', new Cadence(CadenceType::EveryMinute));
    }

    public function test_reschedule_rejects_invalid_cron_expression(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->service->reschedule('payroll.import_minimum_wages', new Cadence(CadenceType::Cron, expression: 'kein cron'));
    }

    public function test_reschedule_rejects_unknown_job(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->service->reschedule('boese.freie-kommandos', new Cadence(CadenceType::Hourly));
    }

    public function test_disallowed_override_in_db_falls_back_to_default(): void {
        // Direkt in die DB geschriebener, nicht (mehr) erlaubter Override —
        // der Registrar wendet ihn NICHT an, sondern nutzt den Default.
        ScheduledJobOverride::query()->create([
            'job_key' => 'toggl.import',
            'organization_id' => null,
            'enabled' => true,
            'cadence' => ['type' => 'everyMinute'],
        ]);

        $this->assertSame($this->withOffset(CadenceType::Hourly), $this->registeredExpressions()['toggl:import']);
    }

    public function test_reset_restores_default(): void {
        $this->service->reschedule('toggl.import', new Cadence(CadenceType::EveryFifteenMinutes));
        $this->service->reset('toggl.import');

        $this->assertSame($this->withOffset(CadenceType::Hourly), $this->registeredExpressions()['toggl:import']);
        $this->assertDatabaseCount('scheduled_job_overrides', 0);
    }

    public function test_override_changes_are_audited(): void {
        $this->service->pause('toggl.import');

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_type', MorphMap::stableKey(ScheduledJobOverride::class))
                ->where('event', 'created')
                ->exists(),
        );
    }

    /** MVP-1619 (N-09): Zeitzone, Ruhezeit und Wiederholung je Job. */
    public function test_run_options_apply_timezone_quiet_window_and_retry_event(): void {
        $this->service->configureRun('toggl.import', 'America/New_York', '22:00', '06:00', 2, 15);

        $schedule = new Schedule;
        app(SchedulerRegistrar::class)->register($schedule);
        $events = collect($schedule->events())->filter(static fn ($e): bool => is_string($e->command) && str_contains($e->command, 'toggl:import'));

        $this->assertCount(2, $events, 'Regulärer Lauf und Wiederholung');
        $this->assertTrue($events->every(static fn ($e): bool => $e->timezone === 'America/New_York'));
        $retry = $events->first(static fn ($e): bool => $e->description === 'toggl.import:retry');
        $this->assertNotNull($retry);
        $this->assertSame('* * * * *', $retry->expression);

        // Ohne fällige Wiederholung bleibt der Wiederholungslauf aus.
        $this->assertFalse($retry->filtersPass(app()));
        \App\Scheduling\JobRetry::afterFailure('toggl.import', 2, 15);
        $this->travel(16)->minutes();
        $this->assertTrue(\App\Scheduling\JobRetry::isDue('toggl.import'));
        \App\Scheduling\JobRetry::afterRetryFailure('toggl.import', 2, 15);
        $this->assertSame(2, \App\Scheduling\JobRetry::attempt('toggl.import'));
        \App\Scheduling\JobRetry::afterRetryFailure('toggl.import', 2, 15);
        $this->assertSame(0, \App\Scheduling\JobRetry::attempt('toggl.import'), 'Nach dem letzten Versuch wird aufgegeben');
    }

    public function test_quiet_window_shifts_daily_time_and_replaces_the_installation_window(): void {
        $this->service->reschedule('toggl.import', new Cadence(CadenceType::DailyAt, '23:30'));
        $this->service->configureRun('toggl.import', null, '22:00', '06:00', 0, null);

        // 23:30 liegt in der Ruhezeit und rückt hinter ihr Ende (06:xx), nicht ins Betriebsfenster der Installation.
        $this->assertMatchesRegularExpression('/^\d+ 6 \* \* \*$/', $this->registeredExpressions()['toggl:import']);
    }

    public function test_configure_run_rejects_unknown_timezone_and_half_quiet_window(): void {
        try {
            $this->service->configureRun('toggl.import', 'Mars/Olympus', null, null, 0, null);
            $this->fail('Unbekannte Zeitzone wurde angenommen.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $this->service->configureRun('toggl.import', null, '22:00', null, 0, null);
    }

    public function test_resume_keeps_override_with_run_options(): void {
        $this->service->configureRun('toggl.import', null, null, null, 1, 5);
        $this->service->pause('toggl.import');
        $this->service->resume('toggl.import');

        $this->assertDatabaseHas('scheduled_job_overrides', ['job_key' => 'toggl.import', 'enabled' => true, 'retry_attempts' => 1]);
    }

    /** Erwarteter Ausdruck des Toggl-Imports samt Minutenversatz (perf-01). */
    private function withOffset(CadenceType $type): string {
        return (new Cadence($type))->withOffset(JobOffset::minutes('toggl.import', $type))->cronExpression();
    }
}
