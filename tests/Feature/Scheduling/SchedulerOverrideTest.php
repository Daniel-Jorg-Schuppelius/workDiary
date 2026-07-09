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

use App\Models\{AuditLog, ScheduledJobOverride};
use App\Scheduling\{Cadence, CadenceType, SchedulerOverrideService, SchedulerRegistrar};
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

        $this->assertSame('*/15 * * * *', $this->registeredExpressions()['toggl:import']);
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

        $this->assertSame('0 * * * *', $this->registeredExpressions()['toggl:import']);
    }

    public function test_reset_restores_default(): void {
        $this->service->reschedule('toggl.import', new Cadence(CadenceType::EveryFifteenMinutes));
        $this->service->reset('toggl.import');

        $this->assertSame('0 * * * *', $this->registeredExpressions()['toggl:import']);
        $this->assertDatabaseCount('scheduled_job_overrides', 0);
    }

    public function test_override_changes_are_audited(): void {
        $this->service->pause('toggl.import');

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_type', ScheduledJobOverride::class)
                ->where('event', 'created')
                ->exists(),
        );
    }
}
