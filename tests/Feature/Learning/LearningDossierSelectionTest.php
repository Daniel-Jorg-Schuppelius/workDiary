<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningDossierSelectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Models\Customer\Customer;
use App\Models\Facility\Site;
use App\Models\Learning\LearningCertificate;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\{Attendance, AttendanceCheckpoint};
use App\Services\DocumentDesign\{DocumentDesignRenderer, RenderProfileService};
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService};
use App\Services\UI\DateRangeContext;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Nachweismappe (MVP-1600): Auswahl nach Person, Projekt, Standort; Ausgeschiedene am Stichtag; ZIP mit Index. */
final class LearningDossierSelectionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Carbon::setTestNow('2026-09-15 09:00:00');
        app(DateRangeContext::class)->set(DateRangeContext::PRESET_CUSTOM, '2026-06-01', '2026-06-30');
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function manager(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id, 'name' => 'Petra Personal']);
    }

    private function worker(string $name, ?string $deactivatedAt = null): User {
        return User::factory()->aussendienst()->create([
            'organization_id' => $this->organization->id, 'name' => $name, 'deactivated_at' => $deactivatedAt,
        ]);
    }

    /**
     * Namentliche Mappe: die Personentabelle (die Auswahlfelder darüber nennen alle Personen).
     *
     * @param  array<string, string>  $query
     */
    private function named(User $manager, array $query): string {
        $html = (string) $this->actingAs($manager)
            ->get(route('learning.dossier.index', ['named' => 1, 'reason' => 'Audit Auftraggeber', ...$query]))
            ->assertOk()
            ->getContent();
        $start = strpos($html, 'id="dossier-people"');
        $this->assertNotFalse($start, 'Die Personentabelle fehlt.');

        return substr($html, (int) $start);
    }

    public function test_selection_by_person_project_and_site(): void {
        $manager = $this->manager();
        $anna = $this->worker('Anna Anker');
        $bernd = $this->worker('Bernd Bolzen');
        $clara = $this->worker('Clara Kran');

        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Neubau Halle']);
        $project->members()->attach($bernd->id);
        $site = Site::factory()->create([
            'organization_id' => $this->organization->id, 'name' => 'Werk Nord',
            'customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
        $checkpoint = AttendanceCheckpoint::factory()->create(['organization_id' => $this->organization->id, 'site_id' => $site->id]);
        Attendance::factory()->create([
            'organization_id' => $this->organization->id, 'user_id' => $clara->id, 'started_checkpoint_id' => $checkpoint->id,
            'started_at' => Carbon::parse('2026-06-10 07:00:00'), 'ended_at' => Carbon::parse('2026-06-10 15:00:00'), 'date' => '2026-06-10',
        ]);

        $table = $this->named($manager, ['user_id' => $anna->sqid]);
        $this->assertStringContainsString('Anna Anker', $table);
        $this->assertStringNotContainsString('Bernd Bolzen', $table);
        $this->assertStringNotContainsString('Clara Kran', $table);

        $table = $this->named($manager, ['project_id' => $project->sqid]);
        $this->assertStringContainsString('Bernd Bolzen', $table);
        $this->assertStringNotContainsString('Anna Anker', $table);

        $table = $this->named($manager, ['site_id' => $site->sqid]);
        $this->assertStringContainsString('Clara Kran', $table);
        $this->assertStringNotContainsString('Bernd Bolzen', $table);

        // Eine Stempelung außerhalb des Zeitraums zählt nicht als Einsatz am Standort.
        app(DateRangeContext::class)->set(DateRangeContext::PRESET_CUSTOM, '2026-09-01', '2026-09-10');
        $this->assertStringNotContainsString('Clara Kran', $this->named($manager, ['site_id' => $site->sqid]));
    }

    public function test_people_who_left_after_the_reference_date_are_included(): void {
        $manager = $this->manager();
        $this->worker('Gero Gegangen', '2026-08-31 12:00:00');
        $this->worker('Vera Vorher', '2026-03-31 12:00:00');

        $table = $this->named($manager, []);
        $this->assertStringContainsString('Gero Gegangen', $table);
        $this->assertStringContainsString(__('learning.field.left_on', ['date' => '31.08.2026']), $table);
        $this->assertStringNotContainsString('Vera Vorher', $table);
    }

    public function test_zip_contains_folder_single_certificates_and_index(): void {
        $manager = $this->manager();
        $anna = $this->worker('Anna Anker');
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Ladungssicherung', 'certificate_enabled' => true]);
        $courses->addUnit($course, ['title' => 'Grundlagen']);
        $courses->release($course->refresh(), null);
        Carbon::setTestNow('2026-05-20 09:00:00');
        $enrollments = app(LearningEnrollmentService::class);
        $enrollments->completeUnit($enrollments->enroll($course->refresh(), $anna), $course->units()->firstOrFail());
        Carbon::setTestNow('2026-09-15 09:00:00');
        $certificate = LearningCertificate::query()->where('user_id', $anna->id)->firstOrFail();

        // Ohne Anlass keine namentliche Ausgabe.
        $this->actingAs($manager)->get(route('learning.dossier.zip', ['user_id' => $anna->sqid]))->assertForbidden();

        // Teilattrappe: Gestaltung und Ausstellerkontext laufen echt (MVP-1624), nur das PDF entfällt.
        $this->instance(DocumentDesignRenderer::class, Mockery::mock(DocumentDesignRenderer::class, [app(RenderProfileService::class)], function ($mock): void {
            $mock->shouldReceive('renderPdf')->andReturn('%PDF-1.4 test');
        })->makePartial());
        $response = $this->actingAs($manager)->get(route('learning.dossier.zip', ['user_id' => $anna->sqid, 'named' => 1, 'reason' => 'Audit Auftraggeber']));
        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));

        $entries = ZipFile::readEntries((string) $response->getContent());
        $file = 'zertifikate/zertifikat-' . str_replace(['/', '\\', ' '], '-', $certificate->number) . '.pdf';
        $this->assertEqualsCanonicalizing(['nachweismappe.pdf', 'nachweismappe.json', 'index.csv', $file], array_keys($entries));
        $this->assertStringContainsString('Anna Anker', $entries['index.csv']);
        $this->assertStringContainsString($certificate->number, $entries['index.csv']);
        $this->assertStringContainsString($file, $entries['index.csv']);
        $this->assertStringContainsString('"hash"', $entries['nachweismappe.json']);

        // Die namentliche Herausgabe steht mit Anlass im Audit-Log.
        $this->assertDatabaseHas('audit_logs', ['event' => 'learning.dossierDisclosed', 'user_id' => $manager->id]);
    }
}
