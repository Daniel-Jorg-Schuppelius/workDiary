<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEnumsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Diary\{LocationMode, Mode, Priority, Status};
use Tests\TestCase;

final class DiaryEnumsTest extends TestCase {
    public function test_priority_values_and_tones(): void {
        $this->assertSame(['low', 'normal', 'high', 'urgent'], Priority::values());
        $this->assertSame('ghost', Priority::Low->tone());
        $this->assertSame('info', Priority::Normal->tone());
        $this->assertSame('warning', Priority::High->tone());
        $this->assertSame('error', Priority::Urgent->tone());
        $this->assertNotEmpty(Priority::Normal->label());
    }

    public function test_location_mode_values(): void {
        $this->assertSame(['onsite', 'remote', 'hybrid'], LocationMode::values());
        $this->assertNotEmpty(LocationMode::Onsite->label());
        $this->assertNotEmpty(LocationMode::Remote->label());
        $this->assertNotEmpty(LocationMode::Hybrid->label());
    }

    public function test_mode_values_and_labels(): void {
        $this->assertSame(['fixed', 'deadline', 'window', 'recurring', 'backlog'], Mode::values());
        $this->assertNotEmpty(Mode::Fixed->label());
        $this->assertNotEmpty(Mode::Deadline->label());
        $this->assertNotEmpty(Mode::Window->label());
        $this->assertNotEmpty(Mode::Recurring->label());
        $this->assertNotEmpty(Mode::Backlog->label());
    }

    public function test_status_values_and_tones(): void {
        $this->assertSame([-1, 1, 2, 3], Status::values());
        $this->assertSame('done', Status::Done->tone());
        $this->assertSame('progress', Status::InProgress->tone());
        $this->assertSame('open', Status::Open->tone());
        $this->assertSame('alert', Status::Problem->tone());
        $this->assertNotEmpty(Status::Done->label());
        $this->assertNotEmpty(Status::InProgress->label());
        $this->assertNotEmpty(Status::Open->label());
        $this->assertNotEmpty(Status::Problem->label());
    }
}
