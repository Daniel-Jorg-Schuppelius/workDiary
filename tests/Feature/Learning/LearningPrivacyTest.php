<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningPrivacyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Privacy\{DataSubjectKind, DataSubjectRequestType};
use App\Models\Learning\{LearningCertificate, LearningEnrollment};
use App\Models\Platform\User;
use App\Models\Privacy\{DataSubjectRequest, RetentionProposal};
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService};
use App\Services\Privacy\{DataSubjectRequestService, SubjectDataExporter};
use App\Services\Privacy\Retention\{RetentionRegistry, RetentionScanService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lerndaten in Auskunft und Löschkonzept (Feature 149, MVP-787): die
 * Betroffenenauskunft nennt Einschreibungen, Versuche, Zertifikate, Lernzeit
 * und Buchungen (ohne Fragetexte); das Löschkonzept schlägt abgeschlossene
 * Einschreibungen ohne Zertifikat vor und kürzt Zertifikate nach der
 * Nachweisfrist auf Initialen — die Prüfseite bleibt erreichbar.
 */
class LearningPrivacyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['legal_region' => 'DE']);
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
        app()->instance('currentOrganization', $this->organization);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function dsr(): DataSubjectRequest {
        return app(DataSubjectRequestService::class)->open(
            $this->organization,
            DataSubjectRequestType::Access,
            'Erika Beispiel',
            'Bitte Auskunft nach Art. 15.',
            null,
            $this->orgAdmin(),
        );
    }

    private function learner(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id, 'name' => 'Erika Beispiel']);
    }

    private function completed(User $learner, bool $certificate = false): LearningEnrollment {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz', 'certificate_enabled' => $certificate]);
        $courses->addUnit($course, ['title' => 'Einheit']);
        $courses->release($course->refresh(), null);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);
        app(LearningEnrollmentService::class)->completeUnit($enrollment, $course->units()->firstOrFail());

        return $enrollment->refresh();
    }

    public function test_auskunft_enthaelt_die_lernfamilien_ohne_fragetexte(): void {
        $learner = $this->learner();
        $this->completed($learner, certificate: true);
        $payload = app(SubjectDataExporter::class)->build($this->dsr(), DataSubjectKind::User, $learner);
        $section = collect($payload['sections'] ?? [])->firstWhere('key', 'learning');

        $this->assertNotNull($section, 'Die Lern-Sektion fehlt in der Auskunft.');
        $families = collect($section['families'])->keyBy('table');
        $this->assertSame(1, $families['learning_enrollments']['count']);
        $this->assertSame(1, $families['learning_certificates']['count']);
        $this->assertSame(LearningCertificate::query()->value('number'), $families['learning_certificates']['details']['numbers']);
        $this->assertArrayHasKey('learning_time_sessions', $families->all());
        $this->assertStringNotContainsString('prompt', json_encode($section) ?: '');
    }

    public function test_loeschkonzept_schlaegt_alte_einschreibungen_ohne_zertifikat_vor(): void {
        $learner = $this->learner();
        $plain = $this->completed($learner);
        $withCertificate = $this->completed($learner, certificate: true);
        LearningEnrollment::query()->whereIn('id', [$plain->id, $withCertificate->id])->update(['updated_at' => now()->subYears(4)]);

        $result = app(RetentionScanService::class)->scan($this->organization);

        $proposals = RetentionProposal::query()->where('area', 'learning_records')->get();
        $this->assertSame([$plain->id], $proposals->map(fn ($p) => (int) $p->subject_id)->all(), 'Nur die Einschreibung ohne Zertifikat wird vorgeschlagen.');
        $this->assertGreaterThanOrEqual(1, $result['exempt']);

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $service = app(RetentionScanService::class);
        $service->approve($proposals->first(), $admin);
        $service->purge($proposals->first()->fresh(), $admin);

        $this->assertNull(LearningEnrollment::query()->find($plain->id));
        $this->assertNotNull(LearningEnrollment::query()->find($withCertificate->id));
    }

    public function test_zertifikat_wird_nach_frist_auf_initialen_gekuerzt_und_bleibt_pruefbar(): void {
        $learner = $this->learner();
        $this->completed($learner, certificate: true);
        $certificate = LearningCertificate::query()->firstOrFail();
        LearningCertificate::query()->whereKey($certificate->id)->update(['issued_on' => now()->subYears(11)->toDateString()]);

        app(RetentionScanService::class)->scan($this->organization);
        $proposal = RetentionProposal::query()->where('area', 'learning_certificates')->firstOrFail();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $service = app(RetentionScanService::class);
        $service->approve($proposal, $admin);
        $service->purge($proposal->fresh(), $admin);

        $certificate->refresh();
        $this->assertSame('E. B.', $certificate->holder_name);
        $this->assertNotNull(LearningCertificate::query()->find($certificate->id));

        $this->get(route('learning.certificates.verify', $certificate->verification_code))
            ->assertOk()
            ->assertDontSee('Erika Beispiel');

        // Ein zweiter Lauf schlägt das gekürzte Zertifikat nicht erneut vor.
        RetentionProposal::query()->delete();
        app(RetentionScanService::class)->scan($this->organization);
        $this->assertSame(0, RetentionProposal::query()->where('area', 'learning_certificates')->count());
    }

    public function test_bereiche_haben_fristen_in_allen_regionen(): void {
        $registry = app(RetentionRegistry::class);
        foreach (['learning_records', 'learning_certificates'] as $area) {
            $this->assertNotNull($registry->policy($area));
            $this->assertNotSame('', (string) config("retention.areas.{$area}.label"));
            $this->assertNotNull($registry->yearsFor($this->organization, $area));
        }
    }
}
