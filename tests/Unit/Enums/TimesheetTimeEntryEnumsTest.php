<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetTimeEntryEnumsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Timesheet\TimesheetKind;
use App\Enums\Timesheet\TimesheetStatus;
use Tests\TestCase;

final class TimesheetTimeEntryEnumsTest extends TestCase {
    public function test_timesheet_status_values(): void {
        $this->assertSame(['draft', 'submitted', 'signed', 'locked'], TimesheetStatus::values());
        $this->assertSame('neutral', TimesheetStatus::Draft->tone());
        $this->assertSame('info', TimesheetStatus::Submitted->tone());
        $this->assertSame('success', TimesheetStatus::Signed->tone());
        $this->assertSame('warning', TimesheetStatus::Locked->tone());
        $this->assertNotEmpty(TimesheetStatus::Draft->label());
        $this->assertArrayHasKey('draft', TimesheetStatus::options());
    }

    public function test_timesheet_kind_values(): void {
        $this->assertSame(['project', 'personal_day'], TimesheetKind::values());
        $this->assertNotEmpty(TimesheetKind::Project->label());
        $this->assertNotEmpty(TimesheetKind::PersonalDay->label());
        $this->assertCount(2, TimesheetKind::options());
    }

    public function test_time_entry_kind_values(): void {
        $this->assertSame(['work', 'travel', 'standby'], TimeEntryKind::values());
        $this->assertNotEmpty(TimeEntryKind::Work->label());
        $this->assertNotEmpty(TimeEntryKind::Travel->label());
        $this->assertNotEmpty(TimeEntryKind::Standby->label());
        $this->assertArrayHasKey('travel', TimeEntryKind::options());
    }
}
