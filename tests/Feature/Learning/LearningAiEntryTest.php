<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAiEntryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningEnrollmentStatus, LearningUnitKind};
use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection};
use App\Models\Learning\{LearningCourse, LearningQuiz};
use App\Models\User;
use App\Services\Learning\{LearningAiSuggestionService, LearningCourseService, LearningEnrollmentService, LearningOutlineParser};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeAiProvider, FakeAiProviderFactory};
use Tests\TestCase;

/**
 * KI-Eingänge (Feature 149, MVP-781): Gliederungs- und Fragenentwurf im
 * Editor, Tutor im Player — alles nur Vorschlag, nichts wird bewertet oder
 * freigeschaltet. Ohne freigeschaltete Fähigkeit gibt es keinen Eingang.
 */
class LearningAiEntryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private FakeAiProvider $fake;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->fake = FakeAiProviderFactory::install();
    }

    private function enableAi(): void {
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

    private function author(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    private function draftCourse(): LearningCourse {
        return app(LearningCourseService::class)->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt', 'objectives' => 'Fluchtwege kennen']);
    }

    public function test_gliederungsparser_liest_ueberschriften_und_aufzaehlungen(): void {
        $sections = app(LearningOutlineParser::class)->parse("## Grundlagen\n- Fluchtwege — Lernziel: Wege kennen\n* Feuerlöscher\n2. Praxis\n2.1 Übung am Gerät\nFließtext ohne Bedeutung\n");

        $this->assertSame([
            ['title' => 'Grundlagen', 'units' => ['Fluchtwege', 'Feuerlöscher']],
            ['title' => 'Praxis', 'units' => ['Übung am Gerät']],
        ], $sections);
    }

    public function test_ohne_freigeschaltete_faehigkeit_gibt_es_keinen_eingang(): void {
        $course = $this->draftCourse();

        $this->actingAs($this->author())
            ->get(route('learning.courses.ai-outline.create', $course))
            ->assertNotFound();

        $this->actingAs($this->author())
            ->get(route('learning.courses.show', $course))
            ->assertOk()
            ->assertDontSee(__('learning.action.ai_outline'));
    }

    public function test_gliederungsentwurf_legt_abschnitte_und_einheiten_als_entwurf_an(): void {
        $this->enableAi();
        $this->fake->textResponse = "1. Grundlagen\n- Fluchtwege\n- Löscher\n2. Praxis\n- Übung";
        $course = $this->draftCourse();

        $this->actingAs($this->author())
            ->get(route('learning.courses.ai-outline.create', $course))
            ->assertOk()
            ->assertSee('Brandschutz kompakt');

        $this->actingAs($this->author())
            ->post(route('learning.courses.ai-outline', $course), ['topic' => 'Brandschutz im Lager', 'audience' => 'Lagerpersonal'])
            ->assertRedirect(route('learning.courses.show', $course));

        $course->refresh();
        $this->assertSame(['Grundlagen', 'Praxis'], $course->sections()->pluck('title')->all());
        $this->assertSame(['Fluchtwege', 'Löscher', 'Übung'], $course->units()->pluck('title')->all());
        $this->assertSame('Grundlagen', $course->units()->firstOrFail()->section?->title);
        $this->assertTrue($course->status->value === 'draft');
    }

    public function test_freigegebener_kurs_bekommt_keine_ki_gliederung(): void {
        $this->enableAi();
        $course = $this->draftCourse();
        app(LearningCourseService::class)->addUnit($course, ['title' => 'Einführung']);
        app(LearningCourseService::class)->release($course->refresh(), null);

        $this->actingAs($this->author())
            ->post(route('learning.courses.ai-outline', $course), ['topic' => 'Brandschutz'])
            ->assertForbidden();
    }

    public function test_fragenentwurf_landet_als_text_im_editor_und_nicht_als_frage(): void {
        $this->enableAi();
        $this->fake->textResponse = "Frage 1: Wozu dienen Fluchtwege?\n*Zum Verlassen des Gebäudes\nZum Lagern";
        $course = $this->draftCourse();
        app(LearningCourseService::class)->addUnit($course, ['title' => 'Prüfung', 'kind' => LearningUnitKind::Quiz->value]);
        $unit = $course->refresh()->units()->firstOrFail();
        LearningQuiz::query()->create(['organization_id' => $this->organization->id, 'learning_unit_id' => $unit->id, 'title' => 'Prüfung', 'pass_percent' => 50, 'max_attempts' => 1]);

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.quiz.ai-draft', [$course, $unit]), ['count' => 3])
            ->assertRedirect(route('learning.courses.units.quiz.edit', [$course, $unit]))
            ->assertSessionHas('aiDraft');

        $this->actingAs($this->author())
            ->get(route('learning.courses.units.quiz.edit', [$course, $unit]))
            ->assertOk()
            ->assertSee(__('learning.action.ai_questions'));

        $this->assertSame(0, $unit->quiz()->firstOrFail()->questions()->count(), 'Der Entwurf darf keine Frage anlegen.');
    }

    public function test_tutor_antwortet_und_veraendert_nichts(): void {
        $this->enableAi();
        $this->fake->textResponse = 'Fluchtwege sind freizuhalten (siehe Einheit 1).';
        $courses = app(LearningCourseService::class);
        $course = $this->draftCourse();
        $courses->addUnit($course, ['title' => 'Fluchtwege']);
        $courses->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $this->actingAs($learner)
            ->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertSee(__('learning.action.ask_tutor'));

        $this->actingAs($learner)
            ->post(route('learning.my.tutor', $enrollment), ['question' => 'Darf ich Kisten im Flur abstellen?'])
            ->assertRedirect(route('learning.my.show', $enrollment))
            ->assertSessionHas('tutorAnswer', 'Fluchtwege sind freizuhalten (siehe Einheit 1).');

        $enrollment->refresh();
        $this->assertSame(LearningEnrollmentStatus::Assigned, $enrollment->status);
        $this->assertSame(0, $enrollment->progress()->count());
    }

    public function test_tutor_ist_ohne_faehigkeit_unsichtbar(): void {
        $courses = app(LearningCourseService::class);
        $course = $this->draftCourse();
        $courses->addUnit($course, ['title' => 'Fluchtwege']);
        $courses->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $this->actingAs($learner)
            ->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertDontSee(__('learning.action.ask_tutor'));

        $this->actingAs($learner)
            ->post(route('learning.my.tutor', $enrollment), ['question' => 'Frage?'])
            ->assertNotFound();
    }
}
