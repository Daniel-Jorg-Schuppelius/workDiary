<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TargetDaysTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Sickness\SickLeaveKind;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\{SickLeave, Vacation};
use App\Models\Platform\{Holiday, User};
use App\Models\Time\{Attendance, WorkSchedule};
use App\Services\Flextime\FlexCalculator;
use App\Services\Reporting\PlanIstReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Soll wie in der Arbeitsbilanz (MVP-1092): Feiertage und genehmigte
 * Abwesenheit haben kein Soll; stornierte Stempelungen zählen nicht.
 */
class TargetDaysTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $worker;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->worker = $this->orgUser(['name' => 'Willi Worker']);

        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->worker->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'core_start' => '09:00',
            'core_end' => '15:00',
            'valid_from' => '2030-01-01',
        ]);
        // Woche Mo 11.03.–Fr 15.03.2030: Di Feiertag, Do Urlaub.
        Holiday::query()->create([
            'organization_id' => $this->organization->id,
            'date' => '2030-03-12',
            'name' => 'Testfeiertag',
            'is_recurring' => false,
            'recurrence_type' => 'fixed',
        ]);
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->worker->id,
            'start_date' => '2030-03-14',
            'end_date' => '2030-03-14',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);
    }

    public function test_attendance_report_target_skips_holidays_and_vacation(): void {
        $rows = $this->actingAs($this->worker)
            ->withSession($this->dateRangeSession('2030-03-11', '2030-03-15'))
            ->get(route('reports.attendance'))
            ->assertOk()
            ->viewData('rows');

        $row = collect($rows)->firstWhere('user.id', $this->worker->id);
        $this->assertNotNull($row);
        $this->assertSame(3, $row['workdays']);
        $this->assertSame(3 * 480, $row['target_minutes']);
    }

    /** MVP-1098: Krankheit mindert das Soll wie Urlaub; stornierte Krankmeldungen nicht. */
    public function test_sick_days_have_no_target_unless_cancelled(): void {
        SickLeave::create(['organization_id' => $this->organization->id, 'user_id' => $this->worker->id, 'start_date' => '2030-03-13', 'end_date' => '2030-03-13', 'kind' => SickLeaveKind::Initial->value]);
        SickLeave::create(['organization_id' => $this->organization->id, 'user_id' => $this->worker->id, 'start_date' => '2030-03-15', 'end_date' => '2030-03-15', 'kind' => SickLeaveKind::Initial->value, 'cancelled_at' => now()]);
        $flex = app(FlexCalculator::class);

        $this->assertSame(0, $flex->targetMinutes($this->worker, CarbonImmutable::parse('2030-03-13')));
        $this->assertSame(480, $flex->targetMinutes($this->worker, CarbonImmutable::parse('2030-03-15')));
        $days = array_keys($flex->daysWithoutTarget([(int) $this->worker->id], CarbonImmutable::parse('2030-03-11'), CarbonImmutable::parse('2030-03-15'))[(int) $this->worker->id]);
        sort($days);
        $this->assertSame(['2030-03-12', '2030-03-13', '2030-03-14'], $days);
    }

    public function test_plan_ist_presence_has_no_plan_on_days_off_and_ignores_cancelled_stamps(): void {
        Attendance::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->worker->id,
            'date' => '2030-03-11',
            'started_at' => '2030-03-11 05:00:00',
            'ended_at' => '2030-03-11 05:00:00',
            'status' => AttendanceStatus::Cancelled->value,
        ]);

        $rows = collect(app(PlanIstReportBuilder::class)->presenceFor($this->worker, CarbonImmutable::parse('2030-03-11'), CarbonImmutable::parse('2030-03-15')))->keyBy('date');

        $this->assertSame(0, $rows['2030-03-12']['plan_minutes']);
        $this->assertTrue($rows['2030-03-12']['no_plan']);
        $this->assertTrue($rows['2030-03-14']['no_plan']);
        $this->assertSame(480, $rows['2030-03-13']['plan_minutes']);
        $this->assertNull($rows['2030-03-11']['actual_start'], 'Stornierte Stempelung liefert keinen Arbeitsbeginn.');
    }
}
