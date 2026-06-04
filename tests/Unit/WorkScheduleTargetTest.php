<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkScheduleTargetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit;

use App\Enums\WorkSchedule\ScheduleType;
use App\Models\WorkSchedule;
use Tests\TestCase;

/**
 * Zentrale Soll-Ermittlung je Arbeitszeit-Typ ({@see WorkSchedule::targetMinutesForWeekday()}).
 * Reine Modell-Logik, keine DB.
 */
class WorkScheduleTargetTest extends TestCase {
    public function test_flextime_returns_daily_target_on_working_days_only(): void {
        $s = new WorkSchedule([
            'schedule_type' => ScheduleType::Flextime,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        $this->assertSame(480, $s->targetMinutesForWeekday(1)); // Mo
        $this->assertSame(0, $s->targetMinutesForWeekday(6));   // Sa
        $this->assertTrue($s->tracksTarget());
    }

    public function test_weekly_distributes_weekly_minutes_over_working_days(): void {
        $s = new WorkSchedule([
            'schedule_type' => ScheduleType::Weekly,
            'weekly_minutes' => 2000,
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        $this->assertSame(400, $s->targetMinutesForWeekday(2)); // 2000/5
        $this->assertSame(0, $s->targetMinutesForWeekday(7));   // So
    }

    public function test_per_weekday_uses_day_targets_map(): void {
        $s = new WorkSchedule([
            'schedule_type' => ScheduleType::PerWeekday,
            'day_targets' => [
                1 => ['mode' => 'times', 'start' => '08:00', 'end' => '17:00', 'break' => 60, 'minutes' => 480],
                5 => ['mode' => 'hours', 'minutes' => 360],
            ],
        ]);

        $this->assertSame(480, $s->targetMinutesForWeekday(1));
        $this->assertSame(360, $s->targetMinutesForWeekday(5));
        $this->assertSame(0, $s->targetMinutesForWeekday(3));
        $this->assertTrue($s->appliesOnWeekday(1));
        $this->assertFalse($s->appliesOnWeekday(3));
    }

    public function test_trust_never_has_a_target(): void {
        $s = new WorkSchedule([
            'schedule_type' => ScheduleType::Trust,
            'daily_target_minutes' => 480,
            'weekly_minutes' => 2400,
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        $this->assertSame(0, $s->targetMinutesForWeekday(1));
        $this->assertSame(0, $s->targetMinutesForWeekday(3));
        $this->assertFalse($s->tracksTarget());
    }

    public function test_default_type_is_flextime(): void {
        $this->assertSame(ScheduleType::Flextime, (new WorkSchedule)->schedule_type);
    }
}
