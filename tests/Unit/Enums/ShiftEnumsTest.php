<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftEnumsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Shift\DutyPlanPeriodType;
use App\Enums\Shift\DutyPlanStatus;
use App\Enums\Shift\ScheduledShiftStatus;
use Tests\TestCase;

final class ShiftEnumsTest extends TestCase {
    public function test_scheduled_shift_status(): void {
        $this->assertSame(
            ['draft', 'published', 'confirmed', 'cancelled'],
            ScheduledShiftStatus::values()
        );
        $this->assertSame('ghost', ScheduledShiftStatus::Draft->tone());
        $this->assertSame('info', ScheduledShiftStatus::Published->tone());
        $this->assertSame('success', ScheduledShiftStatus::Confirmed->tone());
        $this->assertSame('error', ScheduledShiftStatus::Cancelled->tone());
        $this->assertNotEmpty(ScheduledShiftStatus::Draft->label());
    }

    public function test_duty_plan_status(): void {
        $this->assertSame(['draft', 'published'], DutyPlanStatus::values());
        $this->assertSame('ghost', DutyPlanStatus::Draft->tone());
        $this->assertSame('info', DutyPlanStatus::Published->tone());
        $this->assertNotEmpty(DutyPlanStatus::Draft->label());
    }

    public function test_duty_plan_period_type(): void {
        $this->assertSame(['daily', 'weekly', 'monthly'], DutyPlanPeriodType::values());
        $this->assertCount(3, DutyPlanPeriodType::options());
        $this->assertNotEmpty(DutyPlanPeriodType::Weekly->label());
    }
}
