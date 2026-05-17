<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BreakRuleEvaluatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Timekeeping\BreakRuleEvaluator;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class BreakRuleEvaluatorTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_required_minutes_follows_arbzg_thresholds(): void
    {
        $eval = app(BreakRuleEvaluator::class);

        $this->assertSame(0, $eval->requiredMinutes(360));   // exactly 6h → no break required
        $this->assertSame(30, $eval->requiredMinutes(361));  // >6h
        $this->assertSame(30, $eval->requiredMinutes(540));  // exactly 9h
        $this->assertSame(45, $eval->requiredMinutes(541));  // >9h
        $this->assertSame(45, $eval->requiredMinutes(720));  // 12h
    }

    public function test_attendance_saving_tops_up_break_when_below_minimum(): void
    {
        $day = CarbonImmutable::create(2026, 5, 4);
        $attendance = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'date' => $day,
            'started_at' => $day->setTime(8, 0),
            'ended_at' => $day->setTime(17, 0), // 9h gross
            'break_minutes_manual' => 0,
            'break_minutes_auto' => 0,
        ]);

        // >9h would need 45, but gross is exactly 9h so 30 min are required.
        // Wait: gross = 540, threshold for 45 is >540. So minimum is 30.
        $this->assertSame(30, $attendance->break_minutes_auto);
        $this->assertSame(30, $attendance->break_minutes_total);
        $this->assertSame(510, $attendance->duration_minutes);
    }

    public function test_attendance_saving_does_not_top_up_when_break_sufficient(): void
    {
        $day = CarbonImmutable::create(2026, 5, 4);
        $attendance = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'date' => $day,
            'started_at' => $day->setTime(8, 0),
            'ended_at' => $day->setTime(17, 0),
            'break_minutes_manual' => 45,
            'break_minutes_auto' => 0,
        ]);

        $this->assertSame(0, $attendance->break_minutes_auto);
        $this->assertSame(45, $attendance->break_minutes_total);
    }

    public function test_attendance_auto_break_is_disabled_via_config(): void
    {
        config(['timesheet.breaks.auto_apply' => false]);

        $day = CarbonImmutable::create(2026, 5, 4);
        $attendance = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'date' => $day,
            'started_at' => $day->setTime(8, 0),
            'ended_at' => $day->setTime(17, 0),
            'break_minutes_manual' => 0,
            'break_minutes_auto' => 0,
        ]);

        $this->assertSame(0, $attendance->break_minutes_auto);
        $this->assertSame(540, $attendance->duration_minutes);
    }

    public function test_long_attendance_requires_45_minutes(): void
    {
        $day = CarbonImmutable::create(2026, 5, 4);
        $attendance = Attendance::factory()->create([
            'user_id' => $this->user->id,
            'date' => $day,
            'started_at' => $day->setTime(7, 0),
            'ended_at' => $day->setTime(19, 0), // 12h gross
            'break_minutes_manual' => 15,
            'break_minutes_auto' => 0,
        ]);

        // Required 45, manual gave 15, auto should fill 30
        $this->assertSame(30, $attendance->break_minutes_auto);
        $this->assertSame(45, $attendance->break_minutes_total);
        $this->assertSame(720 - 45, $attendance->duration_minutes);
    }
}
