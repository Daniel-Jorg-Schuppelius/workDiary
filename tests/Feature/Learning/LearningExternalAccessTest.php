<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningExternalAccessTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningEnrollmentStatus;
use App\Models\{ExternalParticipant, User};
use App\Models\Learning\{LearningAccessToken, LearningCourse, LearningEnrollment};
use App\Services\Learning\{LearningAccessService, LearningCourseService, LearningEnrollmentService};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lernzugang ohne Benutzerkonto (Feature 149, MVP-742): gehashter Token,
 * neutrale Fehlermeldungen, Session statt Token in jeder Folge-URL — und
 * derselbe Nachweis wie für interne Lernende.
 */
class LearningExternalAccessTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        // Garantierter Reset, damit keine Testzeit in den nächsten Test leckt.
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): LearningAccessService {
        return app(LearningAccessService::class);
    }

    /** @return array{0: LearningEnrollment, 1: LearningCourse} */
    private function externalEnrollment(): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Sicherheitsunterweisung Baustelle']);
        $courses->addUnit($course, ['title' => 'Gefahren auf der Baustelle']);
        $courses->release($course->refresh(), null);

        $external = ExternalParticipant::factory()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $external);

        return [$enrollment, $course->refresh()];
    }

    public function test_token_wird_nur_gehasht_gespeichert(): void {
        [$enrollment] = $this->externalEnrollment();

        $token = $this->service()->issue($enrollment);

        $record = LearningAccessToken::query()->firstOrFail();
        $this->assertSame(CryptoHelper::hash($token), $record->token_hash);
        $this->assertStringNotContainsString($token, json_encode($record->toArray()) ?: '');
    }

    public function test_neuer_link_entwertet_den_alten(): void {
        [$enrollment] = $this->externalEnrollment();
        $first = $this->service()->issue($enrollment);

        $second = $this->service()->issue($enrollment);

        $this->assertNull($this->service()->resolve($first), 'Ein abgelöster Link darf nicht weiter gelten.');
        $this->assertNotNull($this->service()->resolve($second));
    }

    public function test_abgelaufener_link_gilt_nicht_mehr(): void {
        [$enrollment] = $this->externalEnrollment();
        $token = $this->service()->issue($enrollment, null, 7);

        Carbon::setTestNow(Carbon::now()->addDays(8));
        $this->assertNull($this->service()->resolve($token));
        Carbon::setTestNow();
    }

    public function test_interne_lernende_bekommen_keinen_einmal_link(): void {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Intern']);
        $courses->addUnit($course, ['title' => 'Einheit']);
        $courses->release($course->refresh(), null);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $user);

        $this->expectException(ValidationException::class);
        $this->service()->issue($enrollment);
    }

    public function test_einstiegslink_oeffnet_den_kurs(): void {
        [$enrollment, $course] = $this->externalEnrollment();
        $token = $this->service()->issue($enrollment);

        $this->get(route('learning.external.enter', $token))
            ->assertRedirect(route('learning.external.show'));

        $this->get(route('learning.external.show'))
            ->assertOk()
            ->assertSee($course->title);
    }

    public function test_ungueltiger_link_antwortet_neutral(): void {
        $this->get(route('learning.external.enter', str_repeat('x', 48)))
            ->assertRedirect(route('learning.external.denied'));

        $this->get(route('learning.external.denied'))
            ->assertOk()
            ->assertSee(__('learning.external.link_invalid'))
            // Kein Hinweis, ob der Token existiert, abgelaufen oder widerrufen ist.
            ->assertDontSee('abgelaufen', false)
            ->assertDontSee('widerrufen', false);
    }

    public function test_ohne_session_kein_zugriff(): void {
        [$enrollment] = $this->externalEnrollment();
        $this->service()->issue($enrollment);

        $this->get(route('learning.external.show'))->assertForbidden();
    }

    public function test_externe_person_schliesst_den_kurs_ab(): void {
        [$enrollment, $course] = $this->externalEnrollment();
        $token = $this->service()->issue($enrollment);
        $unit = $course->units()->firstOrFail();

        $this->get(route('learning.external.enter', $token));
        $this->post(route('learning.external.units.complete', $unit))
            ->assertRedirect(route('learning.external.show'));

        $this->assertSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_fremde_einheit_wird_abgewiesen(): void {
        [$enrollment] = $this->externalEnrollment();
        [, $otherCourse] = $this->externalEnrollment();
        $token = $this->service()->issue($enrollment);
        $foreignUnit = $otherCourse->units()->firstOrFail();

        $this->get(route('learning.external.enter', $token));
        $this->post(route('learning.external.units.complete', $foreignUnit))->assertNotFound();
    }

    public function test_widerrufener_zugang_gilt_nicht_mehr(): void {
        [$enrollment] = $this->externalEnrollment();
        $token = $this->service()->issue($enrollment);

        $this->service()->revoke($enrollment);

        $this->assertNull($this->service()->resolve($token));
    }
}
