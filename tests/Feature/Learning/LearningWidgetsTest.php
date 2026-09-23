<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningWidgetsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Dashboard\Widgets\{LearningDueWidget, LearningGradingQueueWidget};
use App\Enums\Learning\LearningUnitKind;
use App\Models\Learning\LearningAssignment;
use App\Models\Platform\User;
use App\Services\Learning\{LearningAssignmentService, LearningCourseService, LearningEnrollmentService};
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Dashboard-Kacheln der Lernplattform (Feature 149, MVP-789): „Meine
 * Schulungen" nur mit Einschreibung, „Bewertungen offen" nur mit
 * Bewertungsrecht; beide rendern mit Daten.
 */
class LearningWidgetsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function html(View|string $rendered): string {
        return $rendered instanceof View ? $rendered->render() : (string) $rendered;
    }

    public function test_meine_schulungen_kachel_erscheint_nur_mit_einschreibung(): void {
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $widget = new LearningDueWidget;
        $this->assertFalse($widget->availableFor($learner));

        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        $courses->addUnit($course, ['title' => 'Grundlagen']);
        $courses->release($course->refresh(), null);
        app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner, ['due_at' => now()->subDay()->toDateString()]);

        $this->assertTrue($widget->availableFor($learner));
        $html = $this->html($widget->render($learner));
        $this->assertStringContainsString('Brandschutz', $html);
        $this->assertStringContainsString('badge-error', $html, 'Überfällig wird rot ausgewiesen.');
    }

    public function test_bewertungs_kachel_zaehlt_offene_abgaben(): void {
        $grader = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $widget = new LearningGradingQueueWidget;
        $this->assertTrue($widget->availableFor($grader));
        $this->assertFalse($widget->availableFor($learner));

        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, $grader, ['title' => 'Erste Hilfe']);
        $courses->addUnit($course, ['title' => 'Bericht', 'kind' => LearningUnitKind::Assignment->value]);
        $unit = $course->refresh()->units()->firstOrFail();
        $assignment = LearningAssignment::query()->create([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => 'Bericht',
            'submission_kind' => 'text',
            'points' => 10,
            'pass_percent' => 50,
        ]);
        $courses->release($course->refresh(), null);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);
        app(LearningAssignmentService::class)->submit($enrollment, $assignment->refresh(), 'Mein Bericht.');

        $html = $this->html($widget->render($grader));
        $this->assertStringContainsString(__('learning.field.pending_submissions'), $html);
        $this->assertStringContainsString('badge-warning', $html);
        $this->assertStringContainsString(route('learning.grading.index'), $html);
    }
}
