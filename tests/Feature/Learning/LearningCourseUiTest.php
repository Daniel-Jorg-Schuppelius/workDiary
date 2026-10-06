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
use App\Models\Platform\{Organization, User};
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

    public function test_entwurf_laesst_sich_aus_der_kursakte_loeschen(): void {
        $author = $this->author();
        $course = $this->courseWithUnit();
        $deleteForm = 'action="' . route('learning.courses.destroy', $course) . '"';

        $this->actingAs($author)
            ->get(route('learning.courses.show', $course))
            ->assertOk()
            ->assertSee($deleteForm, false)
            ->assertSee(__('learning.action.delete_course'));

        $this->actingAs($author)
            ->delete(route('learning.courses.destroy', $course))
            ->assertRedirect(route('learning.courses.index'))
            ->assertSessionHas('success', __('learning.flash.deleted'));

        $this->assertDatabaseMissing('learning_courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('learning_units', ['learning_course_id' => $course->id]);
    }

    /** Eine Version kann Nachweise tragen: Der Kurs wird archiviert, nie gelöscht — auch wieder geöffnet nicht. */
    public function test_einmal_freigegebener_kurs_bietet_kein_loeschen_und_verweigert_es(): void {
        $author = $this->author();
        $course = $this->courseWithUnit();
        $deleteForm = 'action="' . route('learning.courses.destroy', $course) . '"';
        app(LearningCourseService::class)->release($course, $author);

        $this->actingAs($author)->get(route('learning.courses.show', $course))->assertOk()->assertDontSee($deleteForm, false);
        $this->actingAs($author)->delete(route('learning.courses.destroy', $course))->assertForbidden();

        app(LearningCourseService::class)->reopen($course->refresh());
        $this->assertSame(LearningCourseStatus::Draft, $course->refresh()->status);
        $this->actingAs($author)->get(route('learning.courses.show', $course))->assertOk()->assertDontSee($deleteForm, false);
        $this->actingAs($author)->delete(route('learning.courses.destroy', $course))->assertForbidden();

        $this->assertDatabaseHas('learning_courses', ['id' => $course->id]);
    }

    /** Die Aktion prüfte `update` — das verlangt einen bearbeitbaren Stand, also bekam außer Admins jeder 403. */
    public function test_autorin_oeffnet_einen_freigegebenen_kurs_wieder(): void {
        $author = $this->author();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $course = $this->courseWithUnit();
        $reopenForm = 'action="' . route('learning.courses.reopen', $course) . '"';

        // Im Entwurf gibt es nichts zu öffnen.
        $this->actingAs($author)->get(route('learning.courses.show', $course))->assertOk()->assertDontSee($reopenForm, false);
        $this->actingAs($author)->post(route('learning.courses.reopen', $course))->assertForbidden();

        app(LearningCourseService::class)->release($course, $author);
        $this->actingAs($lead)->post(route('learning.courses.reopen', $course))->assertForbidden();
        $this->assertSame(LearningCourseStatus::Released, $course->refresh()->status);

        $this->actingAs($author)->get(route('learning.courses.show', $course))->assertOk()->assertSee($reopenForm, false);
        $this->actingAs($author)->post(route('learning.courses.reopen', $course))->assertRedirect();
        $this->assertSame(LearningCourseStatus::Draft, $course->refresh()->status);
    }

    public function test_loeschen_braucht_das_autorenrecht_und_die_eigene_organisation(): void {
        $course = $this->courseWithUnit();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($lead)->delete(route('learning.courses.destroy', $course))->assertForbidden();

        $foreign = Organization::factory()->create();
        $foreignCourse = app(LearningCourseService::class)->createCourse($foreign, null, ['title' => 'Fremdkurs']);
        $this->actingAs($this->author())->delete(route('learning.courses.destroy', $foreignCourse->sqid))->assertNotFound();

        $this->assertDatabaseHas('learning_courses', ['id' => $course->id]);
        $this->assertDatabaseHas('learning_courses', ['id' => $foreignCourse->id]);
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
