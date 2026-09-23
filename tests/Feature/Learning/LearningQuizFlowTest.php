<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuizFlowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningQuestionKind, LearningUnitKind};
use App\Models\Learning\{LearningAnswer, LearningCourse, LearningEnrollment, LearningQuestion, LearningQuiz, LearningUnit};
use App\Models\Platform\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningQuestionCatalogService, LearningQuizService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Prüfungsablauf (Feature 149, MVP-783): Zwischenspeichern je Antwort,
 * Wiederaufnahme, Abgabe nach Ablauf nur aus dem Zwischenspeicher,
 * Pflichtbeantwortung, Merken, Ergebnistexte, Ein-Seiten-Modus unverändert.
 */
class LearningQuizFlowTest extends TestCase {
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

    private function quizzes(): LearningQuizService {
        return app(LearningQuizService::class);
    }

    /**
     * @return array{0: LearningEnrollment, 1: LearningQuiz, 2: list<LearningQuestion>, 3: User, 4: LearningCourse, 5: LearningUnit}
     */
    private function scenario(array $quizAttributes = [], int $questions = 2): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);
        $courses->addUnit($course, ['title' => 'Abschlussprüfung', 'kind' => LearningUnitKind::Quiz->value]);
        $unit = $course->refresh()->units()->firstOrFail();

        $quiz = LearningQuiz::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => 'Abschlussprüfung',
            'pass_percent' => 50,
            'max_attempts' => 3,
            'shuffle_questions' => false,
            'shuffle_answers' => false,
        ], $quizAttributes));

        $created = [];
        for ($i = 1; $i <= $questions; $i++) {
            $question = LearningQuestion::query()->create([
                'organization_id' => $this->organization->id,
                'kind' => LearningQuestionKind::Single->value,
                'prompt' => 'Frage ' . $i,
                'points' => 2,
                'position' => $i,
                'settings' => ['hint' => 'Denken Sie an die 112.'],
            ]);
            $question->options()->create(['organization_id' => $this->organization->id, 'label' => 'richtig', 'is_correct' => true, 'position' => 1]);
            $question->options()->create(['organization_id' => $this->organization->id, 'label' => 'falsch', 'is_correct' => false, 'position' => 2]);
            app(LearningQuestionCatalogService::class)->attach($quiz, $question);
            $created[] = $question->refresh();
        }

        $courses->release($course->refresh(), null);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $user);

        return [$enrollment, $quiz->refresh(), $created, $user, $course->refresh(), $unit];
    }

    private function correctOptionId(LearningQuestion $question): int {
        return (int) $question->options()->where('is_correct', true)->firstOrFail()->id;
    }

    public function test_antwort_wird_zwischengespeichert_ohne_bewertung(): void {
        [$enrollment, $quiz, $questions, $user] = $this->scenario();
        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);

        $this->actingAs($user)
            ->patchJson(route('learning.my.quiz.answer', [$enrollment, $attempt]), [
                'question_id' => $questions[0]->id,
                'payload' => ['option_ids' => [(string) $this->correctOptionId($questions[0])]],
                'flagged' => true,
            ])
            ->assertOk()
            ->assertJson(['saved' => true, 'answered' => true, 'flagged' => true]);

        $answer = LearningAnswer::query()->where('learning_quiz_attempt_id', $attempt->id)->firstOrFail();
        $this->assertNull($answer->is_correct, 'Zwischenspeichern bewertet nichts.');
        $this->assertSame(0, $answer->points_awarded);
        $this->assertTrue($answer->flagged);
        $this->assertNull($attempt->refresh()->submitted_at);
    }

    public function test_wiederaufnahme_zeigt_gespeicherte_antworten_und_tipp(): void {
        [$enrollment, $quiz, $questions, $user] = $this->scenario();
        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);
        $optionId = $this->correctOptionId($questions[0]);
        $this->quizzes()->saveAnswer($attempt, $questions[0]->id, ['option_ids' => [(string) $optionId]], true);

        $this->actingAs($user)
            ->get(route('learning.my.quiz.show', [$enrollment, $attempt]))
            ->assertOk()
            ->assertSee('value="' . $optionId . '" checked', false)
            ->assertSee('Denken Sie an die 112.')
            ->assertSee(__('learning.action.mark_question'));
    }

    public function test_abgabe_nimmt_formular_vor_zwischenspeicher(): void {
        [$enrollment, $quiz, $questions] = $this->scenario();
        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);
        $wrong = (int) $questions[0]->options()->where('is_correct', false)->firstOrFail()->id;
        $this->quizzes()->saveAnswer($attempt, $questions[0]->id, ['option_ids' => [(string) $wrong]]);
        $this->quizzes()->saveAnswer($attempt, $questions[1]->id, ['option_ids' => [(string) $this->correctOptionId($questions[1])]]);

        // Frage 1 im Formular korrigiert, Frage 2 nur aus dem Speicher.
        $submitted = $this->quizzes()->submitAttempt($attempt, [
            $questions[0]->id => ['option_ids' => [(string) $this->correctOptionId($questions[0])]],
        ]);

        $this->assertSame(4, $submitted->score_points);
        $this->assertTrue($submitted->passed);
    }

    public function test_nach_ablauf_zaehlt_nur_der_zwischenspeicher(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));
        [$enrollment, $quiz, $questions] = $this->scenario(['time_limit_minutes' => 10]);
        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);
        $this->quizzes()->saveAnswer($attempt, $questions[0]->id, ['option_ids' => [(string) $this->correctOptionId($questions[0])]]);

        Carbon::setTestNow(Carbon::parse('2026-09-15 10:15:00'));

        try {
            $this->quizzes()->saveAnswer($attempt, $questions[1]->id, ['option_ids' => [(string) $this->correctOptionId($questions[1])]]);
            $this->fail('Nach Ablauf darf nichts mehr gespeichert werden.');
        } catch (ValidationException) {
        }

        $submitted = $this->quizzes()->submitAttempt($attempt, [
            $questions[1]->id => ['option_ids' => [(string) $this->correctOptionId($questions[1])]],
        ]);

        $this->assertSame(2, $submitted->score_points, 'Die verspätete Formularantwort zählt nicht.');
        $this->assertTrue($submitted->passed);
    }

    public function test_abgabe_kurz_nach_ablauf_zaehlt_noch(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));
        [$enrollment, $quiz, $questions] = $this->scenario(['time_limit_minutes' => 10]);
        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);

        // Der Countdown löst die Abgabe bei 0 aus — Sekunden später am Server.
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:10:05'));
        $submitted = $this->quizzes()->submitAttempt($attempt, [
            $questions[0]->id => ['option_ids' => [(string) $this->correctOptionId($questions[0])]],
            $questions[1]->id => ['option_ids' => [(string) $this->correctOptionId($questions[1])]],
        ]);

        $this->assertSame(4, $submitted->score_points);
    }

    public function test_pflichtbeantwortung_blockiert_die_abgabe(): void {
        [$enrollment, $quiz, $questions, $user] = $this->scenario(['require_all_answered' => true]);
        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);

        $this->actingAs($user)
            ->post(route('learning.my.quiz.submit', [$enrollment, $attempt]), [
                'answers' => [$questions[0]->id => ['option_ids' => [(string) $this->correctOptionId($questions[0])]]],
            ])
            ->assertRedirect(route('learning.my.quiz.show', [$enrollment, $attempt]))
            ->assertSessionHasErrors('answers');

        $this->assertNull($attempt->refresh()->submitted_at);
        $this->assertSame(1, $attempt->answers()->whereNotNull('payload')->count());

        $this->actingAs($user)
            ->post(route('learning.my.quiz.submit', [$enrollment, $attempt]), [
                'answers' => [
                    $questions[0]->id => ['option_ids' => [(string) $this->correctOptionId($questions[0])]],
                    $questions[1]->id => ['option_ids' => [(string) $this->correctOptionId($questions[1])]],
                ],
            ])
            ->assertRedirect(route('learning.my.quiz.show', [$enrollment, $attempt]));

        $this->assertNotNull($attempt->refresh()->submitted_at);
    }

    public function test_ergebnistext_folgt_der_hoechsten_erreichten_stufe(): void {
        [$enrollment, $quiz, $questions, $user] = $this->scenario([
            'result_messages' => [
                ['from_percent' => 0, 'text' => 'Bitte wiederholen.'],
                ['from_percent' => 50, 'text' => 'Bestanden.'],
                ['from_percent' => 90, 'text' => 'Ausgezeichnet.'],
            ],
        ]);
        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);
        $this->quizzes()->submitAttempt($attempt, [
            $questions[0]->id => ['option_ids' => [(string) $this->correctOptionId($questions[0])]],
        ]);

        $this->assertSame('Bestanden.', $quiz->resultMessageFor(50));
        $this->assertSame('Ausgezeichnet.', $quiz->resultMessageFor(100));
        $this->assertSame('Bitte wiederholen.', $quiz->resultMessageFor(10));

        $this->actingAs($user)
            ->get(route('learning.my.quiz.show', [$enrollment, $attempt]))
            ->assertOk()
            ->assertSee('Bestanden.');
    }

    public function test_einstellungen_des_ablaufs_werden_im_editor_gespeichert(): void {
        [, , , , $course, $unit] = $this->scenario();
        $author = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
        app(LearningCourseService::class)->reopen($course);

        $this->actingAs($author)
            ->put(route('learning.courses.units.quiz.update', [$course, $unit]), [
                'title' => 'Abschlussprüfung',
                'pass_percent' => 60,
                'max_attempts' => 2,
                'retry_wait_hours' => 0,
                'feedback_mode' => 'end',
                'display_mode' => 'single',
                'allow_back' => '1',
                'require_all_answered' => '1',
                'result_messages' => "90: Ausgezeichnet\n60 - Bestanden\nkeine Zahl",
            ])
            ->assertRedirect();

        $quiz = $unit->quiz()->firstOrFail();
        $this->assertSame('single', $quiz->display_mode);
        $this->assertTrue($quiz->allow_back);
        $this->assertFalse($quiz->allow_skip);
        $this->assertTrue($quiz->require_all_answered);
        $this->assertSame([
            ['from_percent' => 60, 'text' => 'Bestanden'],
            ['from_percent' => 90, 'text' => 'Ausgezeichnet'],
        ], $quiz->result_messages);
    }

    public function test_fragenweiser_modus_liefert_alle_fragen_im_html(): void {
        [$enrollment, $quiz, , $user] = $this->scenario(['display_mode' => 'single'], 3);
        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);

        // Ohne JavaScript bleibt die Prüfung bedienbar: alle Fragen stehen im Dokument.
        $this->actingAs($user)
            ->get(route('learning.my.quiz.show', [$enrollment, $attempt]))
            ->assertOk()
            ->assertSee('Frage 1')
            ->assertSee('Frage 3')
            ->assertSee(__('learning.action.next_question'))
            ->assertSee('data-quiz-runner', false);
    }

    public function test_fremde_frage_wird_nicht_zwischengespeichert(): void {
        [$enrollment, $quiz, , $user] = $this->scenario();
        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);

        $this->actingAs($user)
            ->patchJson(route('learning.my.quiz.answer', [$enrollment, $attempt]), [
                'question_id' => 999999,
                'payload' => ['text' => 'x'],
            ])
            ->assertStatus(422);
    }
}
