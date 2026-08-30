<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuizTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningEnrollmentStatus, LearningQuestionKind, LearningUnitKind};
use App\Models\Learning\{LearningQuestion, LearningQuiz};
use App\Models\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningQuizService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Prüfungskern (Feature 149, MVP-738): eingefrorene Prüfungsakte,
 * Versuchsgrenze, automatische Bewertung, additive Korrektur und der
 * Abschluss der Lerneinheit bei bestandener Prüfung.
 */
class LearningQuizTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        // Garantierter Reset: bleibt eine Testzeit stehen, sieht der nächste
        // Test im selben Worker eine falsche Systemzeit — das äußert sich
        // später als scheinbar zufälliger Fehler in einem fremden Test.
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function quizzes(): LearningQuizService {
        return app(LearningQuizService::class);
    }

    /** Kurs mit einer Prüfungs-Einheit und einer Einfachauswahl-Frage. */
    private function scenario(array $quizAttributes = []): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);
        $courses->addUnit($course, ['title' => 'Abschlussprüfung', 'kind' => LearningUnitKind::Quiz->value]);
        $unit = $course->refresh()->units()->firstOrFail();

        $quiz = LearningQuiz::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => 'Abschlussprüfung',
            'pass_percent' => 50,
            'max_attempts' => 2,
        ], $quizAttributes));

        $question = LearningQuestion::query()->create([
            'organization_id' => $this->organization->id,
            'learning_quiz_id' => $quiz->id,
            'kind' => LearningQuestionKind::Single->value,
            'prompt' => 'Welche Nummer hat die Feuerwehr?',
            'points' => 2,
            'position' => 1,
        ]);
        $right = $question->options()->create([
            'organization_id' => $this->organization->id,
            'label' => '112',
            'is_correct' => true,
            'position' => 1,
        ]);
        $question->options()->create([
            'organization_id' => $this->organization->id,
            'label' => '110',
            'position' => 2,
        ]);

        $courses->release($course->refresh(), null);

        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $user);

        return [$enrollment, $quiz->refresh(), $question->refresh(), $right, $unit, $user];
    }

    public function test_versuch_friert_die_fragen_ein(): void {
        [$enrollment, $quiz, $question] = $this->scenario();

        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);
        $snapshot = $attempt->questions();

        $this->assertCount(1, $snapshot);
        $this->assertSame($question->id, $snapshot[0]['id']);
        $this->assertSame(2, $attempt->max_points);

        // Spätere Änderung der Frage lässt den laufenden Versuch unberührt.
        $question->update(['prompt' => 'Ganz andere Frage', 'points' => 99]);
        $this->assertSame('Welche Nummer hat die Feuerwehr?', $attempt->refresh()->questions()[0]['prompt']);
        $this->assertSame(2, $attempt->max_points);
    }

    public function test_bestandene_pruefung_schliesst_die_lerneinheit_ab(): void {
        [$enrollment, $quiz, $question, $right] = $this->scenario();

        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);
        $attempt = $this->quizzes()->submitAttempt($attempt, [
            $question->id => ['option_ids' => [$right->id]],
        ]);

        $this->assertTrue($attempt->passed);
        $this->assertSame(100, $attempt->score_percent);
        $this->assertSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_nicht_bestandene_pruefung_schliesst_nichts_ab(): void {
        [$enrollment, $quiz, $question] = $this->scenario();
        $wrong = $question->options()->where('is_correct', false)->firstOrFail();

        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);
        $attempt = $this->quizzes()->submitAttempt($attempt, [
            $question->id => ['option_ids' => [$wrong->id]],
        ]);

        $this->assertFalse($attempt->passed);
        $this->assertSame(0, $attempt->score_percent);
        $this->assertNotSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_versuchsgrenze_wird_vor_dem_start_geprueft(): void {
        [$enrollment, $quiz, $question] = $this->scenario(['max_attempts' => 1]);
        $wrong = $question->options()->where('is_correct', false)->firstOrFail();

        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);
        $this->quizzes()->submitAttempt($attempt, [$question->id => ['option_ids' => [$wrong->id]]]);

        $this->expectException(ValidationException::class);
        $this->quizzes()->startAttempt($enrollment->refresh(), $quiz);
    }

    public function test_sperrfrist_zwischen_versuchen_wird_eingehalten(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));
        [$enrollment, $quiz, $question] = $this->scenario(['max_attempts' => 3, 'retry_wait_hours' => 24]);
        $wrong = $question->options()->where('is_correct', false)->firstOrFail();

        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);
        $this->quizzes()->submitAttempt($attempt, [$question->id => ['option_ids' => [$wrong->id]]]);

        try {
            $this->expectException(ValidationException::class);
            $this->quizzes()->startAttempt($enrollment->refresh(), $quiz);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_zweiter_start_liefert_den_offenen_versuch(): void {
        [$enrollment, $quiz] = $this->scenario();

        $first = $this->quizzes()->startAttempt($enrollment, $quiz);
        $second = $this->quizzes()->startAttempt($enrollment, $quiz);

        $this->assertSame($first->id, $second->id);
    }

    public function test_aufsatz_haelt_das_ergebnis_offen_bis_zur_bewertung(): void {
        [$enrollment, $quiz] = $this->scenario();
        $essay = LearningQuestion::query()->create([
            'organization_id' => $this->organization->id,
            'learning_quiz_id' => $quiz->id,
            'kind' => LearningQuestionKind::Essay->value,
            'prompt' => 'Beschreiben Sie den Ablauf einer Evakuierung.',
            'points' => 2,
            'position' => 2,
        ]);

        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);
        $attempt = $this->quizzes()->submitAttempt($attempt, [
            $essay->id => ['text' => 'Zuerst Menschen retten, dann melden.'],
        ]);

        $this->assertNull($attempt->passed, 'Solange ein Aufsatz offen ist, steht das Ergebnis nicht fest.');

        $answer = $attempt->answers()->where('learning_question_id', $essay->id)->firstOrFail();
        $grader = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
        $this->quizzes()->correctAnswer($answer, 2, 'Vollständig', $grader);

        $attempt->refresh();
        $this->assertNotNull($attempt->passed);
        $this->assertSame(2, $answer->refresh()->corrected_points);
        $this->assertSame(0, $answer->points_awarded, 'Der Automatikwert bleibt zur Nachvollziehbarkeit stehen.');
    }

    public function test_pruefung_ohne_fragen_kann_nicht_gestartet_werden(): void {
        [$enrollment, $quiz, $question] = $this->scenario();
        $question->delete();

        $this->expectException(ValidationException::class);
        $this->quizzes()->startAttempt($enrollment, $quiz->refresh());
    }

    public function test_abgegebener_versuch_wird_nicht_erneut_abgegeben(): void {
        [$enrollment, $quiz, $question, $right] = $this->scenario();

        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);
        $this->quizzes()->submitAttempt($attempt, [$question->id => ['option_ids' => [$right->id]]]);

        $this->expectException(ValidationException::class);
        $this->quizzes()->submitAttempt($attempt->refresh(), []);
    }

    // ── Lückentext, Sortieren, Zuordnung (MVP-738) ──────────────────────
    //
    // Diese drei Typen liefen zuvor ins Leere: der Editor schrieb eine
    // andere Datenform, als der Bewerter liest, und die Prüfungsansicht bot
    // nur ein einzelnes Textfeld. Beantwortbar waren sie damit nicht.

    /** Frage über die Autoren-Oberfläche anlegen — wie eine echte Person. */
    private function addQuestionViaEditor(string $kind, string $prompt, string $options, int $points = 4): LearningQuestion {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);
        $courses->addUnit($course, ['title' => 'Abschlussprüfung', 'kind' => LearningUnitKind::Quiz->value]);
        $unit = $course->refresh()->units()->firstOrFail();

        LearningQuiz::query()->create([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => 'Abschlussprüfung',
            'pass_percent' => 50,
            'max_attempts' => 2,
        ]);

        $author = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($author)
            ->post(route('learning.courses.units.quiz.questions.store', [$course->sqid, $unit->sqid]), [
                'kind' => $kind,
                'prompt' => $prompt,
                'points' => $points,
                'options' => $options,
            ])
            ->assertRedirect();

        return LearningQuestion::query()->where('prompt', $prompt)->firstOrFail();
    }

    public function test_lueckentext_aus_dem_editor_ist_bewertbar(): void {
        $question = $this->addQuestionViaEditor(
            LearningQuestionKind::Cloze->value,
            'Der :: ist freizuhalten, Breite :: cm.',
            "Fluchtweg | Rettungsweg\n120",
        );

        // Der Bewerter liest `gaps` — schrieb der Editor `answers`, blieben
        // die Lücken leer und die Frage gäbe immer null Punkte.
        $this->assertSame(
            [['Fluchtweg', 'Rettungsweg'], ['120']],
            $question->settings['gaps'] ?? null
        );

        $grader = app(\App\Services\Learning\LearningAnswerGrader::class);
        $snapshot = [
            'kind' => LearningQuestionKind::Cloze->value,
            'points' => 4,
            'settings' => $question->settings,
            'options' => [],
        ];

        // Beide Alternativen zählen, Teilpunkte je Lücke.
        $this->assertSame(4, $grader->grade($snapshot, ['gaps' => ['Rettungsweg', '120']])['points']);
        $this->assertSame(2, $grader->grade($snapshot, ['gaps' => ['Fluchtweg', '90']])['points']);
    }

    public function test_zuordnung_aus_dem_editor_bekommt_paarschluessel(): void {
        $question = $this->addQuestionViaEditor(
            LearningQuestionKind::Matching->value,
            'Ordnen Sie Löscher und Brandklasse zu.',
            "Wasserlöscher = Brandklasse A\nCO2-Löscher = Brandklasse B",
        );

        $options = $question->options()->orderBy('position')->get();

        // Ohne match_key findet der Bewerter kein einziges Paar.
        $this->assertCount(4, $options);
        $this->assertSame($options[0]->match_key, $options[1]->match_key);
        $this->assertSame($options[2]->match_key, $options[3]->match_key);
        $this->assertNotSame($options[0]->match_key, $options[2]->match_key);

        $snapshot = [
            'kind' => LearningQuestionKind::Matching->value,
            'points' => 4,
            'settings' => null,
            'options' => $options->map(fn ($o): array => [
                'id' => $o->id, 'label' => $o->label, 'is_correct' => $o->is_correct,
                'position' => $o->position, 'match_key' => $o->match_key,
            ])->all(),
        ];

        $grader = app(\App\Services\Learning\LearningAnswerGrader::class);

        $right = $grader->grade($snapshot, ['pairs' => [$options[0]->id => $options[1]->id, $options[2]->id => $options[3]->id]]);
        $this->assertSame(4, $right['points']);

        // Vertauscht: kein Paar stimmt.
        $wrong = $grader->grade($snapshot, ['pairs' => [$options[0]->id => $options[3]->id, $options[2]->id => $options[1]->id]]);
        $this->assertSame(0, $wrong['points']);
    }

    public function test_pruefungsansicht_bietet_je_typ_die_passende_maske(): void {
        [$enrollment, $quiz, $question] = $this->scenario();

        // Aus der Single-Choice-Frage eine Sortierfrage machen.
        $question->update(['kind' => LearningQuestionKind::Sort->value]);

        $attempt = $this->quizzes()->startAttempt($enrollment->refresh(), $quiz);

        $this->actingAs($enrollment->user)
            ->get(route('learning.my.quiz.show', [$enrollment->sqid, $attempt->sqid]))
            ->assertOk()
            // Nicht mehr das einzelne Textfeld aus dem Default-Zweig.
            ->assertSee('[order][]', false)
            ->assertDontSee('name="answers[' . $question->id . '][text]"', false);
    }

    // ── Zusatz-Fragetypen: Bildmarkierung und Matrix (MVP-738) ──────────

    public function test_bildmarkierung_wertet_klick_und_flaechenwahl(): void {
        \Illuminate\Support\Facades\Storage::fake('local');

        $question = $this->addQuestionViaEditor(
            LearningQuestionKind::Hotspot->value,
            'Wo ist der Feuerlöscher?',
            // * markiert die richtige Fläche; Ablenker müssen sein, sonst
            // wäre die Tastatur-Auswahlliste eine Liste nur richtiger Antworten.
            "*10,10,20,20: Wandhalterung\n60,60,20,20: Fenster",
        );

        $spots = $question->settings['hotspots'] ?? [];
        $this->assertCount(2, $spots);
        $this->assertTrue($spots[0]['is_correct']);
        $this->assertFalse($spots[1]['is_correct']);
        $this->assertSame('Wandhalterung', $spots[0]['label']);

        $grader = app(\App\Services\Learning\LearningAnswerGrader::class);
        $snapshot = [
            'kind' => LearningQuestionKind::Hotspot->value,
            'points' => 4,
            'settings' => $question->settings,
            'options' => [],
        ];

        // Klick in die richtige Fläche (Prozent, nicht Pixel).
        $this->assertSame(4, $grader->grade($snapshot, ['x' => 15, 'y' => 15])['points']);
        // Klick daneben.
        $this->assertSame(0, $grader->grade($snapshot, ['x' => 65, 'y' => 65])['points']);
        // Tastaturweg: Flächenwahl — richtige Fläche zählt, falsche nicht.
        $this->assertSame(4, $grader->grade($snapshot, ['spot' => 0])['points']);
        $this->assertSame(0, $grader->grade($snapshot, ['spot' => 1])['points']);
    }

    public function test_matrix_vergibt_teilpunkte_und_erlaubt_gleiche_spalte(): void {
        $question = $this->addQuestionViaEditor(
            LearningQuestionKind::Matrix->value,
            'Ordnen Sie die Stoffe der Brandklasse zu.',
            // Dieselbe Spalte mehrfach — das unterscheidet die Matrix von
            // der Zuordnung.
            "Holz = Brandklasse A\nPapier = Brandklasse A\nBenzin = Brandklasse B",
        );

        $settings = $question->settings;
        $this->assertSame(['Brandklasse A', 'Brandklasse B'], $settings['columns']);
        $this->assertCount(3, $settings['rows']);
        $this->assertSame(0, $settings['rows'][1]['column']);

        $grader = app(\App\Services\Learning\LearningAnswerGrader::class);
        $snapshot = [
            'kind' => LearningQuestionKind::Matrix->value,
            'points' => 6,
            'settings' => $settings,
            'options' => [],
        ];

        $this->assertSame(6, $grader->grade($snapshot, ['matrix' => [0 => 0, 1 => 0, 2 => 1]])['points']);
        // Zwei von drei Zeilen richtig ⇒ Teilpunkte statt null.
        $this->assertSame(4, $grader->grade($snapshot, ['matrix' => [0 => 0, 1 => 0, 2 => 0]])['points']);
        $this->assertSame(0, $grader->grade($snapshot, ['matrix' => [0 => 1, 1 => 1, 2 => 0]])['points']);
    }

    public function test_bildmarkierung_bietet_einen_tastaturweg(): void {
        \Illuminate\Support\Facades\Storage::fake('local');

        $question = $this->addQuestionViaEditor(
            LearningQuestionKind::Hotspot->value,
            'Wo ist der Feuerlöscher?',
            "*10,10,20,20: Wandhalterung\n60,60,20,20: Fenster",
        );

        $quiz = $question->quiz;
        $unit = $quiz->unit;
        app(LearningCourseService::class)->release($unit->course->refresh(), null);

        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($unit->course->refresh(), $user);
        $attempt = $this->quizzes()->startAttempt($enrollment, $quiz);

        // Wer nicht klicken kann, muss die Fläche wählen können (WCAG 2.1.1).
        $this->actingAs($user)
            ->get(route('learning.my.quiz.show', [$enrollment->sqid, $attempt->sqid]))
            ->assertOk()
            ->assertSee('Wandhalterung')
            ->assertSee('[spot]', false);
    }
}
