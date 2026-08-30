<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseFoundationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningCourseStatus, LearningTimePolicy, LearningUnitKind};
use App\Models\Learning\LearningCourse;
use App\Models\Training\TrainingCourse;
use App\Models\User;
use App\Services\Learning\LearningCourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lernplattform, Datenfundament (Feature 149, MVP-735): Kursstruktur,
 * eingefrorene Inhaltsversion bei der Freigabe, Inhaltssperre danach und
 * die einzige Schreibrichtung in den Trainingskatalog (Feature 145).
 */
class LearningCourseFoundationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function service(): LearningCourseService {
        return app(LearningCourseService::class);
    }

    private function courseWithUnit(array $attributes = []): LearningCourse {
        $course = $this->service()->createCourse($this->organization, null, array_merge([
            'title' => 'Brandschutz kompakt',
        ], $attributes));

        $this->service()->addUnit($course, ['title' => 'Einführung']);

        return $course->refresh();
    }

    public function test_kurs_startet_als_entwurf_mit_eindeutigem_code(): void {
        $first = $this->service()->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);
        $second = $this->service()->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt']);

        $this->assertSame(LearningCourseStatus::Draft, $first->status);
        $this->assertSame('brandschutz-kompakt', $first->code);
        $this->assertNotSame($first->code, $second->code, 'Der Kurscode muss je Organisation eindeutig bleiben.');
    }

    public function test_freigabe_friert_den_inhaltsbaum_als_version_ein(): void {
        $course = $this->courseWithUnit();

        $version = $this->service()->release($course, null, 'Erstfassung');

        $this->assertSame(1, $version->version);
        $this->assertTrue($version->is_current);
        $this->assertNotNull($version->released_at);
        $this->assertSame(LearningCourseStatus::Released, $course->refresh()->status);

        $snapshot = $version->snapshot();
        $this->assertSame('Brandschutz kompakt', $snapshot['course']['title']);
        $this->assertCount(1, $snapshot['units']);
        $this->assertSame('Einführung', $snapshot['units'][0]['title']);
    }

    public function test_freigegebener_kurs_ist_inhaltlich_gesperrt(): void {
        $course = $this->courseWithUnit();
        $this->service()->release($course, null);

        $this->expectException(ValidationException::class);
        $this->service()->addUnit($course->refresh(), ['title' => 'Nachtrag']);
    }

    public function test_zweite_freigabe_erzeugt_folgeversion_und_entwertet_die_alte(): void {
        $course = $this->courseWithUnit();
        $first = $this->service()->release($course, null);

        $this->service()->reopen($course->refresh());
        $this->service()->addUnit($course->refresh(), ['title' => 'Vertiefung', 'kind' => LearningUnitKind::Content->value]);
        $second = $this->service()->release($course->refresh(), null);

        $this->assertSame(2, $second->version);
        $this->assertTrue($second->is_current);
        $this->assertFalse($first->refresh()->is_current);
        $this->assertCount(2, $second->snapshot()['units']);
    }

    public function test_kurs_ohne_einheiten_kann_nicht_freigegeben_werden(): void {
        $course = $this->service()->createCourse($this->organization, null, ['title' => 'Leerer Kurs']);

        $this->expectException(ValidationException::class);
        $this->service()->release($course, null);
    }

    public function test_freigabe_spiegelt_die_kursversion_in_den_trainingskatalog(): void {
        $trainingCourse = TrainingCourse::factory()->create(['organization_id' => $this->organization->id]);
        $course = $this->courseWithUnit(['training_course_id' => $trainingCourse->id]);

        $version = $this->service()->release($course, null, 'Jahresunterweisung 2026');

        $this->assertNotNull($version->training_course_version_id);

        $mirrored = $version->trainingCourseVersion;
        $this->assertNotNull($mirrored);
        $this->assertTrue($mirrored->is_current, 'Die gespiegelte Version muss im Trainingskatalog die aktuelle sein.');
        $this->assertSame($trainingCourse->id, $mirrored->training_course_id);
    }

    public function test_pflichtkurs_darf_nicht_freiwillig_unbezahlt_sein(): void {
        $trainingCourse = TrainingCourse::factory()->create(['organization_id' => $this->organization->id]);

        $this->expectException(ValidationException::class);
        $this->service()->createCourse($this->organization, null, [
            'title' => 'Pflichtunterweisung',
            'training_course_id' => $trainingCourse->id,
            'time_policy' => LearningTimePolicy::VoluntaryUnpaid->value,
        ]);
    }

    public function test_abschnitt_eines_fremden_kurses_wird_abgewiesen(): void {
        $courseA = $this->service()->createCourse($this->organization, null, ['title' => 'Kurs A']);
        $courseB = $this->service()->createCourse($this->organization, null, ['title' => 'Kurs B']);
        $section = $this->service()->addSection($courseB, ['title' => 'Abschnitt B']);

        $this->expectException(ValidationException::class);
        $this->service()->addUnit($courseA, ['title' => 'Einheit', 'section' => $section]);
    }

    public function test_teamleitung_sieht_kurse_gibt_aber_nicht_frei(): void {
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $course = $this->courseWithUnit();

        $this->assertTrue($lead->can('viewAny', LearningCourse::class));
        $this->assertFalse($lead->can('create', LearningCourse::class), 'Autorenschaft ist nicht Aufgabe der Teamleitung.');
        $this->assertFalse($lead->can('release', $course), 'Freigabe braucht learning.release.');
    }

    public function test_personalverwaltung_darf_anlegen_und_freigeben(): void {
        $hr = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
        $course = $this->courseWithUnit();

        $this->assertTrue($hr->can('create', LearningCourse::class));
        $this->assertTrue($hr->can('update', $course));
        $this->assertTrue($hr->can('release', $course));
    }

    public function test_freigegebener_kurs_ist_auch_fuer_die_policy_gesperrt(): void {
        $hr = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
        $course = $this->courseWithUnit();
        $this->service()->release($course, $hr);

        $this->assertFalse($hr->can('update', $course->refresh()), 'Der Inhalt einer freigegebenen Version ist eingefroren.');
        $this->assertTrue($hr->can('updateMeta', $course), 'Stammdaten bleiben pflegbar.');
        $this->assertFalse($hr->can('delete', $course), 'Ein Kurs mit Version wird archiviert, nicht gelöscht.');
    }
}
