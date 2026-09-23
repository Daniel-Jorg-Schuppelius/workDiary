<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionRefinementsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningQuestionKind, LearningUnitKind};
use App\Models\Learning\{LearningCourse, LearningQuestion, LearningQuiz, LearningUnit};
use App\Models\Platform\User;
use App\Services\Learning\{LearningAnswerGrader, LearningCourseService, LearningEnrollmentService, LearningQuestionEditorService, LearningQuizService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fragen-Feinheiten (Feature 149, MVP-793): Punkte je Option (Einfach- und
 * Mehrfachauswahl), Selbsteinschätzung als Skala, Bestehen in Punkten,
 * Prozent-Teilmenge je Versuch und der Aufsatz-Upload bis in die Bewertung.
 */
class LearningQuestionRefinementsTest extends TestCase {
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

    /** @return array{0: LearningCourse, 1: LearningUnit, 2: LearningQuiz} */
    private function quiz(array $quizAttributes = []): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        $courses->addUnit($course, ['title' => 'Prüfung', 'kind' => LearningUnitKind::Quiz->value]);
        $unit = $course->refresh()->units()->firstOrFail();
        $quiz = LearningQuiz::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => 'Prüfung',
            'pass_percent' => 50,
            'max_attempts' => 3,
        ], $quizAttributes));

        return [$course, $unit, $quiz];
    }

    private function question(LearningQuiz $quiz, string $kind, string $options, int $points = 2, array $extra = []): LearningQuestion {
        return app(LearningQuestionEditorService::class)->create($this->organization->id, array_merge([
            'kind' => $kind,
            'prompt' => 'Frage ' . $kind,
            'points' => $points,
            'options' => $options,
        ], $extra), null, null, $quiz);
    }

    public function test_punkte_je_option_gelten_fuer_einfach_und_mehrfachauswahl(): void {
        [, , $quiz] = $this->quiz();
        $editor = app(LearningQuestionEditorService::class);
        $single = $this->question($quiz, 'single', "*Ganz richtig {4}\nHalb richtig {2}\nFalsch", 4);
        $multiple = $this->question($quiz, 'multiple', "*A {2}\n*B {2}\nZ {-3}", 4);

        // Round-Trip: Punkte bleiben in der Zeilensyntax erhalten.
        $this->assertSame("*Ganz richtig {4}\nHalb richtig {2}\nFalsch", $editor->toLines($single));
        $this->assertSame([4, 2, null], $single->options()->orderBy('position')->pluck('points')->all());

        $grader = app(LearningAnswerGrader::class);
        $snapshot = fn (LearningQuestion $q): array => [
            'id' => $q->id, 'kind' => $q->kind->value, 'points' => $q->points, 'settings' => $q->settings,
            'options' => $q->options()->orderBy('position')->get()->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'is_correct' => $o->is_correct, 'position' => $o->position, 'match_key' => $o->match_key, 'points' => $o->points])->all(),
        ];
        [$right, $half, $wrong] = $single->options()->orderBy('position')->pluck('id')->all();
        $this->assertSame(['correct' => true, 'points' => 4], $grader->grade($snapshot($single), ['option_ids' => [$right]]));
        $this->assertSame(['correct' => false, 'points' => 2], $grader->grade($snapshot($single), ['option_ids' => [$half]]), 'Halb richtig bringt eigene Punkte.');
        $this->assertSame(['correct' => false, 'points' => 0], $grader->grade($snapshot($single), ['option_ids' => [$wrong]]));

        [$a, $b, $z] = $multiple->options()->orderBy('position')->pluck('id')->all();
        $this->assertSame(['correct' => true, 'points' => 4], $grader->grade($snapshot($multiple), ['option_ids' => [$a, $b]]));
        $this->assertSame(['correct' => false, 'points' => 1], $grader->grade($snapshot($multiple), ['option_ids' => [$a, $b, $z]]), '2 + 2 − 3 = 1');
        $this->assertSame(['correct' => false, 'points' => 0], $grader->grade($snapshot($multiple), ['option_ids' => [$z]]), 'nie unter null');
    }

    public function test_selbsteinschaetzung_zaehlt_die_gewaehlte_stufe(): void {
        [, , $quiz] = $this->quiz();
        $question = $this->question($quiz, 'assessment', "unsicher\nmittel\nsicher", 3);

        $this->assertSame(LearningQuestionKind::Assessment, $question->kind);
        $this->assertSame(['unsicher', 'mittel', 'sicher'], $question->settings['scale']);
        $this->assertSame("unsicher\nmittel\nsicher", app(LearningQuestionEditorService::class)->toLines($question));
        $this->assertSame(0, $question->options()->count());

        $grader = app(LearningAnswerGrader::class);
        $snapshot = ['id' => $question->id, 'kind' => 'assessment', 'points' => 3, 'settings' => $question->settings, 'options' => []];
        $this->assertSame(['correct' => true, 'points' => 2], $grader->grade($snapshot, ['level' => 2]));
        $this->assertSame(['correct' => true, 'points' => 3], $grader->grade($snapshot, ['level' => 3]));
        $this->assertSame(['correct' => false, 'points' => 0], $grader->grade($snapshot, ['level' => 9]));
    }

    public function test_bestehen_in_punkten_und_prozent_teilmenge(): void {
        [$course, , $quiz] = $this->quiz(['pass_percent' => 50, 'pass_points' => 3, 'questions_per_attempt_percent' => 50]);
        for ($i = 1; $i <= 4; $i++) {
            $this->question($quiz, 'single', "*Richtig\nFalsch", 2, ['prompt' => 'Frage ' . $i]);
        }
        app(LearningCourseService::class)->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $service = app(LearningQuizService::class);
        $attempt = $service->startAttempt($enrollment, $quiz);
        $questions = $attempt->questions();
        $this->assertCount(2, $questions, '50 % von 4 Fragen');

        // Eine von zwei richtig: 50 % erreicht, aber 2 < 3 Punkte ⇒ nicht bestanden.
        $answers = [];
        foreach ($questions as $index => $question) {
            $correct = collect($question['options'])->firstWhere('is_correct', true);
            $wrong = collect($question['options'])->firstWhere('is_correct', false);
            $answers[(int) $question['id']] = ['option_ids' => [(int) ($index === 0 ? $correct['id'] : $wrong['id'])]];
        }
        $submitted = $service->submitAttempt($attempt, $answers);
        $this->assertSame(50, $submitted->score_percent);
        $this->assertFalse($submitted->passed);

        // Beide richtig: 4 Punkte ≥ 3 ⇒ bestanden.
        $second = $service->startAttempt($enrollment->refresh(), $quiz);
        $answers = [];
        foreach ($second->questions() as $question) {
            $answers[(int) $question['id']] = ['option_ids' => [(int) collect($question['options'])->firstWhere('is_correct', true)['id']]];
        }
        $this->assertTrue($service->submitAttempt($second, $answers)->passed);
    }

    public function test_aufsatz_upload_haengt_an_der_antwort_und_ist_in_der_bewertung_abrufbar(): void {
        Storage::fake('local');
        [$course, , $quiz] = $this->quiz(['require_all_answered' => true]);
        $essay = $this->question($quiz, 'essay', '', 5, ['submission_kind' => 'upload']);
        $this->assertSame('upload', $essay->settings['submission_kind']);
        app(LearningCourseService::class)->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);
        $attempt = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);

        $this->actingAs($learner)
            ->get(route('learning.my.quiz.show', [$enrollment, $attempt]))
            ->assertOk()
            ->assertSee('answers[' . $essay->id . '][file]', false)
            ->assertDontSee('answers[' . $essay->id . '][text]', false);

        $this->actingAs($learner)
            ->post(route('learning.my.quiz.submit', [$enrollment, $attempt]), [
                'answers' => [$essay->id => ['file' => UploadedFile::fake()->create('aufsatz.pdf', 30, 'application/pdf')]],
            ])
            ->assertRedirect(route('learning.my.quiz.show', [$enrollment, $attempt]))
            ->assertSessionHasNoErrors();

        $answer = $attempt->refresh()->answers()->firstOrFail();
        $this->assertNull($answer->is_correct);
        $this->assertSame('aufsatz.pdf', $answer->payload['file']);
        $this->assertSame(1, $answer->attachments()->count());
        $attachment = $answer->attachments()->firstOrFail();

        $grader = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($grader)
            ->get(route('learning.grading.index'))
            ->assertOk()
            ->assertSee(route('learning.grading.essay.file', [$answer, $attachment]));
        $this->actingAs($grader)
            ->get(route('learning.grading.essay.file', [$answer, $attachment]))
            ->assertOk();
    }

    public function test_bewertungsdatei_bleibt_ohne_recht_verschlossen(): void {
        Storage::fake('local');
        [$course, , $quiz] = $this->quiz();
        $essay = $this->question($quiz, 'essay', '', 5, ['submission_kind' => 'both']);
        app(LearningCourseService::class)->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);
        $attempt = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);
        $this->actingAs($learner)->post(route('learning.my.quiz.submit', [$enrollment, $attempt]), [
            'answers' => [$essay->id => ['text' => 'Mein Text.', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]],
        ]);
        $answer = $attempt->refresh()->answers()->firstOrFail();
        $attachment = $answer->attachments()->firstOrFail();

        $this->assertSame('Mein Text.', $answer->payload['text']);
        $this->actingAs($learner)->get(route('learning.grading.essay.file', [$answer, $attachment]))->assertForbidden();
    }
}
