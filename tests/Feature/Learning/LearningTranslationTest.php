<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningTranslationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningBlockKind, LearningTranslationStatus};
use App\Models\Learning\{LearningContentTranslation, LearningCourse, LearningUnit};
use App\Models\User;
use App\Services\Learning\{LearningContentService, LearningCourseService, LearningEnrollmentService, LearningTranslationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Übersetzte Kursinhalte (Feature 149, MVP-748).
 *
 * Die Regeln, die hier zählen — und die sich nicht aus der Bequemlichkeit
 * ergeben, sondern aus der Haftung:
 *
 *  1. **Maßgeblich bleibt die Ausgangssprache.** Eine maschinelle
 *     Übersetzung einer Sicherheitsunterweisung erscheint erst nach
 *     Freigabe durch einen Menschen.
 *  2. **Veraltet ist so gut wie nicht vorhanden.** Ändert sich der Stoff,
 *     wird die Übersetzung nicht mehr ausgespielt — eine Übersetzung des
 *     vorletzten Textes wäre schlimmer als keine.
 */
class LearningTranslationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function service(): LearningTranslationService {
        return app(LearningTranslationService::class);
    }

    /** @return array{0: LearningCourse, 1: LearningUnit} */
    private function courseWithText(): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        $courses->addUnit($course, ['title' => 'Fluchtwege']);
        $unit = $course->refresh()->units()->firstOrFail();

        app(LearningContentService::class)->appendBlock($unit, LearningBlockKind::Text, [
            'text' => 'Fluchtwege sind freizuhalten.',
        ]);

        return [$course->refresh(), $unit->refresh()];
    }

    /** Übersetzung von Hand ablegen — der Anbieter ist hier nicht der Prüfgegenstand. */
    private function storeTranslation(LearningUnit $unit, string $locale, array $fields, LearningTranslationStatus $status): LearningContentTranslation {
        return LearningContentTranslation::query()->create([
            'organization_id' => $this->organization->id,
            'translatable_type' => $unit->getMorphClass(),
            'translatable_id' => $unit->id,
            'locale' => $locale,
            'payload' => (string) json_encode($fields, JSON_UNESCAPED_UNICODE),
            'source_hash' => $this->service()->sourceHash($unit),
            'status' => $status,
        ]);
    }

    public function test_entwurf_wird_nicht_ausgespielt(): void {
        [, $unit] = $this->courseWithText();
        $this->storeTranslation($unit, 'en', ['title' => 'Escape routes'], LearningTranslationStatus::Draft);

        // Ungeprüfte Maschinenfassung: nicht sichtbar.
        $this->assertNull($this->service()->fieldsFor($unit, 'en'));
    }

    public function test_freigegebene_uebersetzung_wird_ausgespielt(): void {
        [, $unit] = $this->courseWithText();
        $this->storeTranslation($unit, 'en', ['title' => 'Escape routes'], LearningTranslationStatus::Approved);

        $fields = $this->service()->fieldsFor($unit, 'en');

        $this->assertSame('Escape routes', $fields['title'] ?? null);
    }

    public function test_geaenderter_stoff_entwertet_die_uebersetzung(): void {
        [, $unit] = $this->courseWithText();
        $this->storeTranslation($unit, 'en', ['title' => 'Escape routes'], LearningTranslationStatus::Approved);

        // Der Titel ändert sich — die Übersetzung gehört zum alten Stand.
        $unit->update(['title' => 'Flucht- und Rettungswege']);

        $this->assertNull($this->service()->fieldsFor($unit->refresh(), 'en'));
    }

    public function test_freigabe_setzt_pruefer_und_zeitpunkt(): void {
        [, $unit] = $this->courseWithText();
        $translation = $this->storeTranslation($unit, 'en', ['title' => 'Escape routes'], LearningTranslationStatus::Draft);
        $reviewer = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $approved = $this->service()->approve($translation, $reviewer);

        $this->assertSame(LearningTranslationStatus::Approved, $approved->status);
        $this->assertSame($reviewer->id, $approved->approved_by_user_id);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_player_zeigt_die_freigegebene_fassung(): void {
        [$course, $unit] = $this->courseWithText();
        $this->storeTranslation($unit, 'en', [
            'title' => 'Escape routes',
            'blocks' => [['index' => 0, 'type' => 'text', 'text' => 'Keep escape routes clear.']],
        ], LearningTranslationStatus::Approved);

        app(LearningCourseService::class)->release($course->refresh(), null);

        // Die Sprache kommt aus der Einstellung der Person, nicht aus einem
        // gesetzten Ambient-Locale — genau so läuft es im Betrieb.
        $learner = User::factory()->aussendienst()->create([
            'organization_id' => $this->organization->id,
            'preferences' => ['locale' => 'en'],
        ]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $this->actingAs($learner)
            ->get(route('learning.my.show', $enrollment->sqid))
            ->assertOk()
            ->assertSee('Escape routes')
            ->assertSee('Keep escape routes clear.')
            ->assertDontSee('Fluchtwege sind freizuhalten.');
    }

    public function test_in_die_eigene_sprache_wird_nicht_uebersetzt(): void {
        [$course] = $this->courseWithText();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service()->translateCourse($course, 'de');
    }
}
