<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningCourseStatus, LearningTimePolicy, LearningUnitKind};
use App\Models\Learning\LearningCourse;
use App\Models\{Organization, User};
use App\Services\Learning\LearningCourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lernkatalog-Oberfläche (Feature 149, MVP-735): Liste, Modal-CRUD,
 * Kursakte mit Struktur und Freigabe, Rechte und Plan-Gating.
 */
class LearningCourseUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function author(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    private function courseWithUnit(): LearningCourse {
        $service = app(LearningCourseService::class);
        $course = $service->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);
        $service->addUnit($course, ['title' => 'Einführung']);

        return $course->refresh();
    }

    public function test_katalog_listet_kurse(): void {
        $course = $this->courseWithUnit();

        $this->actingAs($this->author())
            ->get(route('learning.courses.index'))
            ->assertOk()
            ->assertSee($course->title);
    }

    public function test_kurs_anlegen_ueber_das_formular(): void {
        $response = $this->actingAs($this->author())->post(route('learning.courses.store'), [
            'title' => 'Ladungssicherung',
            'access_kind' => 'enrolled',
            'time_policy' => LearningTimePolicy::WorkTimeRequired->value,
            'instruction_suitability' => 'with_presence',
            'audiences' => ['internal'],
        ]);

        $course = LearningCourse::query()->where('title', 'Ladungssicherung')->firstOrFail();
        $response->assertRedirect(route('learning.courses.show', $course->sqid));
        $this->assertSame(LearningCourseStatus::Draft, $course->status);
        $this->assertSame(['internal'], $course->audiences);
    }

    public function test_kursakte_zeigt_struktur_und_freigabe(): void {
        $course = $this->courseWithUnit();

        $this->actingAs($this->author())
            ->get(route('learning.courses.show', $course))
            ->assertOk()
            ->assertSee('Einführung')
            ->assertSee(__('learning.action.release'));
    }

    public function test_einheit_anlegen_und_freigeben(): void {
        $author = $this->author();
        $course = app(LearningCourseService::class)->createCourse($this->organization, $author, ['title' => 'Hygiene']);

        $this->actingAs($author)
            ->post(route('learning.courses.units.store', $course), [
                'title' => 'Händedesinfektion',
                'kind' => LearningUnitKind::Content->value,
            ])
            ->assertRedirect();

        $this->actingAs($author)
            ->post(route('learning.courses.release', $course), ['label' => 'Erstfassung'])
            ->assertRedirect();

        $course->refresh();
        $this->assertSame(LearningCourseStatus::Released, $course->status);
        $this->assertSame(1, $course->versions()->count());
    }

    public function test_freigegebener_kurs_nimmt_keine_einheit_mehr_an(): void {
        // Doppelt abgesichert: die Policy verweigert bereits den Zugriff
        // (403), der Service würde zusätzlich mit einer Validierungsmeldung
        // abbrechen (siehe LearningCourseFoundationTest).
        $author = $this->author();
        $course = $this->courseWithUnit();
        app(LearningCourseService::class)->release($course, $author);

        $this->actingAs($author)
            ->post(route('learning.courses.units.store', $course->refresh()), [
                'title' => 'Nachtrag',
                'kind' => LearningUnitKind::Content->value,
            ])
            ->assertForbidden();
    }

    public function test_teamleitung_darf_keinen_kurs_anlegen(): void {
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($lead)
            ->get(route('learning.courses.create'))
            ->assertForbidden();
    }

    public function test_freier_plan_sperrt_den_lernkatalog(): void {
        $this->organization->update(['plan' => Organization::PLAN_FREE]);

        $this->actingAs($this->author())
            ->get(route('learning.courses.index'))
            ->assertStatus(423);
    }

    public function test_kurs_einer_fremden_organisation_ist_unsichtbar(): void {
        $foreign = Organization::factory()->create();
        $foreignCourse = app(LearningCourseService::class)->createCourse($foreign, null, ['title' => 'Fremdkurs']);

        $this->actingAs($this->author())
            ->get(route('learning.courses.show', $foreignCourse->sqid))
            ->assertNotFound();
    }
}
