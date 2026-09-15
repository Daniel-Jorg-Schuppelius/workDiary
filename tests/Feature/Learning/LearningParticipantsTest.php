<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningParticipantsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningEnrollmentSource, LearningEnrollmentStatus};
use App\Mail\LearningAccessLinkMail;
use App\Models\{ExternalParticipant, Organization, User};
use App\Models\Learning\{LearningAccessToken, LearningCourse, LearningEnrollment};
use App\Services\Learning\{LearningAccessService, LearningBookingService, LearningCourseService, LearningEnrollmentService};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Teilnehmerverwaltung je Kurs (Feature 149, MVP-778): manuell einschreiben,
 * Frist/Zugang mit Begründung ändern, stornieren, Einstiegslink für Externe —
 * und die Buchungszusage, die den Link von selbst verschickt.
 */
class LearningParticipantsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function manager(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    private function learner(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    private function course(bool $release = true, array $attributes = []): LearningCourse {
        $service = app(LearningCourseService::class);
        $course = $service->createCourse($this->organization, null, ['title' => 'Ladungssicherung'] + $attributes);
        $service->addUnit($course, ['title' => 'Grundlagen']);
        if ($release) {
            $service->release($course->refresh(), null);
        }

        return $course->refresh();
    }

    private function external(array $attributes = []): ExternalParticipant {
        return ExternalParticipant::factory()->create(['organization_id' => $this->organization->id] + $attributes);
    }

    public function test_teilnehmerliste_zeigt_einschreibungen_und_zaehler(): void {
        $course = $this->course();
        $learner = $this->learner();
        app(LearningEnrollmentService::class)->enroll($course, $learner);

        $this->actingAs($this->manager())
            ->get(route('learning.courses.enrollments.index', $course))
            ->assertOk()
            ->assertSee($learner->name)
            ->assertSee(LearningEnrollmentStatus::Assigned->label());

        $this->actingAs($this->manager())
            ->get(route('learning.courses.show', $course))
            ->assertOk()
            ->assertSee(__('learning.action.participants'));
    }

    public function test_ohne_recht_keine_teilnehmerliste(): void {
        $course = $this->course();

        $this->actingAs($this->learner())
            ->get(route('learning.courses.enrollments.index', $course))
            ->assertForbidden();
    }

    public function test_person_der_organisation_wird_eingeschrieben(): void {
        $course = $this->course();
        $learner = $this->learner();
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('learning.courses.enrollments.store', $course), [
                'learner_kind' => 'user',
                'user_id' => $learner->sqid,
                'due_at' => now()->addDays(14)->toDateString(),
                'reason' => 'Neue Aufgabe im Lager',
            ])
            ->assertRedirect(route('learning.courses.enrollments.index', $course));

        $enrollment = LearningEnrollment::query()->where('user_id', $learner->id)->firstOrFail();
        $this->assertSame(LearningEnrollmentSource::Manual, $enrollment->source);
        $this->assertSame($manager->id, $enrollment->assigned_by_user_id);
        $this->assertSame(now()->addDays(14)->toDateString(), $enrollment->due_at?->toDateString());
        $this->assertSame('Neue Aufgabe im Lager', $enrollment->events()->firstOrFail()->reason);
    }

    public function test_person_aus_fremder_organisation_wird_abgewiesen(): void {
        $course = $this->course();
        $foreign = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->actingAs($this->manager())
            ->post(route('learning.courses.enrollments.store', $course), [
                'learner_kind' => 'user',
                'user_id' => $foreign->sqid,
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(0, LearningEnrollment::query()->count());
    }

    public function test_entwurf_nimmt_keine_einschreibung_an(): void {
        $course = $this->course(release: false);

        $this->actingAs($this->manager())
            ->post(route('learning.courses.enrollments.store', $course), [
                'learner_kind' => 'user',
                'user_id' => $this->learner()->sqid,
            ])
            ->assertSessionHasErrors('course');
    }

    public function test_neue_externe_person_bekommt_ihren_einstiegslink(): void {
        Mail::fake();
        $course = $this->course();

        $this->actingAs($this->manager())
            ->post(route('learning.courses.enrollments.store', $course), [
                'learner_kind' => 'external_new',
                'name' => 'Petra Prüferin',
                'email' => 'Petra@Example.org',
                'party' => 'inspector',
                'send_link' => '1',
            ])
            ->assertRedirect(route('learning.courses.enrollments.index', $course))
            ->assertSessionHas('success', __('learning.flash.participant_enrolled_link_sent', ['name' => 'Petra Prüferin']));

        $participant = ExternalParticipant::query()->where('name', 'Petra Prüferin')->firstOrFail();
        $this->assertSame('petra@example.org', $participant->email);
        $this->assertSame($course->getMorphClass(), $participant->subject_type);
        $this->assertSame([], $participant->abilities);

        $enrollment = LearningEnrollment::query()->where('external_participant_id', $participant->id)->firstOrFail();
        $token = LearningAccessToken::query()->where('learning_enrollment_id', $enrollment->id)->firstOrFail();

        Mail::assertQueued(LearningAccessLinkMail::class, function (LearningAccessLinkMail $mail) use ($token, $course): bool {
            $plain = basename($mail->accessUrl);

            return $mail->hasTo('petra@example.org')
                && $mail->courseTitle === $course->title
                && CryptoHelper::hash($plain) === $token->token_hash;
        });
    }

    public function test_ohne_email_wird_kein_link_erzeugt(): void {
        Mail::fake();
        $course = $this->course();
        $participant = $this->external(['email' => null]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $participant);

        $this->actingAs($this->manager())
            ->post(route('learning.courses.enrollments.access-link', [$course, $enrollment]))
            ->assertRedirect(route('learning.courses.enrollments.index', $course))
            ->assertSessionHas('error');

        Mail::assertNothingQueued();
        $this->assertSame(0, LearningAccessToken::query()->count());
    }

    public function test_neuer_link_aus_der_liste_entwertet_den_alten(): void {
        Mail::fake();
        $course = $this->course();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $this->external());
        $first = app(LearningAccessService::class)->issue($enrollment);

        $this->actingAs($this->manager())
            ->post(route('learning.courses.enrollments.access-link', [$course, $enrollment]))
            ->assertSessionHas('success');

        Mail::assertQueued(LearningAccessLinkMail::class);
        $this->assertNull(app(LearningAccessService::class)->resolve($first));
        $this->assertSame(2, LearningAccessToken::query()->count());
    }

    public function test_link_endet_mit_dem_zugang_der_einschreibung(): void {
        Mail::fake();
        $course = $this->course();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $this->external(), [
            'access_until' => now()->addDays(5)->toDateString(),
        ]);

        app(LearningAccessService::class)->deliver($enrollment);

        $token = LearningAccessToken::query()->firstOrFail();
        $this->assertTrue($token->expires_at->lessThanOrEqualTo(now()->addDays(5)->endOfDay()));
    }

    public function test_frist_aendern_braucht_eine_begruendung(): void {
        $course = $this->course();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $this->learner());

        $this->actingAs($this->manager())
            ->patch(route('learning.courses.enrollments.update', [$course, $enrollment]), [
                'due_at' => now()->addMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->manager())
            ->patch(route('learning.courses.enrollments.update', [$course, $enrollment]), [
                'due_at' => now()->addMonth()->toDateString(),
                'access_until' => now()->addMonths(2)->toDateString(),
                'reason' => 'Krankheitsbedingt verschoben',
            ])
            ->assertRedirect(route('learning.courses.enrollments.index', $course));

        $enrollment->refresh();
        $this->assertSame(now()->addMonth()->toDateString(), $enrollment->due_at?->toDateString());
        $this->assertSame(now()->addMonths(2)->toDateString(), $enrollment->access_until?->toDateString());
        $this->assertSame('Krankheitsbedingt verschoben', $enrollment->events()->latest('id')->firstOrFail()->reason);
    }

    public function test_zugang_darf_nicht_vor_der_faelligkeit_enden(): void {
        $course = $this->course();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $this->learner());

        $this->expectException(ValidationException::class);

        app(LearningEnrollmentService::class)->extendAccess(
            $enrollment,
            now()->addDays(10)->toDateString(),
            now()->addDays(5)->toDateString(),
            null,
            'Testfall',
        );
    }

    public function test_abgeschlossene_einschreibung_laesst_sich_nicht_verlaengern(): void {
        $course = $this->course();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $this->learner());
        app(LearningEnrollmentService::class)->completeUnit($enrollment, $course->units()->firstOrFail());

        $this->expectException(ValidationException::class);

        app(LearningEnrollmentService::class)->extendAccess($enrollment->refresh(), null, now()->addYear()->toDateString(), null, 'Nachträglich');
    }

    public function test_stornieren_mit_grund(): void {
        $course = $this->course();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $this->learner());

        $this->actingAs($this->manager())
            ->post(route('learning.courses.enrollments.cancel', [$course, $enrollment]), [
                'reason' => 'Mitarbeiter ausgeschieden',
            ])
            ->assertRedirect(route('learning.courses.enrollments.index', $course));

        $this->assertSame(LearningEnrollmentStatus::Cancelled, $enrollment->refresh()->status);
    }

    public function test_pflicht_einschreibung_bleibt_unstornierbar(): void {
        $course = $this->course();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $this->learner(), [
            'source' => LearningEnrollmentSource::Requirement->value,
        ]);

        $this->actingAs($this->manager())
            ->post(route('learning.courses.enrollments.cancel', [$course, $enrollment]), ['reason' => 'Versuch'])
            ->assertSessionHasErrors('status');

        $this->assertSame(LearningEnrollmentStatus::Assigned, $enrollment->refresh()->status);
    }

    public function test_einschreibung_eines_fremden_kurses_ist_404(): void {
        $course = $this->course();
        $other = $this->course(attributes: ['title' => 'Anderer Kurs']);
        $enrollment = app(LearningEnrollmentService::class)->enroll($other, $this->learner());

        $this->actingAs($this->manager())
            ->get(route('learning.courses.enrollments.edit', [$course, $enrollment]))
            ->assertNotFound();
    }

    public function test_buchungszusage_schickt_dem_externen_den_link(): void {
        Mail::fake();
        $course = $this->course(attributes: ['access_kind' => 'bookable']);
        $participant = $this->external(['email' => 'gast@example.org']);
        $bookings = app(LearningBookingService::class);
        $booking = $bookings->request($course, $participant);

        $bookings->confirm($booking, $this->manager());

        Mail::assertQueued(LearningAccessLinkMail::class, fn (LearningAccessLinkMail $mail): bool => $mail->hasTo('gast@example.org'));
        $this->assertSame(1, LearningAccessToken::query()->count());
    }

    public function test_buchungszusage_fuer_konto_nutzer_schickt_keinen_link(): void {
        Mail::fake();
        $course = $this->course(attributes: ['access_kind' => 'bookable']);
        $bookings = app(LearningBookingService::class);
        $booking = $bookings->request($course, $this->learner());

        $bookings->confirm($booking, $this->manager());

        Mail::assertNothingQueued();
    }
}
