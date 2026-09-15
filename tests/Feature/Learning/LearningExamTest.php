<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningExamTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningCourseKind, LearningEnrollmentSource, LearningEnrollmentStatus, LearningQuestionKind, LearningUnitKind};
use App\Models\Learning\{LearningCertificate, LearningCourse, LearningEnrollment, LearningQuestion, LearningQuiz};
use App\Models\User;
use App\Services\Learning\{LearningCoursePortabilityService, LearningCourseService, LearningEnrollmentService, LearningQuestionCatalogService, LearningQuizService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Prüfung ohne Kurs und Voraussetzungen (Feature 149, MVP-784): Kursart
 * `exam` mit einer Prüfungseinheit, Bestehen rechnet den Zielkurs an;
 * Voraussetzungen sperren den Start, nie die Zuweisung — Pflicht umgeht sie.
 */
class LearningExamTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function courses(): LearningCourseService {
        return app(LearningCourseService::class);
    }

    private function learner(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    private function releasedCourse(string $title, array $attributes = []): LearningCourse {
        $course = $this->courses()->createCourse($this->organization, null, ['title' => $title] + $attributes);
        if (! $course->isExam()) {
            $this->courses()->addUnit($course, ['title' => 'Einheit']);
        }
        $this->courses()->release($course->refresh(), null);

        return $course->refresh();
    }

    /** Prüfung mit einer richtig zu beantwortenden Frage an die einzige Einheit hängen. */
    private function attachQuestion(LearningCourse $exam): LearningQuestion {
        $unit = $exam->units()->firstOrFail();
        $quiz = LearningQuiz::query()->create([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => $exam->title,
            'pass_percent' => 50,
            'max_attempts' => 2,
            'shuffle_answers' => false,
        ]);
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

        return $question->refresh();
    }

    public function test_pruefung_ohne_kurs_bekommt_genau_eine_pruefungseinheit(): void {
        $exam = $this->courses()->createCourse($this->organization, null, ['title' => 'Staplerschein Auffrischung', 'kind' => 'exam']);

        $this->assertSame(LearningCourseKind::Exam, $exam->kind);
        $this->assertSame(1, $exam->units()->count());
        $this->assertSame(LearningUnitKind::Quiz, $exam->units()->firstOrFail()->kind);
    }

    public function test_bestehen_rechnet_den_zielkurs_an(): void {
        $target = $this->releasedCourse('Staplerschein', ['certificate_enabled' => true]);
        $exam = $this->courses()->createCourse($this->organization, null, ['title' => 'Staplerschein Auffrischung', 'kind' => 'exam', 'exam_for_course_id' => $target->id]);
        $question = $this->attachQuestion($exam);
        $this->courses()->release($exam->refresh(), null);
        $learner = $this->learner();
        $enrollment = app(LearningEnrollmentService::class)->enroll($exam->refresh(), $learner);
        $quiz = $exam->units()->firstOrFail()->quiz()->firstOrFail();

        $attempt = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);
        app(LearningQuizService::class)->submitAttempt($attempt, [
            $question->id => ['option_ids' => [(string) $question->options()->where('is_correct', true)->firstOrFail()->id]],
        ]);

        $this->assertSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);

        $credited = LearningEnrollment::query()->where('learning_course_id', $target->id)->where('user_id', $learner->id)->firstOrFail();
        $this->assertSame(LearningEnrollmentSource::Exam, $credited->source);
        $this->assertSame(LearningEnrollmentStatus::Completed, $credited->status);
        $this->assertSame(1, LearningCertificate::query()->where('learning_course_id', $target->id)->count(), 'Die Anrechnung löst den regulären Rückfluss aus.');
    }

    public function test_durchfallen_rechnet_nichts_an(): void {
        $target = $this->releasedCourse('Staplerschein');
        $exam = $this->courses()->createCourse($this->organization, null, ['title' => 'Auffrischung', 'kind' => 'exam', 'exam_for_course_id' => $target->id]);
        $question = $this->attachQuestion($exam);
        $this->courses()->release($exam->refresh(), null);
        $enrollment = app(LearningEnrollmentService::class)->enroll($exam->refresh(), $this->learner());
        $quiz = $exam->units()->firstOrFail()->quiz()->firstOrFail();

        $attempt = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);
        app(LearningQuizService::class)->submitAttempt($attempt, [
            $question->id => ['option_ids' => [(string) $question->options()->where('is_correct', false)->firstOrFail()->id]],
        ]);

        $this->assertSame(0, LearningEnrollment::query()->where('learning_course_id', $target->id)->count());
    }

    public function test_voraussetzung_sperrt_den_start_aber_nicht_die_zuweisung(): void {
        $basics = $this->releasedCourse('Grundlagen');
        $advanced = $this->courses()->createCourse($this->organization, null, ['title' => 'Aufbau', 'prerequisite_course_ids' => [$basics->id]]);
        $this->courses()->addUnit($advanced, ['title' => 'Einheit']);
        $this->courses()->release($advanced->refresh(), null);
        $learner = $this->learner();
        $enrollments = app(LearningEnrollmentService::class);

        $enrollment = $enrollments->enroll($advanced->refresh(), $learner);
        $this->assertSame(LearningEnrollmentStatus::Assigned, $enrollment->status);
        $this->assertCount(1, $enrollments->missingPrerequisites($enrollment));

        try {
            $enrollments->start($enrollment);
            $this->fail('Ohne Voraussetzung darf der Start nicht gelingen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('prerequisites', $e->errors());
        }

        $this->actingAs($learner)
            ->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertSee('Grundlagen')
            ->assertSee(__('learning.field.prerequisites_missing'));

        $basic = $enrollments->enroll($basics, $learner);
        $enrollments->completeUnit($basic, $basics->units()->firstOrFail());

        $this->assertSame([], $enrollments->missingPrerequisites($enrollment->refresh()));
        $this->assertSame(LearningEnrollmentStatus::InProgress, $enrollments->start($enrollment->refresh())->status);
    }

    public function test_eine_von_mehreren_voraussetzungen_genuegt_im_modus_any(): void {
        $a = $this->releasedCourse('A');
        $b = $this->releasedCourse('B');
        $course = $this->courses()->createCourse($this->organization, null, ['title' => 'Aufbau', 'prerequisite_mode' => 'any', 'prerequisite_course_ids' => [$a->id, $b->id]]);
        $this->courses()->addUnit($course, ['title' => 'Einheit']);
        $this->courses()->release($course->refresh(), null);
        $learner = $this->learner();
        $enrollments = app(LearningEnrollmentService::class);
        $enrollment = $enrollments->enroll($course->refresh(), $learner);
        $this->assertCount(2, $enrollments->missingPrerequisites($enrollment));

        $enrollments->completeUnit($enrollments->enroll($b, $learner), $b->units()->firstOrFail());

        $this->assertSame([], $enrollments->missingPrerequisites($enrollment->refresh()));
    }

    public function test_pflicht_einschreibung_umgeht_voraussetzungen(): void {
        $basics = $this->releasedCourse('Grundlagen');
        $advanced = $this->courses()->createCourse($this->organization, null, ['title' => 'Aufbau', 'prerequisite_course_ids' => [$basics->id]]);
        $this->courses()->addUnit($advanced, ['title' => 'Einheit']);
        $this->courses()->release($advanced->refresh(), null);
        $enrollments = app(LearningEnrollmentService::class);

        $enrollment = $enrollments->enroll($advanced->refresh(), $this->learner(), ['source' => LearningEnrollmentSource::Requirement->value]);

        $this->assertSame([], $enrollments->missingPrerequisites($enrollment));
        $this->assertSame(LearningEnrollmentStatus::InProgress, $enrollments->start($enrollment)->status);
    }

    public function test_kurs_kann_nicht_seine_eigene_voraussetzung_sein(): void {
        $course = $this->courses()->createCourse($this->organization, null, ['title' => 'Selbst']);

        $this->courses()->syncPrerequisites($course, [$course->id]);

        $this->assertSame(0, $course->prerequisites()->count());
    }

    public function test_export_und_import_tragen_art_und_voraussetzungen(): void {
        $basics = $this->releasedCourse('Grundlagen');
        $exam = $this->courses()->createCourse($this->organization, null, ['title' => 'Auffrischung', 'kind' => 'exam', 'exam_for_course_id' => $basics->id, 'prerequisite_course_ids' => [$basics->id]]);

        $data = app(LearningCoursePortabilityService::class)->export($exam->refresh());
        $this->assertSame('exam', $data['course']['kind']);
        $this->assertSame($basics->code, $data['course']['exam_for_code']);
        $this->assertSame([$basics->code], $data['course']['prerequisite_codes']);

        $imported = app(LearningCoursePortabilityService::class)->import($this->organization, $data, null);
        $this->assertSame(LearningCourseKind::Exam, $imported->kind);
        $this->assertSame($basics->id, $imported->exam_for_course_id);
        $this->assertSame([$basics->id], $imported->prerequisites()->pluck('learning_courses.id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(1, $imported->units()->count(), 'Der Import legt die Prüfungseinheit nicht doppelt an.');
    }

    public function test_katalog_kennzeichnet_pruefungen(): void {
        $this->courses()->createCourse($this->organization, null, ['title' => 'Auffrischung', 'kind' => 'exam']);
        $author = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($author)
            ->get(route('learning.courses.index', ['kind' => 'exam']))
            ->assertOk()
            ->assertSee('Auffrischung')
            ->assertSee(LearningCourseKind::Exam->label());
    }
}
