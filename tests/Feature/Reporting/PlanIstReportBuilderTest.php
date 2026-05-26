<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanIstReportBuilderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\{Attendance, User, WorkSchedule};
use App\Services\Reporting\PlanIstReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class PlanIstReportBuilderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private PlanIstReportBuilder $builder;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->builder = app(PlanIstReportBuilder::class);
    }

    public function test_presence_calculates_plan_actual_and_warnings(): void {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        WorkSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'weekly_minutes' => 2400, // 40h
            'daily_target_minutes' => 480, // 8h
            'working_days' => [1, 2, 3, 4, 5],
            'core_start' => '08:00:00',
            'core_end' => '16:30:00',
            'frame_start' => '06:00:00',
            'frame_end' => '20:00:00',
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2024-01-01',
            'valid_to' => null,
        ]);

        // Mo 15.01.2024 — ISO weekday 1, working day.
        // Stempelung 08:25 (Δ +25 min) … 16:30, brutto 8:05, brutto>6h → 30 min Pause → netto 7:35 = 455 min.
        Attendance::withoutEvents(function () use ($user) {
            Attendance::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $user->id,
                'date' => '2024-01-15',
                'started_at' => '2024-01-15 08:25:00',
                'ended_at' => '2024-01-15 16:30:00',
                'duration_minutes' => 455,
            ]);
        });

        $rows = $this->builder->presenceFor(
            $user,
            CarbonImmutable::create(2024, 1, 15) ?? CarbonImmutable::now(),
            CarbonImmutable::create(2024, 1, 15) ?? CarbonImmutable::now(),
        );

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(480, $row['plan_minutes']);
        $this->assertGreaterThan(0, $row['actual_minutes']);
        $this->assertSame(25, $row['late_start_minutes']);
        $this->assertContains('presence.lateStart', $row['warnings']);
        $this->assertFalse($row['no_plan']);
    }

    public function test_presence_marks_days_outside_schedule_as_no_plan(): void {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        WorkSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'core_start' => '08:00:00',
            'core_end' => '16:30:00',
            'frame_start' => '06:00:00',
            'frame_end' => '20:00:00',
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2024-01-01',
            'valid_to' => null,
        ]);

        // 2024-01-13 ist ein Samstag → ISO 6 → not working day.
        $rows = $this->builder->presenceFor(
            $user,
            CarbonImmutable::create(2024, 1, 13) ?? CarbonImmutable::now(),
            CarbonImmutable::create(2024, 1, 13) ?? CarbonImmutable::now(),
        );

        $this->assertTrue($rows[0]['no_plan']);
        $this->assertSame(0, $rows[0]['plan_minutes']);
        $this->assertEmpty($rows[0]['warnings']);
    }
}
