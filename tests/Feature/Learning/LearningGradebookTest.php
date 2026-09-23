<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningGradebookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Models\Learning\{LearningAssignment, LearningCourse, LearningEnrollment, LearningGradebookComponent, LearningManualGrade, LearningQuiz, LearningQuizAttempt, LearningUnit};
use App\Models\Platform\User;
use App\Services\Learning\{LearningAssignmentService, LearningCourseService, LearningEnrollmentService, LearningGradebookService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Notenbuch-Ausbau (Feature 149, MVP-790): ohne Komponenten addiert das
 * Notenbuch wie bisher; mit Komponenten rechnet es gewichtet (Summe 100
 * oder gar keine Gewichte), manuelle Noten sind additiv (die jüngste
 * zählt), Zeugnis und CSV kommen aus derselben Rechnung.
 */
class LearningGradebookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LearningCourse $course;

    private LearningUnit $quizUnit;

    private LearningUnit $assignmentUnit;

    private LearningEnrollment $enrollment;

    private User $learner;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $settings = (array) ($this->organization->settings ?? []);
        data_set($settings, 'learning.grade_scale', [['min_percent' => 90, 'label' => '1'], ['min_percent' => 70, 'label' => '2'], ['min_percent' => 0, 'label' => '5']]);
        $this->organization->update(['settings' => $settings]);

        $courses = app(LearningCourseService::class);
        $this->course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        $courses->addUnit($this->course, ['title' => 'Abschlussprüfung', 'kind' => LearningUnitKind::Quiz->value]);
        $courses->addUnit($this->course, ['title' => 'Praxisbericht', 'kind' => LearningUnitKind::Assignment->value]);
        [$this->quizUnit, $this->assignmentUnit] = $this->course->refresh()->units()->orderBy('position')->get()->all();
        LearningQuiz::query()->create([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $this->quizUnit->id,
            'title' => 'Abschlussprüfung',
            'pass_percent' => 50,
            'max_attempts' => 3,
        ]);
        LearningAssignment::query()->create([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $this->assignmentUnit->id,
            'title' => 'Praxisbericht',
            'submission_kind' => 'text',
            'points' => 20,
            'pass_percent' => 50,
        ]);
        $courses->release($this->course->refresh(), null);
        $this->learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id, 'name' => 'Lisa Lernend']);
        $this->enrollment = app(LearningEnrollmentService::class)->enroll($this->course->refresh(), $this->learner);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function grader(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    /** Abgegebener Versuch mit festem Ergebnis — die Prüfungslogik selbst ist anderswo getestet. */
    private function attempt(int $points, int $max, int $no = 1): void {
        LearningQuizAttempt::query()->create([
            'organization_id' => $this->organization->id,
            'learning_quiz_id' => $this->quizUnit->refresh()->quiz->id,
            'learning_enrollment_id' => $this->enrollment->id,
            'attempt_no' => $no,
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now(),
            'questions_snapshot' => '[]',
            'score_points' => $points,
            'max_points' => $max,
            'score_percent' => (int) round($points / $max * 100),
            'passed' => $points / $max >= 0.5,
        ]);
    }

    private function gradedSubmission(int $points): void {
        $assignment = $this->assignmentUnit->refresh()->assignment;
        $service = app(LearningAssignmentService::class);
        $submission = $service->submit($this->enrollment, $assignment, 'Mein Bericht.');
        $service->grade($submission, [], $points, null, $this->grader());
    }

    public function test_ohne_komponenten_addiert_das_notenbuch_punkte(): void {
        $this->attempt(5, 10);
        $this->attempt(8, 10, 2);
        $this->gradedSubmission(10);

        $result = app(LearningGradebookService::class)->forEnrollment($this->enrollment->refresh());

        $this->assertFalse($result['weighted']);
        $this->assertSame(18, $result['points']);
        $this->assertSame(30, $result['max']);
        $this->assertSame(60, $result['percent']);
        $this->assertFalse($result['pending']);
        $this->assertSame('5', $result['grade']);
    }

    public function test_gewichte_muessen_hundert_ergeben_oder_leer_bleiben(): void {
        $service = app(LearningGradebookService::class);

        try {
            $service->saveComponents($this->course, [
                ['kind' => 'quiz', 'unit_id' => $this->quizUnit->id, 'weight' => 60],
                ['kind' => 'assignment', 'unit_id' => $this->assignmentUnit->id, 'weight' => 30],
            ]);
            $this->fail('Summe 90 muss abgelehnt werden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('components', $e->errors());
        }

        // Halb gewichtet ist genauso unbrauchbar.
        try {
            $service->saveComponents($this->course, [
                ['kind' => 'quiz', 'unit_id' => $this->quizUnit->id, 'weight' => 100],
                ['kind' => 'assignment', 'unit_id' => $this->assignmentUnit->id],
            ]);
            $this->fail('Teilweise Gewichte müssen abgelehnt werden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('components', $e->errors());
        }

        $components = $service->saveComponents($this->course, [
            ['kind' => 'quiz', 'unit_id' => $this->quizUnit->id],
            ['kind' => 'manual', 'title' => 'Mündlich', 'max_points' => 10],
        ]);
        $this->assertCount(2, $components);
        $this->assertSame(['Abschlussprüfung', 'Mündlich'], $components->pluck('title')->all());

        // Über den Dialog: Summe 90 ⇒ Fehler an der Komponentenliste.
        $this->actingAs($this->grader())
            ->put(route('learning.courses.gradebook.components.update', $this->course), ['components' => [
                ['kind' => 'quiz', 'unit' => $this->quizUnit->sqid, 'enabled' => '1', 'weight' => 60],
                ['kind' => 'manual', 'title' => 'Mündlich', 'max_points' => 10, 'weight' => 30],
            ]])
            ->assertSessionHasErrors('components');
    }

    public function test_gewichtete_komponenten_und_additive_manuelle_note(): void {
        $service = app(LearningGradebookService::class);
        $components = $service->saveComponents($this->course, [
            ['kind' => 'quiz', 'unit_id' => $this->quizUnit->id, 'weight' => 60],
            ['kind' => 'manual', 'title' => 'Mündlich', 'max_points' => 10, 'weight' => 40],
        ]);
        $manual = $components->firstWhere('kind', 'manual');
        $this->attempt(5, 10);

        $result = $service->forEnrollment($this->enrollment->refresh());
        $this->assertTrue($result['weighted']);
        $this->assertTrue($result['pending'], 'Ohne manuelle Note bleibt das Ergebnis offen.');
        $this->assertNull($result['grade']);

        $grader = $this->grader();
        $service->recordManualGrade($this->enrollment, $manual, 6, 'Solide.', $grader);
        $service->recordManualGrade($this->enrollment, $manual, 10, 'Nachprüfung.', $grader, now()->addMinute());
        $this->assertSame(2, LearningManualGrade::query()->count(), 'Additiv: beide Einträge bleiben.');

        $result = $service->forEnrollment($this->enrollment->refresh());
        // 60 % × 50 % + 40 % × 100 % = 70 %
        $this->assertSame(70, $result['percent']);
        $this->assertFalse($result['pending']);
        $this->assertSame('2', $result['grade']);

        try {
            $service->recordManualGrade($this->enrollment, $manual, 11, null, $grader);
            $this->fail('Mehr als das Maximum darf nicht eingetragen werden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('points', $e->errors());
        }
    }

    public function test_notenbuchseite_dialoge_zeugnis_und_csv(): void {
        $service = app(LearningGradebookService::class);
        $components = $service->saveComponents($this->course, [
            ['kind' => 'quiz', 'unit_id' => $this->quizUnit->id],
            ['kind' => 'manual', 'title' => 'Mündlich', 'max_points' => 10],
        ]);
        $manual = $components->firstWhere('kind', 'manual');
        $this->attempt(9, 10);
        $grader = $this->grader();

        $this->actingAs($grader)
            ->get(route('learning.courses.gradebook.show', $this->course))
            ->assertOk()
            ->assertSee('Lisa Lernend')
            ->assertSee('Mündlich')
            ->assertSee('9 / 10');

        $this->actingAs($grader)
            ->get(route('learning.courses.gradebook.components.edit', $this->course))
            ->assertOk()
            ->assertSee('Abschlussprüfung');

        $this->actingAs($grader)
            ->post(route('learning.courses.gradebook.grade.store', [$this->course, $this->enrollment, $manual]), ['points' => 8, 'note' => 'Gut.'])
            ->assertRedirect(route('learning.courses.gradebook.show', $this->course));
        $this->assertSame(8, LearningManualGrade::query()->firstOrFail()->points);

        $pdf = $this->actingAs($grader)->get(route('learning.courses.gradebook.report-card', [$this->course, $this->enrollment]));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $csv = $this->actingAs($grader)->get(route('learning.courses.gradebook.csv', $this->course));
        $csv->assertOk();
        $this->assertStringContainsString('Lisa Lernend', $csv->getContent());
        $this->assertStringContainsString('9/10', $csv->getContent());
        $this->assertStringContainsString('85', $csv->getContent(), 'Gesamt 17/20 = 85 %');

        // Die lernende Person lädt ihr eigenes Zeugnis; ein fremdes bleibt 404.
        $this->actingAs($this->learner)
            ->get(route('learning.my.report-card', $this->enrollment))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($other)->get(route('learning.my.report-card', $this->enrollment))->assertNotFound();
        $this->actingAs($this->learner)->get(route('learning.courses.gradebook.show', $this->course))->assertForbidden();
    }

    public function test_gestrichene_komponente_nimmt_ihre_noten_mit(): void {
        $service = app(LearningGradebookService::class);
        $components = $service->saveComponents($this->course, [['kind' => 'manual', 'title' => 'Mündlich', 'max_points' => 10]]);
        $service->recordManualGrade($this->enrollment, $components->first(), 7, null, $this->grader());

        $service->saveComponents($this->course, [['kind' => 'quiz', 'unit_id' => $this->quizUnit->id]]);

        $this->assertSame(0, LearningGradebookComponent::query()->where('kind', 'manual')->count());
        $this->assertSame(0, LearningManualGrade::query()->count());
    }
}
