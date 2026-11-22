<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexCalculatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Flextime;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\{SickLeave, Vacation};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\{Attendance, TimeEntry, WorkSchedule};
use App\Services\Flextime\FlexCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class FlexCalculatorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Flex',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'frame_start' => '06:00',
            'frame_end' => '20:00',
            'core_start' => '09:00',
            'core_end' => '15:00',
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2030-01-01',
        ]);
    }

    public function test_overtime_is_positive_balance(): void {
        // 2030-01-02 = Mittwoch
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-01-02',
            'minutes' => 540, // 9h
            'kind' => TimeEntryKind::Work->value,
        ]);

        $calc = app(FlexCalculator::class);
        $b = $calc->dailyBalance($this->user, CarbonImmutable::parse('2030-01-02'));

        $this->assertSame(480, $b['target']);
        $this->assertSame(540, $b['actual']);
        $this->assertSame(60, $b['balance']);
    }

    public function test_weekend_target_is_zero(): void {
        // 2030-01-05 = Samstag
        $calc = app(FlexCalculator::class);
        $b = $calc->dailyBalance($this->user, CarbonImmutable::parse('2030-01-05'));
        $this->assertSame(0, $b['target']);
        $this->assertSame(0, $b['actual']);
    }

    public function test_monthly_balance_aggregates(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-03-04', // Montag
            'minutes' => 480,
            'kind' => TimeEntryKind::Work->value,
        ]);
        $calc = app(FlexCalculator::class);
        $m = $calc->monthlyBalance($this->user, 2030, 3);
        $this->assertSame(480, $m['days']['2030-03-04']['actual']);
        $this->assertGreaterThan(0, $m['target']);
    }

    /** Das Zeitraum-Vorladen muss je Tag dasselbe liefern wie die Einzelabfragen. */
    public function test_batched_window_matches_single_day_queries(): void {
        $org = $this->organization->id;
        $uid = $this->user->id;
        WorkSchedule::query()->where('user_id', $uid)->firstOrFail()->update(['valid_to' => '2030-03-15']);
        WorkSchedule::create([
            'organization_id' => $org, 'user_id' => $uid, 'weekly_minutes' => 1800, 'daily_target_minutes' => 360,
            'working_days' => [1, 2, 3, 4, 5], 'frame_start' => '06:00', 'frame_end' => '20:00', 'core_start' => '09:00',
            'core_end' => '15:00', 'break_after_minutes' => 360, 'break_minutes' => 30, 'valid_from' => '2030-03-16',
        ]);
        $vacation = fn (string $from, string $to, VacationStatus $status) => Vacation::create([
            'organization_id' => $org, 'user_id' => $uid, 'start_date' => $from, 'end_date' => $to,
            'type' => VacationType::Vacation->value, 'status' => $status->value,
        ]);
        $vacation('2030-02-27', '2030-03-01', VacationStatus::Approved);
        $vacation('2030-03-11', '2030-03-12', VacationStatus::Approved);
        $vacation('2030-03-13', '2030-03-13', VacationStatus::Pending);
        SickLeave::factory()->create(['organization_id' => $org, 'user_id' => $uid, 'start_date' => '2030-03-20', 'end_date' => '2030-03-21']);
        SickLeave::factory()->create(['organization_id' => $org, 'user_id' => $uid, 'start_date' => '2030-03-25', 'end_date' => '2030-03-25', 'cancelled_at' => now()]);
        $attendance = Attendance::factory()->create([
            'organization_id' => $org, 'user_id' => $uid, 'date' => '2030-03-05',
            'started_at' => '2030-03-05 08:00:00', 'ended_at' => '2030-03-05 13:00:00',
        ]);
        $entry = fn (string $date, int $minutes, array $extra = []) => TimeEntry::create([
            'organization_id' => $org, 'project_id' => $this->project->id, 'user_id' => $uid,
            'date' => $date, 'minutes' => $minutes, 'kind' => TimeEntryKind::Work->value,
        ] + $extra);
        $entry('2030-03-05', 120, ['attendance_id' => $attendance->id]);
        $entry('2030-03-05', 45);
        $entry('2030-03-18', 200);
        $entry('2030-03-18', 160);
        $entry('2030-03-19', 30, ['activity_type' => TimeEntryActivityType::Break_->value]);
        $entry('2030-03-31', 75);

        $from = CarbonImmutable::parse('2030-03-01');
        $to = CarbonImmutable::parse('2030-03-31');
        $perDay = function (FlexCalculator $calc) use ($from, $to): array {
            $days = [];
            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                $days[$day->toDateString()] = $calc->dailyBalance($this->user, $day);
            }

            return $days;
        };

        $single = $perDay(app(FlexCalculator::class));
        $calc = app(FlexCalculator::class);
        $batched = $calc->batched([$uid], $from, $to, fn (): array => $perDay($calc), withActuals: true);
        $targetsOnly = $calc->batched([$uid], $from, $to, fn (): array => $perDay($calc));

        $this->assertSame($single, $batched);
        $this->assertSame($single, $targetsOnly);
        $this->assertSame(0, $single['2030-03-01']['target']);
        $this->assertSame(480, $single['2030-03-13']['target']);
        $this->assertSame(0, $single['2030-03-20']['target']);
        $this->assertSame(360, $single['2030-03-25']['target']);
        $this->assertSame(345, $single['2030-03-05']['actual']);
        $this->assertSame(360, $single['2030-03-18']['actual']);
        $this->assertSame(0, $single['2030-03-19']['actual']);

        $month = $calc->monthlyBalance($this->user, 2030, 3);
        foreach ($single as $iso => $day) {
            $this->assertSame($day['target'], $month['days'][$iso]['target'], $iso);
            $this->assertSame($day['actual'], $month['days'][$iso]['actual'], $iso);
        }
        $this->assertTrue($month['days']['2030-03-11']['is_vacation']);
        $this->assertFalse($month['days']['2030-03-13']['is_vacation']);
    }
}
