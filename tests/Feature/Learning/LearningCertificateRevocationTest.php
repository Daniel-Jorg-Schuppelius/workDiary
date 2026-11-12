<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCertificateRevocationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\Notification\NotificationEvent;
use App\Models\Audit\AuditLog;
use App\Models\Communication\ExternalParticipant;
use App\Models\Learning\{LearningAccessToken, LearningCertificate, LearningCourse, LearningEnrollment};
use App\Models\Platform\User;
use App\Services\Learning\{LearningAccessService, LearningCompletionService, LearningCourseService, LearningEnrollmentService, QualificationDossierService};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Zertifikat widerrufen und Einstiegslink entwerten (MVP-1599). */
final class LearningCertificateRevocationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return array{0: LearningEnrollment, 1: LearningCourse, 2: User, 3: LearningCertificate} */
    private function certified(): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Arbeiten in Höhen', 'certificate_enabled' => true, 'validity_months' => 12]);
        $courses->addUnit($course, ['title' => 'Grundlagen']);
        $courses->release($course->refresh(), null);

        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollments = app(LearningEnrollmentService::class);
        $enrollment = $enrollments->enroll($course->refresh(), $learner);
        $enrollments->completeUnit($enrollment, $course->units()->firstOrFail());

        return [$enrollment->refresh(), $course->refresh(), $learner, LearningCertificate::query()->where('learning_enrollment_id', $enrollment->id)->firstOrFail()];
    }

    private function manager(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    public function test_manager_revokes_with_reason_and_every_reader_shows_it(): void {
        [$enrollment, $course, $learner, $certificate] = $this->certified();
        $manager = $this->manager();

        $this->actingAs($manager)->get(route('learning.courses.enrollments.index', $course))
            ->assertOk()
            ->assertSee($certificate->number)
            ->assertSee(route('learning.courses.enrollments.certificate.revoke', [$course, $enrollment]), false);
        $this->actingAs($manager)->get(route('learning.courses.enrollments.certificate.revoke', [$course, $enrollment]))
            ->assertOk()
            ->assertSee(__('learning.help.revoke_certificate'));

        // Ohne Grund kein Widerruf.
        $this->actingAs($manager)->post(route('learning.courses.enrollments.certificate.revoke.store', [$course, $enrollment]), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertFalse($certificate->refresh()->isRevoked());

        $this->actingAs($manager)->post(route('learning.courses.enrollments.certificate.revoke.store', [$course, $enrollment]), ['reason' => 'Prüfung wurde nicht selbst abgelegt'])
            ->assertRedirect(route('learning.courses.enrollments.index', $course))
            ->assertSessionHas('success');

        $certificate->refresh();
        $this->assertTrue($certificate->isRevoked());
        $this->assertSame('Prüfung wurde nicht selbst abgelegt', $certificate->revoked_reason);
        $this->assertSame($manager->id, (int) $certificate->getAttribute('revoked_by_user_id'));

        // Audit: wer, wann, warum.
        $audit = AuditLog::query()
            ->where('auditable_type', MorphMap::stableKey(LearningCertificate::class))
            ->where('auditable_id', $certificate->id)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame($manager->id, (int) $audit->user_id);
        $this->assertStringContainsString('Prüfung wurde nicht selbst abgelegt', (string) json_encode($audit->changes, JSON_UNESCAPED_UNICODE));

        // Die Person erfährt es mit Grund.
        $notification = $learner->notifications()->get()->first(fn ($n): bool => (((array) $n->data)['event'] ?? '') === NotificationEvent::LearningCertificateRevoked->value);
        $this->assertNotNull($notification);
        $this->assertSame('notification.message.learning_certificate_revoked', ((array) $notification->data)['message_key'] ?? null);

        // Prüfseite, Open Badge, PDF, Nachweismappe, Teilnehmerliste.
        $this->app['auth']->forgetGuards();
        $this->get(route('learning.certificates.verify', $certificate->verification_code))
            ->assertOk()
            ->assertSee(__('learning.verify.revoked'));
        $this->getJson(route('learning.certificates.credential', $certificate->verification_code))
            ->assertOk()
            ->assertJsonPath('credentialStatus.revoked', true);
        $pdfHtml = view('learning.pdf.certificate', ['certificate' => $certificate, 'verifyUrl' => 'https://example.test/pruefen', 'qrImage' => ''])->render();
        $this->assertStringContainsString('Prüfung wurde nicht selbst abgelegt', $pdfHtml);

        $rows = app(QualificationDossierService::class)->forUsers(collect([$learner]), Carbon::tomorrow());
        $this->assertTrue($rows[0]['certificates'][0]['revoked']);
        $this->assertFalse($rows[0]['certificates'][0]['valid_on']);

        $this->actingAs($manager)->get(route('learning.courses.enrollments.index', $course))
            ->assertOk()
            ->assertSee(__('learning.badge.certificate_revoked', ['number' => $certificate->number]))
            ->assertDontSee(route('learning.courses.enrollments.certificate.revoke', [$course, $enrollment]), false);

        // Ein zweiter Widerruf findet kein gültiges Zertifikat mehr.
        $this->actingAs($manager)->post(route('learning.courses.enrollments.certificate.revoke.store', [$course, $enrollment]), ['reason' => 'noch einmal'])
            ->assertNotFound();
    }

    public function test_revocation_needs_the_management_permission(): void {
        [$enrollment, $course, , $certificate] = $this->certified();
        $worker = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($worker)->post(route('learning.courses.enrollments.certificate.revoke.store', [$course, $enrollment]), ['reason' => 'Unberechtigt'])
            ->assertForbidden();
        $this->assertFalse($certificate->refresh()->isRevoked());
    }

    public function test_service_rejects_empty_reason_and_double_revocation(): void {
        [, , , $certificate] = $this->certified();
        $service = app(LearningCompletionService::class);

        try {
            $service->revoke($certificate, '   ');
            $this->fail('Ein leerer Grund darf nicht genügen.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $service->revoke($certificate, 'Fehlerhaft ausgestellt');
        $this->expectException(ValidationException::class);
        $service->revoke($certificate->refresh(), 'Noch einmal');
    }

    public function test_access_link_of_an_external_can_be_revoked(): void {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Baustellenordnung']);
        $courses->addUnit($course, ['title' => 'Regeln']);
        $courses->release($course->refresh(), null);
        $guest = ExternalParticipant::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Vera Beispiel']);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $guest);
        $token = app(LearningAccessService::class)->issue($enrollment);
        $manager = $this->manager();

        $this->actingAs($manager)->get(route('learning.courses.enrollments.index', $course))
            ->assertOk()
            ->assertSee(route('learning.courses.enrollments.access-link.revoke', [$course, $enrollment]), false);

        $this->actingAs($manager)->post(route('learning.courses.enrollments.access-link.revoke', [$course, $enrollment]))
            ->assertRedirect(route('learning.courses.enrollments.index', $course))
            ->assertSessionHas('success', __('learning.flash.access_link_revoked', ['name' => 'Vera Beispiel']));

        $this->assertSame(0, LearningAccessToken::query()->where('learning_enrollment_id', $enrollment->id)->whereNull('revoked_at')->count());
        $this->assertNull(app(LearningAccessService::class)->resolve($token));
        $this->actingAs($manager)->get(route('learning.courses.enrollments.index', $course))
            ->assertOk()
            ->assertDontSee(route('learning.courses.enrollments.access-link.revoke', [$course, $enrollment]), false);
    }
}
