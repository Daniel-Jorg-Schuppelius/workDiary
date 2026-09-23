<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAttemptReviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningQuestionKind, LearningUnitKind};
use App\Models\Audit\AuditLog;
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningQuestion, LearningQuiz, LearningQuizAttemptWaiver, LearningUnit};
use App\Models\Platform\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningQuestionCatalogService, LearningQuizService, LearningReportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Prüfungsakte für Prüfende, Prüfungsstatistik und Versuchsfreigabe
 * (Feature 149, MVP-785): Einsicht nur mit Recht und protokolliert, Quoten
 * je Frage erst ab der Mindestgruppe, Freigabe genau einmal.
 */
class LearningAttemptReviewTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function grader(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    private function learner(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    /** @return array{0: LearningCourse, 1: LearningUnit, 2: LearningQuiz, 3: LearningQuestion} */
    private function quiz(array $quizAttributes = []): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);
        $courses->addUnit($course, ['title' => 'Prüfung', 'kind' => LearningUnitKind::Quiz->value]);
        $unit = $course->refresh()->units()->firstOrFail();
        $quiz = LearningQuiz::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => 'Prüfung',
            'pass_percent' => 50,
            'max_attempts' => 1,
            'shuffle_answers' => false,
        ], $quizAttributes));
        $question = LearningQuestion::query()->create([
            'organization_id' => $this->organization->id,
            'kind' => LearningQuestionKind::Single->value,
            'prompt' => 'Notrufnummer?',
            'points' => 2,
            'position' => 1,
        ]);
        $question->options()->create(['organization_id' => $this->organization->id, 'label' => '112', 'is_correct' => true, 'position' => 1]);
        $question->options()->create(['organization_id' => $this->organization->id, 'label' => '110', 'is_correct' => false, 'position' => 2]);
        app(LearningQuestionCatalogService::class)->attach($quiz, $question);
        $courses->release($course->refresh(), null);

        return [$course->refresh(), $unit, $quiz->refresh(), $question->refresh()];
    }

    private function enroll(LearningCourse $course, ?User $user = null): LearningEnrollment {
        return app(LearningEnrollmentService::class)->enroll($course, $user ?? $this->learner());
    }

    public function test_pruefungsakte_nur_mit_recht_und_protokolliert(): void {
        [$course, , $quiz, $question] = $this->quiz();
        $learner = $this->learner();
        $enrollment = $this->enroll($course, $learner);
        $attempt = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);
        app(LearningQuizService::class)->submitAttempt($attempt, [
            $question->id => ['option_ids' => [(string) $question->options()->where('is_correct', false)->firstOrFail()->id]],
        ]);

        $this->actingAs($learner)->get(route('learning.grading.attempts.show', $attempt))->assertForbidden();

        $this->actingAs($this->grader())
            ->get(route('learning.grading.attempts.show', ['attempt' => $attempt, 'reason' => 'Widerspruch']))
            ->assertOk()
            ->assertSee('Notrufnummer?')
            ->assertSee('110')
            ->assertSee($learner->name);

        $log = AuditLog::query()->where('event', 'learning.attemptViewed')->firstOrFail();
        $this->assertSame($attempt->id, (int) $log->auditable_id);
        $this->assertSame('Widerspruch', $log->changes['reason'] ?? null);
    }

    public function test_statistik_unterdrueckt_quoten_unter_der_mindestgruppe(): void {
        [$course, $unit, $quiz, $question] = $this->quiz();
        $quizzes = app(LearningQuizService::class);
        $correct = (string) $question->options()->where('is_correct', true)->firstOrFail()->id;
        $wrong = (string) $question->options()->where('is_correct', false)->firstOrFail()->id;

        foreach (range(1, 3) as $i) {
            $attempt = $quizzes->startAttempt($this->enroll($course), $quiz);
            $quizzes->submitAttempt($attempt, [$question->id => ['option_ids' => [$i === 1 ? $wrong : $correct]]]);
        }

        $stats = app(LearningReportService::class)->quizStatistics($quiz);
        $this->assertSame(3, $stats['attempts']);
        $this->assertSame(2, $stats['passed']);
        $this->assertNull($stats['pass_rate'], 'Unter der Mindestgruppe keine Quote.');
        $this->assertNull($stats['questions'][0]['error_rate']);

        foreach (range(1, LearningReportService::MIN_GROUP - 3) as $i) {
            $attempt = $quizzes->startAttempt($this->enroll($course), $quiz);
            $quizzes->submitAttempt($attempt, [$question->id => ['option_ids' => [$correct]]]);
        }

        $stats = app(LearningReportService::class)->quizStatistics($quiz);
        $this->assertNotNull($stats['pass_rate']);
        $this->assertSame(20.0, $stats['questions'][0]['error_rate']);

        $this->actingAs($this->grader())
            ->get(route('learning.courses.units.quiz.statistics', [$course, $unit]))
            ->assertOk()
            ->assertSee(__('learning.field.pass_rate'))
            ->assertSee('Notrufnummer?');
    }

    public function test_versuchsfreigabe_gilt_genau_einmal(): void {
        [$course, , $quiz, $question] = $this->quiz(['max_attempts' => 1, 'retry_wait_hours' => 48]);
        $learner = $this->learner();
        $enrollment = $this->enroll($course, $learner);
        $quizzes = app(LearningQuizService::class);
        $wrong = (string) $question->options()->where('is_correct', false)->firstOrFail()->id;
        $quizzes->submitAttempt($quizzes->startAttempt($enrollment, $quiz), [$question->id => ['option_ids' => [$wrong]]]);

        try {
            $quizzes->startAttempt($enrollment, $quiz);
            $this->fail('Die Versuchsgrenze muss greifen.');
        } catch (ValidationException) {
        }

        $this->actingAs($this->grader())
            ->post(route('learning.courses.enrollments.attempt-waiver', [$course, $enrollment]), ['quiz_id' => $quiz->sqid, 'reason' => 'Technische Störung'])
            ->assertRedirect(route('learning.courses.enrollments.index', $course));

        $waiver = LearningQuizAttemptWaiver::query()->firstOrFail();
        $this->assertNull($waiver->used_at);

        $second = $quizzes->startAttempt($enrollment, $quiz);
        $this->assertSame(2, $second->attempt_no);
        $this->assertNotNull($waiver->refresh()->used_at);
        $quizzes->submitAttempt($second, [$question->id => ['option_ids' => [$wrong]]]);

        $this->expectException(ValidationException::class);
        $quizzes->startAttempt($enrollment, $quiz);
    }

    public function test_freigabe_braucht_begruendung_und_eigene_pruefung(): void {
        [$course, , $quiz] = $this->quiz();
        [, , $otherQuiz] = $this->quiz();
        $enrollment = $this->enroll($course);

        $this->actingAs($this->grader())
            ->post(route('learning.courses.enrollments.attempt-waiver', [$course, $enrollment]), ['quiz_id' => $quiz->sqid])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->grader())
            ->post(route('learning.courses.enrollments.attempt-waiver', [$course, $enrollment]), ['quiz_id' => $otherQuiz->sqid, 'reason' => 'Falscher Kurs'])
            ->assertSessionHasErrors('quiz_id');

        $this->assertSame(0, LearningQuizAttemptWaiver::query()->count());
    }
}
