<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningEventTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Event\{EventType, ParticipantStatus};
use App\Enums\Learning\{LearningEnrollmentStatus, LearningUnitKind};
use App\Models\{Event, EventParticipant, User};
use App\Models\Learning\{LearningEnrollment, LearningUnit};
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningEventService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Präsenz und Blended (Feature 149, MVP-741): Anmeldung mit Kapazität,
 * Warteliste mit Nachrücken, Fristen und der Check-in als eigentlicher
 * Nachweis.
 */
class LearningEventTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Carbon::setTestNow(Carbon::parse('2026-09-01 09:00:00'));
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): LearningEventService {
        return app(LearningEventService::class);
    }

    /** @return array{0: LearningUnit, 1: Event} */
    private function courseWithEvent(?int $maxParticipants = null, array $unitAttributes = []): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Erste Hilfe Präsenz']);

        $event = Event::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Erste-Hilfe-Kurs',
            'event_type' => EventType::Training->value,
            'started_at' => Carbon::parse('2026-09-10 09:00:00'),
            'ended_at' => Carbon::parse('2026-09-10 17:00:00'),
            'max_participants' => $maxParticipants,
        ]);

        $courses->addUnit($course, array_merge([
            'title' => 'Präsenztag',
            'kind' => LearningUnitKind::Event->value,
        ], $unitAttributes));

        $unit = $course->refresh()->units()->firstOrFail();
        $unit->update(array_merge(['event_id' => $event->id], $unitAttributes['unit'] ?? []));

        $courses->release($course->refresh(), null);

        return [$unit->refresh(), $event->refresh()];
    }

    private function enroll(LearningUnit $unit, ?User $user = null): LearningEnrollment {
        $user ??= User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        return app(LearningEnrollmentService::class)->enroll($unit->course, $user);
    }

    public function test_anmeldung_belegt_einen_platz(): void {
        [$unit] = $this->courseWithEvent(maxParticipants: 2);
        $enrollment = $this->enroll($unit);

        $participant = $this->service()->register($enrollment, $unit);

        $this->assertSame(ParticipantStatus::Accepted, $participant->status);
    }

    public function test_voller_termin_fuehrt_auf_die_warteliste(): void {
        [$unit] = $this->courseWithEvent(maxParticipants: 1);
        $first = $this->enroll($unit);
        $second = $this->enroll($unit);

        $this->service()->register($first, $unit);
        $waiting = $this->service()->register($second, $unit);

        $this->assertSame(ParticipantStatus::Waitlisted, $waiting->status);
    }

    public function test_absage_laesst_die_warteliste_nachruecken(): void {
        [$unit, $event] = $this->courseWithEvent(maxParticipants: 1);
        $first = $this->enroll($unit);
        $second = $this->enroll($unit);
        $this->service()->register($first, $unit);
        $waiting = $this->service()->register($second, $unit);

        $this->service()->cancel($first, $unit);

        $this->assertSame(ParticipantStatus::Accepted, $waiting->refresh()->status, 'Ein frei werdender Platz darf nicht leer bleiben.');
        $this->assertSame(
            ParticipantStatus::Declined,
            EventParticipant::query()->where('event_id', $event->id)->where('user_id', $first->user_id)->firstOrFail()->status
        );
    }

    public function test_ohne_kapazitaetsgrenze_ist_immer_platz(): void {
        [$unit] = $this->courseWithEvent();
        $a = $this->enroll($unit);
        $b = $this->enroll($unit);

        $this->assertSame(ParticipantStatus::Accepted, $this->service()->register($a, $unit)->status);
        $this->assertSame(ParticipantStatus::Accepted, $this->service()->register($b, $unit)->status);
    }

    public function test_check_in_schliesst_die_lerneinheit_ab(): void {
        [$unit] = $this->courseWithEvent(maxParticipants: 5);
        $enrollment = $this->enroll($unit);
        $this->service()->register($enrollment, $unit);

        $participant = $this->service()->checkIn($enrollment, $unit);

        $this->assertSame(ParticipantStatus::Attended, $participant->status);
        $this->assertSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_ohne_anmeldung_kein_check_in(): void {
        [$unit] = $this->courseWithEvent(maxParticipants: 5);
        $enrollment = $this->enroll($unit);

        $this->expectException(ValidationException::class);
        $this->service()->checkIn($enrollment, $unit);
    }

    public function test_warteliste_darf_nicht_einchecken(): void {
        [$unit] = $this->courseWithEvent(maxParticipants: 1);
        $first = $this->enroll($unit);
        $second = $this->enroll($unit);
        $this->service()->register($first, $unit);
        $this->service()->register($second, $unit);

        $this->expectException(ValidationException::class);
        $this->service()->checkIn($second, $unit);
    }

    public function test_anmeldefrist_wird_eingehalten(): void {
        [$unit] = $this->courseWithEvent(maxParticipants: 5);
        $unit->update(['registration_lead_hours' => 48]);
        $enrollment = $this->enroll($unit);

        // Termin am 10.09. 09:00, Frist 48 h ⇒ Anmeldeschluss 08.09. 09:00.
        Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00'));

        $this->expectException(ValidationException::class);
        $this->service()->register($enrollment, $unit->refresh());
    }

    public function test_absagefrist_wird_eingehalten(): void {
        [$unit] = $this->courseWithEvent(maxParticipants: 5);
        $unit->update(['cancellation_lead_hours' => 24]);
        $enrollment = $this->enroll($unit);
        $this->service()->register($enrollment, $unit->refresh());

        Carbon::setTestNow(Carbon::parse('2026-09-10 08:00:00'));

        $this->expectException(ValidationException::class);
        $this->service()->cancel($enrollment, $unit->refresh());
    }

    public function test_vergangener_termin_nimmt_keine_anmeldung_mehr(): void {
        [$unit] = $this->courseWithEvent(maxParticipants: 5);
        $enrollment = $this->enroll($unit);

        Carbon::setTestNow(Carbon::parse('2026-09-11 09:00:00'));

        $this->expectException(ValidationException::class);
        $this->service()->register($enrollment, $unit->refresh());
    }

    // ── QR-Check-in und Teilnehmerliste (MVP-741) ───────────────────────

    public function test_selbst_checkin_nur_im_zeitfenster(): void {
        [$unit, $event] = $this->courseWithEvent();
        $enrollment = $this->enroll($unit);
        $this->service()->register($enrollment, $unit);
        $user = $enrollment->user;

        // Eine Woche vorher: der abfotografierte Code darf nicht ziehen.
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:00:00'));
        $this->assertFalse($this->service()->isCheckInOpen($event));

        try {
            $this->service()->checkIn($enrollment->refresh(), $unit, $user);
            $this->fail('Check-in außerhalb des Zeitfensters muss scheitern.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('event', $e->errors());
        }

        // Kurz vor Beginn: offen.
        Carbon::setTestNow(Carbon::parse('2026-09-10 08:45:00'));
        $this->assertTrue($this->service()->isCheckInOpen($event));

        $participant = $this->service()->checkIn($enrollment->refresh(), $unit, $user);
        $this->assertSame(ParticipantStatus::Attended, $participant->status);
    }

    public function test_kursleitung_darf_ausserhalb_des_fensters_nachtragen(): void {
        [$unit] = $this->courseWithEvent();
        $enrollment = $this->enroll($unit);
        $this->service()->register($enrollment, $unit);

        // Die Leitung hat die Liste gesehen — der QR-Code niemanden.
        Carbon::setTestNow(Carbon::parse('2026-09-12 09:00:00'));
        $leader = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $participant = $this->service()->checkIn($enrollment->refresh(), $unit, $leader);

        $this->assertSame(ParticipantStatus::Attended, $participant->status);
    }

    public function test_checkin_seite_braucht_eine_gueltige_signatur(): void {
        [$unit] = $this->courseWithEvent();
        $enrollment = $this->enroll($unit);
        $this->service()->register($enrollment, $unit);

        // Ohne Signatur: abgewiesen.
        $this->actingAs($enrollment->user)
            ->get(route('learning.checkin.show', $unit->sqid))
            ->assertForbidden();

        Carbon::setTestNow(Carbon::parse('2026-09-10 08:45:00'));

        $signed = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'learning.checkin.show',
            Carbon::parse('2026-09-10 19:00:00'),
            ['unit' => $unit->sqid],
        );

        $this->actingAs($enrollment->user)->get($signed)->assertOk();

        // Bestätigt wird per POST — der bloße Aufruf setzt nichts.
        $this->assertSame(
            ParticipantStatus::Accepted,
            EventParticipant::query()->where('user_id', $enrollment->user_id)->first()?->status
        );

        $this->actingAs($enrollment->user)
            ->post(route('learning.checkin.store', $unit->sqid))
            ->assertRedirect();

        $this->assertSame(
            ParticipantStatus::Attended,
            EventParticipant::query()->where('user_id', $enrollment->user_id)->first()?->status
        );
    }

    public function test_teilnehmerliste_kommt_als_pdf(): void {
        [$unit] = $this->courseWithEvent();
        $enrollment = $this->enroll($unit);
        $this->service()->register($enrollment, $unit);

        $author = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($author)->get(route('learning.courses.units.attendance-list', [
            'course' => $unit->course->sqid,
            'unit' => $unit->sqid,
        ]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
