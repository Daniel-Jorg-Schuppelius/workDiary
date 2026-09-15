<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionEditingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningQuestionKind, LearningUnitKind};
use App\Models\Learning\{LearningCourse, LearningQuiz, LearningUnit};
use App\Models\User;
use App\Services\Learning\{LearningAnswerGrader, LearningCourseService, LearningQuestionEditorService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fragen bearbeiten, kopieren, sortieren; Einheiten und Abschnitte
 * verschieben (Feature 149, MVP-779). Kern: die Zeilen-Syntax muss in beide
 * Richtungen laufen — parse(toLines(parse(x))) ist parse(x).
 */
class LearningQuestionEditingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function author(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    /** @return array{0: LearningCourse, 1: LearningUnit, 2: LearningQuiz} */
    private function quiz(): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);
        $courses->addUnit($course, ['title' => 'Abschlussprüfung', 'kind' => LearningUnitKind::Quiz->value]);
        $unit = $course->refresh()->units()->firstOrFail();

        $quiz = LearningQuiz::query()->create([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => 'Abschlussprüfung',
            'pass_percent' => 50,
            'max_attempts' => 2,
        ]);

        return [$course->refresh(), $unit, $quiz];
    }

    /**
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function kindsProvider(): array {
        return [
            ['single', "*112\n110\n116117", []],
            ['multiple', "*Löschdecke\n*Feuerlöscher\nWasser", ['partial_credit' => '1']],
            ['true_false', "*Wahr\nFalsch", []],
            ['short_text', "Notruf\n112", ['case_sensitive' => '1']],
            ['cloze', "Feuer | Brand\n112", []],
            ['sort', "Alarmieren\nRetten\nLöschen", []],
            ['matching', "Feuer = Löscher\nSchnitt = Pflaster", []],
            ['matrix', "Löscher = Brand\nPflaster = Wunde\nDecke = Brand", []],
            ['essay', '', []],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    #[DataProvider('kindsProvider')]
    public function test_zeilen_syntax_laeuft_in_beide_richtungen(string $kind, string $lines, array $extra): void {
        [, , $quiz] = $this->quiz();
        $editor = app(LearningQuestionEditorService::class);
        $data = ['kind' => $kind, 'prompt' => 'Frage ' . $kind, 'points' => 3, 'options' => $lines] + $extra;

        $question = $editor->create($this->organization->id, $data, null, null, $quiz);
        $roundTrip = $editor->toLines($question);
        $enumKind = LearningQuestionKind::from($kind);

        $this->assertSame(
            $editor->parse($enumKind, $data, LearningQuestionEditorService::linesOf($lines)),
            $editor->parse($enumKind, $data, LearningQuestionEditorService::linesOf($roundTrip)),
            'toLines() muss den Parser-Zustand unverändert wiedergeben (' . $kind . ')'
        );
    }

    public function test_bildmarkierung_laeuft_in_beide_richtungen(): void {
        [, , $quiz] = $this->quiz();
        $editor = app(LearningQuestionEditorService::class);
        $lines = "*10,20,30,15: Sicherungskasten\n60,60,20,20: Ablenker";
        $data = ['kind' => 'hotspot', 'prompt' => 'Wo ist der Sicherungskasten?', 'points' => 2, 'options' => $lines];

        $question = $editor->create($this->organization->id, $data, null, null, $quiz);

        $this->assertSame($lines, $editor->toLines($question));
    }

    public function test_bearbeiten_ersetzt_optionen_und_bleibt_bewertbar(): void {
        [$course, $unit, $quiz] = $this->quiz();
        $editor = app(LearningQuestionEditorService::class);
        $question = $editor->create($this->organization->id, ['kind' => 'single', 'prompt' => 'Notrufnummer?', 'points' => 2, 'options' => "*112\n110"], null, null, $quiz);
        $author = $this->author();

        $this->actingAs($author)
            ->get(route('learning.courses.units.quiz.questions.edit', [$course, $unit, $question]))
            ->assertOk()
            ->assertSee('*112')
            ->assertSee(__('learning.field.editing_question'));

        $this->actingAs($author)
            ->put(route('learning.courses.units.quiz.questions.update', [$course, $unit, $question]), [
                'kind' => 'multiple',
                'prompt' => 'Welche Nummern erreichen die Feuerwehr?',
                'points' => 4,
                'options' => "*112\n110\n*19222",
                'partial_credit' => '1',
            ])
            ->assertRedirect(route('learning.courses.units.quiz.edit', [$course, $unit]));

        $question->refresh();
        $this->assertSame(LearningQuestionKind::Multiple, $question->kind);
        $this->assertSame(4, $question->points);
        $this->assertSame(['112', '110', '19222'], $question->options()->orderBy('position')->pluck('label')->all());
        $this->assertTrue((bool) ($question->settings['partial_credit'] ?? false));

        $correctIds = $question->options()->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $snapshot = [
            'id' => $question->id,
            'kind' => 'multiple',
            'points' => 4,
            'settings' => $question->settings,
            'options' => $question->options()->orderBy('position')->get()->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'is_correct' => $o->is_correct, 'match_key' => $o->match_key])->all(),
        ];
        $result = app(LearningAnswerGrader::class)->grade($snapshot, ['option_ids' => $correctIds]);
        $this->assertSame(4, (int) $result['points']);
    }

    public function test_freigegebener_kurs_laesst_keine_frage_mehr_aendern(): void {
        [$course, $unit, $quiz] = $this->quiz();
        $editor = app(LearningQuestionEditorService::class);
        $question = $editor->create($this->organization->id, ['kind' => 'single', 'prompt' => 'Notrufnummer?', 'points' => 2, 'options' => "*112\n110"], null, null, $quiz);
        app(LearningCourseService::class)->release($course->refresh(), null);

        $this->actingAs($this->author())
            ->put(route('learning.courses.units.quiz.questions.update', [$course, $unit, $question]), [
                'kind' => 'single',
                'prompt' => 'Geändert',
                'points' => 2,
                'options' => "*112\n110",
            ])
            ->assertForbidden();
    }

    public function test_kopie_ist_unabhaengig_und_haengt_hinten_an(): void {
        Storage::fake('local');
        [$course, $unit, $quiz] = $this->quiz();
        $editor = app(LearningQuestionEditorService::class);
        $first = $editor->create($this->organization->id, ['kind' => 'single', 'prompt' => 'Erste', 'points' => 1, 'options' => "*a\nb"], null, null, $quiz);
        $image = $editor->create(
            $this->organization->id,
            ['kind' => 'hotspot', 'prompt' => 'Bild', 'points' => 2, 'options' => '*10,10,20,20: Ziel'],
            UploadedFile::fake()->image('plan.png', 200, 100),
            $this->author(),
            $quiz,
        );

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.quiz.questions.duplicate', [$course, $unit, $image]))
            ->assertRedirect(route('learning.courses.units.quiz.edit', [$course, $unit]));

        $copy = $quiz->questions()->get()->last();
        $this->assertNotNull($copy);
        $this->assertNotSame($image->id, $copy->id);
        $this->assertSame(3, (int) $quiz->questions()->whereKey($copy->id)->firstOrFail()->pivot->position);
        $this->assertSame('Bild', $copy->prompt);
        $this->assertSame($image->settings['hotspots'], $copy->settings['hotspots']);
        $this->assertNotNull($copy->settings['image_attachment_id'] ?? null);
        $this->assertNotSame($image->settings['image_attachment_id'], $copy->settings['image_attachment_id']);

        $image->delete();
        $this->assertNotNull($copy->refresh()->settings['image_attachment_id']);
        $this->assertSame(1, (int) $quiz->questions()->whereKey($first->id)->firstOrFail()->pivot->position);
    }

    public function test_verschieben_tauscht_mit_dem_nachbarn(): void {
        [$course, $unit, $quiz] = $this->quiz();
        $editor = app(LearningQuestionEditorService::class);
        $a = $editor->create($this->organization->id, ['kind' => 'single', 'prompt' => 'A', 'points' => 1, 'options' => "*a\nb"], null, null, $quiz);
        $b = $editor->create($this->organization->id, ['kind' => 'single', 'prompt' => 'B', 'points' => 1, 'options' => "*a\nb"], null, null, $quiz);
        $c = $editor->create($this->organization->id, ['kind' => 'single', 'prompt' => 'C', 'points' => 1, 'options' => "*a\nb"], null, null, $quiz);

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.quiz.questions.move', [$course, $unit, $c]), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(['A', 'C', 'B'], $quiz->refresh()->questions()->pluck('prompt')->all());

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.quiz.questions.move', [$course, $unit, $a]), ['direction' => 'up'])
            ->assertSessionHasErrors('question');

        $this->assertSame([1, 2, 3], $quiz->refresh()->questions()->get()->map(fn ($q) => (int) $q->pivot->position)->all());
        $this->assertSame($b->id, $quiz->questions()->orderBy('position')->get()->last()->id);
    }

    public function test_einheiten_und_abschnitte_lassen_sich_verschieben(): void {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Struktur']);
        $courses->addSection($course, ['title' => 'Teil 1']);
        $second = $courses->addSection($course, ['title' => 'Teil 2']);
        $courses->addUnit($course, ['title' => 'Einheit 1']);
        $courses->addUnit($course, ['title' => 'Einheit 2']);
        $third = $courses->addUnit($course, ['title' => 'Einheit 3']);
        $course->refresh();

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.move', [$course, $third]), ['direction' => 'up'])
            ->assertRedirect(route('learning.courses.show', $course));
        $this->assertSame(['Einheit 1', 'Einheit 3', 'Einheit 2'], $course->units()->pluck('title')->all());

        $this->actingAs($this->author())
            ->post(route('learning.courses.sections.move', [$course, $second]), ['direction' => 'up'])
            ->assertRedirect(route('learning.courses.show', $course));
        $this->assertSame(['Teil 2', 'Teil 1'], $course->sections()->pluck('title')->all());

        $this->actingAs($this->author())
            ->post(route('learning.courses.sections.move', [$course, $second]), ['direction' => 'up'])
            ->assertSessionHasErrors('section');
    }

    public function test_fremde_einheit_kann_nicht_verschoben_werden(): void {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'A']);
        $other = $courses->createCourse($this->organization, null, ['title' => 'B']);
        $unit = $courses->addUnit($other, ['title' => 'Fremd']);

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.move', [$course, $unit]), ['direction' => 'up'])
            ->assertNotFound();
    }
}
