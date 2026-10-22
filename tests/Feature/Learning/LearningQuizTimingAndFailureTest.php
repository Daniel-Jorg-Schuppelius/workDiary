<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuizTimingAndFailureTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningQuestionKind, LearningUnitKind};
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningEnrollmentEvent, LearningQuestion, LearningQuestionOption, LearningQuiz};
use App\Models\Platform\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningQuizService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Zeitlimit je Frage und Nachteilsausgleich (MVP-1267), endgültig nicht bestanden (MVP-1266). */
final class LearningQuizTimingAndFailureTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LearningCourse $course;

    private LearningQuiz $quiz;

    private LearningQuestionOption $wrong;

    private LearningQuestion $question;

    private User $learner;

    private LearningEnrollment $enrollment;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Carbon::setTestNow('2026-10-22 10:00:00');
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, mixed> $quizAttributes */
    private function scenario(array $quizAttributes = []): void {
        $courses = app(LearningCourseService::class);
        $this->course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);
        $courses->addUnit($this->course, ['title' => 'Abschlussprüfung', 'kind' => LearningUnitKind::Quiz->value]);
        $unit = $this->course->refresh()->units()->firstOrFail();
        $this->quiz = LearningQuiz::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'learning_unit_id' => $unit->id, 'title' => 'Abschlussprüfung', 'pass_percent' => 50, 'max_attempts' => 1,
        ], $quizAttributes));
        $this->question = LearningQuestion::query()->create([
            'organization_id' => $this->organization->id, 'kind' => LearningQuestionKind::Single->value, 'prompt' => 'Welche Nummer hat die Feuerwehr?', 'points' => 2, 'position' => 1,
        ]);
        app(\App\Services\Learning\LearningQuestionCatalogService::class)->attach($this->quiz, $this->question);
        $this->question->options()->create(['organization_id' => $this->organization->id, 'label' => '112', 'is_correct' => true, 'position' => 1]);
        $this->wrong = $this->question->options()->create(['organization_id' => $this->organization->id, 'label' => '110', 'position' => 2]);
        $courses->release($this->course->refresh(), null);
        $this->learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->enrollment = app(LearningEnrollmentService::class)->enroll($this->course->refresh(), $this->learner);
    }

    public function test_time_extension_lengthens_the_overall_limit_and_is_recorded(): void {
        $this->scenario(['time_limit_minutes' => 10]);
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->get(route('learning.courses.enrollments.time-extension', [$this->course, $this->enrollment]))->assertOk();
        $this->actingAs($admin)->post(route('learning.courses.enrollments.time-extension.update', [$this->course, $this->enrollment]), [
            'time_extension_percent' => 50, 'reason' => 'Nachteilsausgleich laut Bescheid',
        ])->assertSessionHas('success');

        $enrollment = $this->enrollment->fresh();
        $this->assertSame(50, $enrollment->time_extension_percent);
        $event = LearningEnrollmentEvent::query()->where('learning_enrollment_id', $enrollment->id)->where('event', 'time_extension_set')->sole();
        $this->assertSame('Nachteilsausgleich laut Bescheid', $event->reason);

        $attempt = app(LearningQuizService::class)->startAttempt($enrollment, $this->quiz);
        $this->assertSame('2026-10-22 10:15:00', $attempt->expires_at?->format('Y-m-d H:i:s'));
        $this->actingAs($admin)->get(route('learning.courses.enrollments.index', $this->course))->assertSee('+50 %');
    }

    public function test_question_time_limit_paces_single_questions_without_going_back(): void {
        $this->scenario(['question_time_limit_seconds' => 60, 'allow_back' => true, 'display_mode' => 'all']);
        $this->enrollment->update(['time_extension_percent' => 50]);
        $service = app(LearningQuizService::class);

        $this->assertTrue($this->quiz->isSingleQuestionMode());
        $this->assertFalse($this->quiz->allowsBack());
        $this->assertSame(90, $service->questionSeconds($this->quiz, $this->enrollment->fresh()));

        $this->actingAs($this->learner)->post(route('learning.my.quiz.start', [$this->enrollment, $this->quiz]))->assertRedirect();
        $attempt = \App\Models\Learning\LearningQuizAttempt::query()->where('learning_enrollment_id', $this->enrollment->id)->sole();
        $this->assertSame('2026-10-22 10:01:30', $attempt->expires_at?->format('Y-m-d H:i:s'));
        $this->actingAs($this->learner)->get(route('learning.my.quiz.show', [$this->enrollment, $attempt]))
            ->assertOk()
            ->assertSee(__('learning.field.question_remaining'))
            ->assertSee('&quot;questionSeconds&quot;:90', false)
            ->assertDontSee('@click="previous()"', false);
    }

    public function test_last_failed_attempt_notifies_learner_and_lead_unless_a_waiver_is_open(): void {
        $this->scenario();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $service = app(LearningQuizService::class);

        $service->grantWaiver($this->enrollment, $this->quiz, $lead, 'Krankheit am Prüfungstag');
        $first = $service->startAttempt($this->enrollment, $this->quiz);
        // Die Freigabe ist mit dem Start verbraucht — der Versuch ist damit der letzte.
        $service->submitAttempt($first, [$this->question->id => ['option_ids' => [$this->wrong->id]]]);
        $event = fn (User $user): int => $user->notifications()->get()->filter(fn ($n): bool => ($n->data['event'] ?? null) === 'learning.failedFinal')->count();
        $this->assertSame(1, $event($this->learner));
        $this->assertSame(1, $event($lead));

        $service->grantWaiver($this->enrollment, $this->quiz, $lead, 'Zweite Chance');
        $second = $service->startAttempt($this->enrollment, $this->quiz);
        $service->grantWaiver($this->enrollment, $this->quiz, $lead, 'Noch eine');
        $service->submitAttempt($second, [$this->question->id => ['option_ids' => [$this->wrong->id]]]);
        $this->assertSame(1, $event($this->learner->fresh()));
    }
}
