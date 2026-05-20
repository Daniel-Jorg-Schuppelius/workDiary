<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceEnumsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Attendance\AttendanceStatus;
use Tests\TestCase;

final class AttendanceEnumsTest extends TestCase {
    public function test_attendance_status_values(): void {
        $this->assertSame(
            ['open', 'closed', 'auto_closed', 'adjusted', 'cancelled'],
            AttendanceStatus::values()
        );
        $this->assertSame('info', AttendanceStatus::Open->tone());
        $this->assertSame('success', AttendanceStatus::Closed->tone());
        $this->assertSame('warning', AttendanceStatus::AutoClosed->tone());
        $this->assertSame('warning', AttendanceStatus::Adjusted->tone());
        $this->assertSame('ghost', AttendanceStatus::Cancelled->tone());
        $this->assertNotEmpty(AttendanceStatus::Open->label());
        $this->assertArrayHasKey('open', AttendanceStatus::options());
    }

    public function test_attendance_source_values(): void {
        $this->assertSame(
            ['clock', 'manual', 'import', 'auto_close'],
            AttendanceSource::values()
        );
        $this->assertNotEmpty(AttendanceSource::Clock->label());
        $this->assertCount(4, AttendanceSource::options());
    }
}
