<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAccessibilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningQuestionKind, LearningUnitKind};
use App\Models\Learning\{LearningQuestion, LearningQuiz};
use App\Models\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningQuizService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Barrierefreiheit der Lernoberfläche (Feature 149, MVP-748).
 *
 * **Kein Qualitätsziel, sondern Zulassungsbedingung:** seit der
 * Betreiber-Entscheidung vom 2026-08-29 werden Kurse auch an Verbraucher
 * verkauft, damit greift das BFSG (EN 301 549 / WCAG 2.1 AA).
 *
 * Geprüft werden hier die Stellen, die man beim Bauen am leichtesten
 * übersieht — nicht das Aussehen, sondern ob ein Formular ohne Augen
 * bedienbar ist.
 */
class LearningAccessibilityTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return array{0: \App\Models\Learning\LearningEnrollment, 1: User} */
    private function attemptScenario(LearningQuestionKind $kind): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        $courses->addUnit($course, ['title' => 'Prüfung', 'kind' => LearningUnitKind::Quiz->value]);
        $unit = $course->refresh()->units()->firstOrFail();

        $quiz = LearningQuiz::query()->create([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => 'Prüfung',
            'pass_percent' => 50,
            'max_attempts' => 3,
        ]);

        $question = LearningQuestion::query()->create([
            'organization_id' => $this->organization->id,
            'learning_quiz_id' => $quiz->id,
            'kind' => $kind->value,
            'prompt' => 'Welche Nummer hat die Feuerwehr?',
            'points' => 2,
            'position' => 1,
            'settings' => $kind === LearningQuestionKind::Cloze ? ['gaps' => [['112'], ['110']]] : null,
        ]);

        if ($kind->needsOptions()) {
            foreach (['112', '110'] as $index => $label) {
                $question->options()->create([
                    'organization_id' => $this->organization->id,
                    'label' => $label,
                    'is_correct' => $index === 0,
                    'position' => $index + 1,
                    'match_key' => $kind === LearningQuestionKind::Matching ? 'p1' : null,
                ]);
            }
        }

        $courses->release($course->refresh(), null);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $user);

        return [$enrollment, $user];
    }

    public function test_frage_ist_die_beschriftung_ihrer_antworten(): void {
        [$enrollment, $user] = $this->attemptScenario(LearningQuestionKind::Single);
        $quiz = $enrollment->course->units()->firstOrFail()->quiz;
        $attempt = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);

        // Ohne fieldset/legend liest ein Screenreader die Optionen vor, ohne
        // zu sagen, wozu sie gehören (WCAG 1.3.1).
        $this->actingAs($user)
            ->get(route('learning.my.quiz.show', [$enrollment->sqid, $attempt->sqid]))
            ->assertOk()
            ->assertSee('<fieldset>', false)
            ->assertSee('<legend', false);
    }

    public function test_freitextfeld_hat_eine_beschriftung(): void {
        [$enrollment, $user] = $this->attemptScenario(LearningQuestionKind::ShortText);
        $quiz = $enrollment->course->units()->firstOrFail()->quiz;
        $attempt = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);

        // Ein Feld ohne Beschriftung ist ohne Augen nicht zuzuordnen
        // (WCAG 3.3.2) — ein Platzhalter zählt nicht, er verschwindet beim Tippen.
        $this->actingAs($user)
            ->get(route('learning.my.quiz.show', [$enrollment->sqid, $attempt->sqid]))
            ->assertOk()
            ->assertSee('class="sr-only" for="answer-', false);
    }

    public function test_sortierauswahl_nennt_ihren_platz(): void {
        [$enrollment, $user] = $this->attemptScenario(LearningQuestionKind::Sort);
        $quiz = $enrollment->course->units()->firstOrFail()->quiz;
        $attempt = app(LearningQuizService::class)->startAttempt($enrollment, $quiz);

        // Drei gleich aussehende Auswahlfelder ohne Namen sind nicht bedienbar.
        $this->actingAs($user)
            ->get(route('learning.my.quiz.show', [$enrollment->sqid, $attempt->sqid]))
            ->assertOk()
            ->assertSee('aria-label="' . __('learning.field.rank_position', ['rank' => 1]) . '"', false);
    }

    public function test_fremdsprachiger_kursinhalt_ist_ausgezeichnet(): void {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        $courses->addUnit($course, ['title' => 'Fluchtwege']);
        $courses->release($course->refresh(), null);

        // Kurs auf Deutsch, Person liest Englisch, keine Übersetzung da:
        // ohne lang-Auszeichnung liest ein Screenreader den deutschen Text
        // englisch vor (WCAG 3.1.2).
        $user = User::factory()->aussendienst()->create([
            'organization_id' => $this->organization->id,
            'preferences' => ['locale' => 'en'],
        ]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $user);

        $this->actingAs($user)
            ->get(route('learning.my.show', $enrollment->sqid))
            ->assertOk()
            ->assertSee('lang="de"', false);
    }

    public function test_restzeit_wird_als_status_gemeldet(): void {
        [$enrollment, $user] = $this->attemptScenario(LearningQuestionKind::Single);
        $unit = $enrollment->course->units()->firstOrFail();
        $unit->quiz->update(['time_limit_minutes' => 30]);
        $attempt = app(LearningQuizService::class)->startAttempt($enrollment->refresh(), $unit->quiz->refresh());

        $this->actingAs($user)
            ->get(route('learning.my.quiz.show', [$enrollment->sqid, $attempt->sqid]))
            ->assertOk()
            ->assertSee('role="status"', false);
    }
}
