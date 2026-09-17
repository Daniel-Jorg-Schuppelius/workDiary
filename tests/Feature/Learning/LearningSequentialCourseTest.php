<?php

/*
 * Filename     : LearningSequentialCourseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Models\Learning\{LearningCourse, LearningUnit};
use App\Models\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lineare Kurse (Vollscan 2026-09-15, `C3-13` / `MVP-798`): `sequential` wurde
 * gespeichert, exportiert und im Autorenwerkzeug angezeigt, aber nirgends
 * durchgesetzt — jede Einheit war sofort abschließbar.
 */
class LearningSequentialCourseTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_sequential_course_releases_a_unit_only_after_its_predecessor(): void {
        $course = $this->course(sequential: true);
        $enrollments = app(LearningEnrollmentService::class);
        $enrollment = $enrollments->enroll($course, $this->learner(), []);
        [$first, $second] = [$this->unitAt($course, 0), $this->unitAt($course, 1)];

        $this->assertTrue($first->isReleasedFor($enrollment));
        $this->assertFalse($second->isReleasedFor($enrollment));

        try {
            $enrollments->completeUnit($enrollment, $second);
            $this->fail('Die zweite Einheit darf vor der ersten nicht abschließbar sein.');
        } catch (ValidationException $exception) {
            $this->assertSame(__('learning.errors.unit_locked_by_sequence'), $exception->errors()['unit'][0]);
        }

        $enrollments->completeUnit($enrollment, $first);

        $this->assertTrue($second->isReleasedFor($enrollment->refresh()));
    }

    public function test_non_sequential_course_keeps_every_unit_open(): void {
        $course = $this->course(sequential: false);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $this->learner(), []);

        $this->assertTrue($this->unitAt($course, 0)->isReleasedFor($enrollment));
        $this->assertTrue($this->unitAt($course, 1)->isReleasedFor($enrollment));
    }

    private function course(bool $sequential): LearningCourse {
        $service = app(LearningCourseService::class);
        $course = $service->createCourse($this->organization, null, ['title' => 'Leitern und Tritte', 'sequential' => $sequential]);
        $service->addUnit($course, ['title' => 'Teil 1', 'kind' => LearningUnitKind::Content->value]);
        $service->addUnit($course, ['title' => 'Teil 2', 'kind' => LearningUnitKind::Content->value]);
        $service->release($course->refresh(), null);

        return $course->refresh();
    }

    private function unitAt(LearningCourse $course, int $index): LearningUnit {
        return $course->units()->orderBy('position')->skip($index)->firstOrFail();
    }

    private function learner(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }
}
