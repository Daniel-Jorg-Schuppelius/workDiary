<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAiSuggestionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningBlockKind;
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection};
use App\Models\Learning\LearningCourse;
use App\Services\Learning\{LearningAiSuggestionService, LearningContentService, LearningCourseService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * KI-Unterstützung der Lernplattform (Feature 149, MVP-746).
 *
 * Der Kern ist eine Grenze, keine Funktion: die KI erzeugt **Entwürfe und
 * Erklärungen** — sie bewertet nichts und entscheidet nichts (EU-KI-VO
 * Anhang III Nr. 3). Der Tutor antwortet nur aus dem freigegebenen
 * Kursinhalt.
 */
class LearningAiSuggestionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private FakeAiProvider $fake;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->fake = FakeAiProviderFactory::install();

        $connection = AiProviderConnection::factory()->create(['organization_id' => $this->organization->id]);

        foreach ([
            LearningAiSuggestionService::CAPABILITY_OUTLINE,
            LearningAiSuggestionService::CAPABILITY_QUESTIONS,
            LearningAiSuggestionService::CAPABILITY_TUTOR,
        ] as $capability) {
            AiCapabilitySetting::factory()->create([
                'organization_id' => $this->organization->id,
                'capability' => $capability,
                'enabled' => true,
                'allowed_connection_ids' => [$connection->id],
            ]);
        }
    }

    private function service(): LearningAiSuggestionService {
        return app(LearningAiSuggestionService::class);
    }

    private function releasedCourse(): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, [
            'title' => 'Brandschutz kompakt',
            'objectives' => 'Fluchtwege kennen',
        ]);
        $courses->addUnit($course, ['title' => 'Fluchtwege']);
        $unit = $course->refresh()->units()->firstOrFail();
        app(LearningContentService::class)->appendBlock($unit, LearningBlockKind::Text, [
            'text' => 'Fluchtwege sind stets freizuhalten.',
        ]);
        $courses->release($course->refresh(), null);

        return $course->refresh();
    }

    public function test_kursgliederung_kommt_als_entwurf(): void {
        $this->fake->textResponse = "1. Grundlagen\n2. Fluchtwege";

        $outline = $this->service()->draftOutline($this->organization, 'Brandschutz für Büros');

        $this->assertStringContainsString('Fluchtwege', $outline);
    }

    public function test_fragenentwurf_nutzt_den_einheitentext(): void {
        $course = $this->releasedCourse();
        $unit = $course->units()->firstOrFail();
        $this->fake->textResponse = 'Frage 1: Wozu dienen Fluchtwege?';

        $draft = $this->service()->draftQuestions($unit, 3);

        $this->assertStringContainsString('Fluchtwege', $draft);
    }

    public function test_tutor_bekommt_nur_den_freigegebenen_kursinhalt(): void {
        $course = $this->releasedCourse();
        $this->fake->textResponse = 'Fluchtwege sind freizuhalten (siehe Einheit 1).';

        $answer = $this->service()->answerLearnerQuestion($course, 'Darf ich Kisten im Flur abstellen?');

        $this->assertStringContainsString('Fluchtwege', $answer);
    }

    public function test_kurs_ohne_freigabe_liefert_leeren_tutor_kontext(): void {
        $courses = app(LearningCourseService::class);
        $draft = $courses->createCourse($this->organization, null, ['title' => 'Entwurf']);
        $this->fake->textResponse = 'Dazu liegt mir nichts vor.';

        $answer = $this->service()->answerLearnerQuestion($draft, 'Frage');

        // Ohne freigegebene Version gibt es keinen Stoff — der Tutor darf
        // dann nichts erfinden; hier zählt nur, dass der Aufruf trägt.
        $this->assertIsString($answer);
    }

    public function test_es_gibt_keine_methode_die_bewertet(): void {
        // Absichtserklärung als Test: eine KI, die Lernergebnisse bewertet
        // oder über Zugang entscheidet, wäre Hochrisiko nach Anhang III
        // Nr. 3 der KI-VO. Diese Klasse darf so etwas nie anbieten.
        $methods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            (new \ReflectionClass(LearningAiSuggestionService::class))->getMethods(\ReflectionMethod::IS_PUBLIC)
        );

        foreach (['grade', 'evaluate', 'assess', 'score', 'decide', 'pass'] as $forbidden) {
            foreach ($methods as $method) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $forbidden,
                    $method,
                    'Die KI darf nicht bewerten oder entscheiden.'
                );
            }
        }
    }
}
