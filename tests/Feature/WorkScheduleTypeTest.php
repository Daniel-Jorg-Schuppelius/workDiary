<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkScheduleTypeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\WorkSchedule\ScheduleType;
use App\Models\{User, WorkSchedule};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Speichern der verschiedenen Arbeitszeit-Typen über den Controller inkl.
 * serverseitiger day_targets-Berechnung.
 */
class WorkScheduleTypeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function hr(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    private function member(): User {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_saves_weekly_type_without_daily_target(): void {
        $member = $this->member();

        $this->actingAs($this->hr())
            ->put(route('users.work-schedule.update', $member), [
                'schedule_type' => 'weekly',
                'weekly_minutes' => 2000,
                'working_days' => [1, 2, 3, 4, 5],
                'break_after_minutes' => 360,
                'break_minutes' => 30,
                'valid_from' => '2026-01-01',
            ])->assertRedirect();

        $s = WorkSchedule::where('user_id', $member->id)->firstOrFail();
        $this->assertSame(ScheduleType::Weekly, $s->schedule_type);
        $this->assertSame(400, $s->targetMinutesForWeekday(1));
    }

    public function test_saves_per_weekday_with_hours_and_times(): void {
        $member = $this->member();

        $this->actingAs($this->hr())
            ->put(route('users.work-schedule.update', $member), [
                'schedule_type' => 'per_weekday',
                'day_targets' => [
                    1 => ['enabled' => '1', 'mode' => 'times', 'start' => '08:00', 'end' => '17:00', 'break' => '60'],
                    5 => ['enabled' => '1', 'mode' => 'hours', 'hours' => '6'],
                    6 => ['enabled' => '0', 'mode' => 'hours', 'hours' => '4'],
                ],
                'break_after_minutes' => 360,
                'break_minutes' => 30,
                'valid_from' => '2026-01-01',
            ])->assertRedirect();

        $s = WorkSchedule::where('user_id', $member->id)->firstOrFail();
        $this->assertSame(ScheduleType::PerWeekday, $s->schedule_type);
        $this->assertSame(480, $s->targetMinutesForWeekday(1)); // 09:00 - 60 Pause
        $this->assertSame(360, $s->targetMinutesForWeekday(5));
        $this->assertSame(0, $s->targetMinutesForWeekday(6));   // nicht aktiviert
        $this->assertSame(840, $s->weekly_minutes);             // Summe 480 + 360
    }

    public function test_saves_trust_type_with_zero_target(): void {
        $member = $this->member();

        $this->actingAs($this->hr())
            ->put(route('users.work-schedule.update', $member), [
                'schedule_type' => 'trust',
                'working_days' => [1, 2, 3, 4, 5],
                'break_after_minutes' => 360,
                'break_minutes' => 30,
                'valid_from' => '2026-01-01',
            ])->assertRedirect();

        $s = WorkSchedule::where('user_id', $member->id)->firstOrFail();
        $this->assertSame(ScheduleType::Trust, $s->schedule_type);
        $this->assertSame(0, $s->targetMinutesForWeekday(1));
        $this->assertFalse($s->tracksTarget());
    }

    public function test_org_default_schedule_type_is_used_for_new_schedules(): void {
        $this->organization->settings = ['timesheet' => ['default_schedule_type' => 'trust']];
        $this->organization->save();
        $member = $this->member();

        $defaults = \App\Services\Flextime\WorkScheduleResolver::defaultsFor($member->fresh());

        $this->assertSame('trust', $defaults['schedule_type']);
    }

    public function test_edit_dialog_renders_with_type_selector_and_alpine(): void {
        $member = $this->member();

        $this->actingAs($this->hr())
            ->get(route('users.work-schedule.edit', $member))
            ->assertOk()
            ->assertSee('name="schedule_type"', false)
            ->assertSee('wsForm(', false)
            ->assertSee(__('work_schedule.type.per_weekday'));
    }

    public function test_per_weekday_requires_at_least_one_day(): void {
        $member = $this->member();

        $this->actingAs($this->hr())
            ->put(route('users.work-schedule.update', $member), [
                'schedule_type' => 'per_weekday',
                'day_targets' => [
                    1 => ['enabled' => '0', 'mode' => 'hours', 'hours' => '8'],
                ],
                'break_after_minutes' => 360,
                'break_minutes' => 30,
                'valid_from' => '2026-01-01',
            ])->assertSessionHasErrors('day_targets');

        $this->assertDatabaseCount('work_schedules', 0);
    }
}
