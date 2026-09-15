<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Models\Learning\{LearningCourse, LearningQuestion, LearningQuestionCategory, LearningQuiz, LearningQuizDrawRule, LearningUnit};
use App\Models\{Organization, User};
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningQuestionCatalogService, LearningQuestionEditorService, LearningQuizService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fragenkatalog (Feature 149, MVP-782): eine Frage in mehreren Prüfungen,
 * Ziehregeln je Kategorie, Löschen nur ohne Verwendung — und die
 * Prüfungsakte bleibt ein Snapshot, den keine Katalogänderung berührt.
 */
class LearningQuestionCatalogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function catalog(): LearningQuestionCatalogService {
        return app(LearningQuestionCatalogService::class);
    }

    private function editor(): LearningQuestionEditorService {
        return app(LearningQuestionEditorService::class);
    }

    private function author(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    /** @return array{0: LearningCourse, 1: LearningUnit, 2: LearningQuiz} */
    private function quiz(string $title = 'Prüfung', array $attributes = []): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Kurs ' . $title]);
        $courses->addUnit($course, ['title' => $title, 'kind' => LearningUnitKind::Quiz->value]);
        $unit = $course->refresh()->units()->firstOrFail();
        $quiz = LearningQuiz::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => $title,
            'pass_percent' => 50,
            'max_attempts' => 3,
            'shuffle_questions' => false,
        ], $attributes));

        return [$course->refresh(), $unit, $quiz];
    }

    private function question(string $prompt, ?LearningQuestionCategory $category = null): LearningQuestion {
        return $this->editor()->create($this->organization->id, [
            'kind' => 'single',
            'prompt' => $prompt,
            'points' => 2,
            'options' => "*richtig\nfalsch",
            'category_id' => $category?->id,
        ], null, null);
    }

    public function test_eine_katalogfrage_laeuft_in_zwei_pruefungen(): void {
        [, , $a] = $this->quiz('A');
        [, , $b] = $this->quiz('B');
        $question = $this->question('Notrufnummer?');

        $this->catalog()->attach($a, $question);
        $this->catalog()->attach($b, $question);
        $this->catalog()->attach($a, $question); // doppelt = No-Op

        $this->assertSame(1, $a->questions()->count());
        $this->assertSame(1, $b->questions()->count());
        $this->assertSame(2, $question->quizzes()->count());
    }

    public function test_aenderung_wirkt_auf_neue_versuche_nicht_auf_alte(): void {
        [$course, , $quiz] = $this->quiz();
        $question = $this->question('Notrufnummer?');
        $this->catalog()->attach($quiz, $question);
        app(LearningCourseService::class)->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $first = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);
        $this->assertSame('Notrufnummer?', $first->questions()[0]['prompt']);

        $this->editor()->update($question, ['kind' => 'single', 'prompt' => 'Neue Fassung?', 'points' => 2, 'options' => "*ja\nnein"], null, null);

        $this->assertSame('Notrufnummer?', $first->refresh()->questions()[0]['prompt'], 'Die Prüfungsakte bleibt eingefroren.');
        app(LearningQuizService::class)->submitAttempt($first, []);
        $second = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);
        $this->assertSame('Neue Fassung?', $second->questions()[0]['prompt']);
    }

    public function test_ziehregel_zieht_genau_n_fragen_der_kategorie(): void {
        [$course, , $quiz] = $this->quiz();
        $fire = $this->catalog()->createCategory($this->organization, 'Brandschutz');
        $aid = $this->catalog()->createCategory($this->organization, 'Erste Hilfe');
        foreach (range(1, 5) as $i) {
            $this->question('Brand ' . $i, $fire);
        }
        $this->question('Hilfe 1', $aid);
        $fixed = $this->question('Fest', $fire);
        $this->catalog()->attach($quiz, $fixed);
        $this->catalog()->setDrawRule($quiz, $fire, 3);
        $this->catalog()->setDrawRule($quiz, $aid, 1);

        app(LearningCourseService::class)->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $attempt = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);
        $prompts = array_map(fn (array $q): string => $q['prompt'], $attempt->questions());

        $this->assertCount(5, $prompts);
        $this->assertSame('Fest', $prompts[0]);
        $this->assertContains('Hilfe 1', $prompts);
        $this->assertCount(5, array_unique($prompts), 'Keine Frage doppelt.');
        $this->assertSame(3, count(array_filter($prompts, fn (string $p): bool => str_starts_with($p, 'Brand '))));
    }

    public function test_ziehregel_mit_zu_wenig_fragen_blockiert_start_und_anlage(): void {
        [$course, , $quiz] = $this->quiz();
        $fire = $this->catalog()->createCategory($this->organization, 'Brandschutz');
        $this->question('Brand 1', $fire);

        try {
            $this->catalog()->setDrawRule($quiz, $fire, 3);
            $this->fail('Eine Regel über den Bestand hinaus darf nicht gespeichert werden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('count', $e->errors());
        }

        $this->catalog()->setDrawRule($quiz, $fire, 1);
        LearningQuestion::query()->where('prompt', 'Brand 1')->firstOrFail()->delete();
        app(LearningCourseService::class)->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $this->expectException(ValidationException::class);
        app(LearningQuizService::class)->startAttempt($enrollment, $quiz);
    }

    public function test_ziehregel_nimmt_nur_fragen_der_eigenen_organisation(): void {
        [$course, , $quiz] = $this->quiz();
        $fire = $this->catalog()->createCategory($this->organization, 'Brandschutz');
        $this->question('Eigene', $fire);
        $this->question('Eigene 2', $fire);
        $foreign = Organization::factory()->create();
        $foreignCat = $this->catalog()->createCategory($foreign, 'Brandschutz');
        $this->editor()->create($foreign->id, ['kind' => 'single', 'prompt' => 'Fremd', 'points' => 1, 'options' => "*a\nb", 'category_id' => $foreignCat->id], null, null);

        $this->catalog()->setDrawRule($quiz, $fire, 2);
        app(LearningCourseService::class)->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $attempt = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);
        $this->assertNotContains('Fremd', array_map(fn (array $q): string => $q['prompt'], $attempt->questions()));

        $this->expectException(ValidationException::class);
        $this->catalog()->setDrawRule($quiz, $foreignCat, 1);
    }

    public function test_verwendete_frage_laesst_sich_nicht_loeschen_aber_entfernen(): void {
        [, , $quiz] = $this->quiz();
        $question = $this->question('Notrufnummer?');
        $this->catalog()->attach($quiz, $question);

        try {
            $this->catalog()->delete($question);
            $this->fail('Eine verwendete Frage darf nicht gelöscht werden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('question', $e->errors());
        }

        $this->catalog()->detach($quiz, $question);
        $this->assertSame(0, $quiz->questions()->count());
        $this->assertNotNull(LearningQuestion::query()->find($question->id), 'Entfernen aus der Prüfung löscht nichts im Katalog.');

        $this->catalog()->delete($question);
        $this->assertNull(LearningQuestion::query()->find($question->id));
    }

    public function test_kategorie_verschwindet_nur_leer(): void {
        $fire = $this->catalog()->createCategory($this->organization, 'Brandschutz');
        $question = $this->question('Brand', $fire);

        $this->expectException(ValidationException::class);
        $this->catalog()->deleteCategory($fire);
    }

    public function test_katalogseite_und_dialoge(): void {
        [$course, $unit, $quiz] = $this->quiz();
        $fire = $this->catalog()->createCategory($this->organization, 'Brandschutz');
        $question = $this->question('Wo hängt der Löscher?', $fire);
        $author = $this->author();

        $this->actingAs($author)
            ->get(route('learning.questions.index'))
            ->assertOk()
            ->assertSee('Wo hängt der Löscher?')
            ->assertSee('Brandschutz');

        $this->actingAs($author)
            ->post(route('learning.questions.store'), [
                'kind' => 'multiple',
                'title' => 'Löschmittel',
                'category_id' => $fire->sqid,
                'prompt' => 'Welche Löschmittel sind für Fettbrände geeignet?',
                'points' => 3,
                'options' => "*Löschdecke\nWasser",
            ])
            ->assertRedirect(route('learning.questions.index'));

        $created = LearningQuestion::query()->where('title', 'Löschmittel')->firstOrFail();
        $this->assertSame($fire->id, $created->learning_question_category_id);

        $this->actingAs($author)
            ->get(route('learning.courses.units.quiz.catalog', [$course, $unit]))
            ->assertOk()
            ->assertSee('Wo hängt der Löscher?')
            ->assertSee('Löschmittel');

        $this->actingAs($author)
            ->post(route('learning.courses.units.quiz.attach', [$course, $unit]), ['question_ids' => [$question->sqid, $created->sqid]])
            ->assertRedirect(route('learning.courses.units.quiz.edit', [$course, $unit]));
        $this->assertSame(2, $quiz->questions()->count());

        $this->actingAs($author)
            ->get(route('learning.courses.units.quiz.catalog', [$course, $unit]))
            ->assertOk()
            ->assertDontSee('Wo hängt der Löscher?');

        $this->actingAs($author)
            ->post(route('learning.courses.units.quiz.draw-rules.store', [$course, $unit]), ['category_id' => $fire->sqid, 'count' => 1])
            ->assertRedirect(route('learning.courses.units.quiz.edit', [$course, $unit]));
        $rule = LearningQuizDrawRule::query()->firstOrFail();

        $this->actingAs($author)
            ->get(route('learning.courses.units.quiz.edit', [$course, $unit]))
            ->assertOk()
            ->assertSee(__('learning.field.draw_rules'));

        $this->actingAs($author)
            ->delete(route('learning.courses.units.quiz.draw-rules.destroy', [$course, $unit, $rule]))
            ->assertRedirect();
        $this->assertSame(0, LearningQuizDrawRule::query()->count());
    }

    public function test_fremde_frage_ist_im_katalog_unsichtbar(): void {
        $foreign = Organization::factory()->create();
        $this->editor()->create($foreign->id, ['kind' => 'single', 'prompt' => 'Fremde Frage', 'points' => 1, 'options' => "*a\nb"], null, null);

        $this->actingAs($this->author())
            ->get(route('learning.questions.index'))
            ->assertOk()
            ->assertDontSee('Fremde Frage');
    }
}
