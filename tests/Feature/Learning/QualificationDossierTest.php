<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationDossierTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Models\Learning\LearningCertificate;
use App\Models\{Qualification, User, UserQualification};
use App\Services\Learning\QualificationDossierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Qualifikationsnachweis nach außen (Feature 149, MVP-750).
 *
 * Der Kern ist die **Stichtagsfähigkeit**: „war die Person am 14. März
 * unterwiesen?" — nicht „ist sie es heute?".
 */
class QualificationDossierTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): QualificationDossierService {
        return app(QualificationDossierService::class);
    }

    private function worker(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    private function qualification(): Qualification {
        return Qualification::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'PSA gegen Absturz',
            'is_active' => true,
        ]);
    }

    public function test_stichtag_entscheidet_ueber_die_gueltigkeit(): void {
        $user = $this->worker();
        $qualification = $this->qualification();
        UserQualification::query()->create([
            'user_id' => $user->id,
            'qualification_id' => $qualification->id,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-06-30',
        ]);

        $duringMarch = $this->service()->forUsers(collect([$user]), Carbon::parse('2026-03-14'));
        $this->assertTrue($duringMarch[0]['qualifications'][0]['valid_on'], 'Am 14.03. war der Nachweis gültig.');

        $today = $this->service()->forUsers(collect([$user]), Carbon::parse('2026-08-28'));
        $this->assertFalse($today[0]['qualifications'][0]['valid_on'], 'Heute ist er abgelaufen — beides muss beantwortbar sein.');
    }

    public function test_noch_nicht_erteilte_nachweise_zaehlen_nicht(): void {
        $user = $this->worker();
        $qualification = $this->qualification();
        UserQualification::query()->create([
            'user_id' => $user->id,
            'qualification_id' => $qualification->id,
            'valid_from' => '2026-05-01',
            'valid_until' => '2027-05-01',
        ]);

        $rows = $this->service()->forUsers(collect([$user]), Carbon::parse('2026-03-14'));

        $this->assertFalse($rows[0]['qualifications'][0]['valid_on'], 'Ein späterer Nachweis heilt keinen früheren Stichtag.');
    }

    public function test_aggregierte_auskunft_nennt_keine_namen(): void {
        $qualification = $this->qualification();
        $covered = $this->worker();
        $missing = $this->worker();

        UserQualification::query()->create([
            'user_id' => $covered->id,
            'qualification_id' => $qualification->id,
            'valid_from' => '2026-01-01',
            'valid_until' => '2027-01-01',
        ]);

        $summary = $this->service()->summarizeQualification(
            collect([$covered, $missing]),
            $qualification->id,
            Carbon::parse('2026-08-28')
        );

        $this->assertSame(2, $summary['people']);
        $this->assertSame(1, $summary['covered']);
        $this->assertSame(1, $summary['missing']);
        $this->assertSame('2027-01-01', $summary['earliest_expiry']);
        $this->assertStringNotContainsString($covered->name, json_encode($summary) ?: '');
    }

    public function test_widerruf_wirkt_ab_seinem_zeitpunkt_nicht_rueckwirkend(): void {
        $user = $this->worker();
        // Zertifikate hängen an Kurs und Einschreibung — beides ist Pflicht,
        // damit ein Nachweis nie ohne seinen Ursprung dasteht.
        $courses = app(\App\Services\Learning\LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Höhenarbeit']);
        $courses->addUnit($course, ['title' => 'Praxis']);
        $courses->release($course->refresh(), null);
        $enrollment = app(\App\Services\Learning\LearningEnrollmentService::class)->enroll($course->refresh(), $user);

        LearningCertificate::query()->create([
            'organization_id' => $this->organization->id,
            'learning_enrollment_id' => $enrollment->id,
            'learning_course_id' => $course->id,
            'user_id' => $user->id,
            'number' => 'Z-1',
            'verification_code' => str_repeat('a', 32),
            'holder_name' => $user->name,
            'issued_on' => '2026-01-10',
            'valid_until' => '2027-01-10',
            'revoked_at' => '2026-06-01 10:00:00',
        ]);

        $before = $this->service()->forUsers(collect([$user]), Carbon::parse('2026-03-14'));
        $this->assertFalse($before[0]['certificates'][0]['revoked'], 'Vor dem Widerruf galt das Zertifikat.');
        $this->assertTrue($before[0]['certificates'][0]['valid_on']);

        $after = $this->service()->forUsers(collect([$user]), Carbon::parse('2026-07-01'));
        $this->assertTrue($after[0]['certificates'][0]['revoked']);
        $this->assertFalse($after[0]['certificates'][0]['valid_on']);
    }

    public function test_export_ist_zum_selben_stichtag_reproduzierbar(): void {
        $user = $this->worker();
        $qualification = $this->qualification();
        UserQualification::query()->create([
            'user_id' => $user->id,
            'qualification_id' => $qualification->id,
            'valid_from' => '2026-01-01',
            'valid_until' => '2027-01-01',
        ]);

        $first = $this->service()->exportPayload($this->organization, collect([$user]), Carbon::parse('2026-08-28'));
        $second = $this->service()->exportPayload($this->organization, collect([$user]), Carbon::parse('2026-08-28'));

        $this->assertSame($first['hash'], $second['hash'], 'Zwei Läufe zum selben Stichtag müssen identisch sein.');
        $this->assertSame('2026-08-28', $first['as_of']);
    }
}
