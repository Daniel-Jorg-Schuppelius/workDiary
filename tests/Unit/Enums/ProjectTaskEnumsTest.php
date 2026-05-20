<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTaskEnumsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\Project\ProjectStatus;
use App\Enums\Task\TaskPriority;
use App\Enums\Task\TaskStatus;
use Tests\TestCase;

final class ProjectTaskEnumsTest extends TestCase {
    public function test_project_status_values(): void {
        $this->assertSame(['active', 'paused', 'archived'], ProjectStatus::values());
        $this->assertSame('success', ProjectStatus::Active->tone());
        $this->assertSame('warning', ProjectStatus::Paused->tone());
        $this->assertSame('ghost', ProjectStatus::Archived->tone());
        $this->assertNotEmpty(ProjectStatus::Active->label());
        $this->assertArrayHasKey('active', ProjectStatus::options());
    }

    public function test_task_status_values(): void {
        $this->assertSame(['open', 'in_progress', 'done'], TaskStatus::values());
        $this->assertSame('neutral', TaskStatus::Open->tone());
        $this->assertSame('info', TaskStatus::InProgress->tone());
        $this->assertSame('success', TaskStatus::Done->tone());
        $this->assertNotEmpty(TaskStatus::Done->label());
    }

    public function test_task_priority_values(): void {
        $this->assertSame(['low', 'medium', 'high', 'urgent'], TaskPriority::values());
        $this->assertSame('ghost', TaskPriority::Low->tone());
        $this->assertSame('error', TaskPriority::Urgent->tone());
        $this->assertSame('#94a3b8', TaskPriority::Low->color());
        $this->assertSame('#3b82f6', TaskPriority::Medium->color());
        $this->assertSame('#f59e0b', TaskPriority::High->color());
        $this->assertSame('#ef4444', TaskPriority::Urgent->color());
        $this->assertCount(4, TaskPriority::options());
    }
}
