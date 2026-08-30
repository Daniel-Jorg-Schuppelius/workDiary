<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningTimeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Enums\Learning\LearningTimePolicy;
use App\Models\{Attendance, ExternalParticipant, User};
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningTimeSession};
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningTimeService, LearningWorkTimeClassifier};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lernzeit (Feature 149, MVP-749).
 *
 * Die drei Sätze, die dieser Test absichert:
 *  - Pflichtkurse starten nicht außerhalb der Arbeitszeit (§ 12 Abs. 1 ArbSchG).
 *  - Innerhalb der Arbeitszeit entsteht KEINE zweite Buchung.
 *  - Außerhalb entsteht eine Anwesenheitsspanne, damit die vorhandenen
 *    ArbZG-Prüfungen greifen.
 */
class LearningTimeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        // Garantierter Reset: bleibt eine Testzeit stehen, sieht der nächste
        // Test im selben Worker eine falsche Systemzeit — das äußert sich
        // später als scheinbar zufälliger Fehler in einem fremden Test.
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function time(): LearningTimeService {
        return app(LearningTimeService::class);
    }

    private function courseWith(LearningTimePolicy $policy): LearningCourse {
        $service = app(LearningCourseService::class);
        $course = $service->createCourse($this->organization, null, [
            'title' => 'Brandschutz kompakt',
            'time_policy' => $policy->value,
        ]);
        $service->addUnit($course, ['title' => 'Einführung']);
        $service->release($course->refresh(), null);

        return $course->refresh();
    }

    private function enrollmentFor(LearningTimePolicy $policy, ?User $user = null): LearningEnrollment {
        $user ??= User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        return app(LearningEnrollmentService::class)->enroll($this->courseWith($policy), $user);
    }

    /** Laufender Stempel, der den Lernzeitraum abdeckt. */
    private function clockIn(User $user, Carbon $start): Attendance {
        return Attendance::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'started_at' => $start,
            'date' => $start->toDateString(),
            'source' => AttendanceSource::Clock->value,
            'status' => AttendanceStatus::Open->value,
        ]);
    }

    public function test_lernen_in_der_arbeitszeit_erzeugt_keine_zweite_buchung(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->clockIn($user, Carbon::parse('2026-09-01 08:00:00'));
        $enrollment = $this->enrollmentFor(LearningTimePolicy::WorkTimeRequired, $user);

        $session = $this->time()->start($enrollment);
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:30:00'));
        $session = $this->time()->stop($session);

        $this->assertSame(LearningWorkTimeClassifier::INSIDE, $session->classification);
        $this->assertNull($session->attendance_id, 'Innerhalb der Arbeitszeit darf keine zweite Spanne entstehen.');
        $this->assertSame(1, Attendance::query()->where('user_id', $user->id)->count());
        Carbon::setTestNow();
    }

    public function test_pflichtkurs_startet_nicht_ausserhalb_der_arbeitszeit(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 21:00:00'));
        $enrollment = $this->enrollmentFor(LearningTimePolicy::WorkTimeRequired);

        $this->expectException(ValidationException::class);
        try {
            $this->time()->start($enrollment);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_angeordnete_fortbildung_am_abend_erzeugt_eine_anwesenheitsspanne(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = $this->enrollmentFor(LearningTimePolicy::AlwaysCounts, $user);

        $session = $this->time()->start($enrollment);
        Carbon::setTestNow(Carbon::parse('2026-09-01 21:15:00'));
        $session = $this->time()->stop($session);

        $this->assertNotSame(LearningWorkTimeClassifier::INSIDE, $session->classification);
        $this->assertNotNull($session->attendance_id);

        $attendance = $session->attendance;
        $this->assertNotNull($attendance);
        $this->assertSame(AttendanceSource::Learning, $attendance->source);
        $this->assertSame(75, $attendance->duration_minutes);
        Carbon::setTestNow();
    }

    public function test_freiwilliges_angebot_erzeugt_keine_arbeitszeit(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = $this->enrollmentFor(LearningTimePolicy::VoluntaryUnpaid, $user);

        $session = $this->time()->start($enrollment);
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:45:00'));
        $session = $this->time()->stop($session);

        $this->assertNull($session->attendance_id, 'Freiwillig und unbezahlt erzeugt keine Arbeitszeit.');
        $this->assertSame(0, Attendance::query()->where('user_id', $user->id)->count());
        $this->assertGreaterThan(0, $session->active_seconds, 'Die Sitzung bleibt trotzdem im Journal.');
        Carbon::setTestNow();
    }

    public function test_doppelter_start_erzeugt_keine_zweite_sitzung(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $enrollment = $this->enrollmentFor(LearningTimePolicy::AlwaysCounts);

        $first = $this->time()->start($enrollment);
        $second = $this->time()->start($enrollment);

        $this->assertSame($first->id, $second->id);
        Carbon::setTestNow();
    }

    public function test_externe_lernende_erzeugen_keine_arbeitszeit(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $course = $this->courseWith(LearningTimePolicy::AlwaysCounts);
        $external = ExternalParticipant::factory()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $external);

        $session = $this->time()->start($enrollment);
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:30:00'));
        $session = $this->time()->stop($session);

        $this->assertNull($session->attendance_id);
        $this->assertNull($session->user_id);
        Carbon::setTestNow();
    }

    public function test_einheit_abschliessen_beendet_die_laufende_lernzeit(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = $this->enrollmentFor(LearningTimePolicy::AlwaysCounts, $user);
        $unit = $enrollment->course->units()->firstOrFail();

        $this->actingAs($user)->post(route('learning.my.time.start', $enrollment))->assertRedirect();
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:20:00'));
        $this->actingAs($user)->post(route('learning.my.units.complete', [$enrollment, $unit]))->assertRedirect();

        $this->assertNull($this->time()->openSessionFor($enrollment->refresh()), 'Die Sitzung darf nicht offen weiterlaufen.');
        Carbon::setTestNow();
    }

    public function test_summen_trennen_innerhalb_und_ausserhalb(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = $this->enrollmentFor(LearningTimePolicy::AlwaysCounts, $user);

        $session = $this->time()->start($enrollment);
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:30:00'));
        $this->time()->stop($session);

        $totals = $this->time()->secondsByClassification($enrollment);
        $this->assertSame(0, $totals['inside']);
        $this->assertSame(1800, $totals['outside']);
        Carbon::setTestNow();
    }

    // ── Lebenszeichen und Leerlauf (MVP-749) ────────────────────────────

    public function test_liegengebliebene_sitzung_ohne_lebenszeichen_bucht_nichts(): void {
        // Der teuerste Fehler wäre hier: ein offener Tab, den niemand
        // benutzt, gebucht als gearbeitete Stunden. Ein ausdrückliches
        // „Stopp" ist dagegen selbst ein Anwesenheitsnachweis — siehe den
        // Test zur angeordneten Abendfortbildung.
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $enrollment = $this->enrollmentFor(LearningTimePolicy::AlwaysCounts);

        $this->time()->start($enrollment);

        Carbon::setTestNow(Carbon::parse('2026-09-01 23:00:00'));
        $closed = $this->time()->closeStaleSessions();

        $session = LearningTimeSession::query()->where('learning_enrollment_id', $enrollment->id)->firstOrFail();

        $this->assertSame(1, $closed);
        $this->assertSame(0, $session->active_seconds);
        $this->assertNull($session->attendance_id);
        $this->assertSame('2026-09-01 20:00:00', $session->ended_at?->format('Y-m-d H:i:s'));
    }

    public function test_lebenszeichen_begrenzt_die_gebuchte_zeit(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $enrollment = $this->enrollmentFor(LearningTimePolicy::AlwaysCounts);
        $session = $this->time()->start($enrollment);

        // Eine halbe Stunde gelernt …
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:30:00'));
        $this->time()->heartbeat($session);

        // … dann zweieinhalb Stunden Stille.
        Carbon::setTestNow(Carbon::parse('2026-09-01 23:00:00'));
        $stopped = $this->time()->stop($session->refresh());

        $this->assertSame(1800, $stopped->active_seconds);
        $this->assertSame('2026-09-01 20:30:00', $stopped->ended_at?->format('Y-m-d H:i:s'));

        $attendance = Attendance::query()->where('source', AttendanceSource::Learning->value)->firstOrFail();
        $this->assertSame(30, $attendance->duration_minutes);
    }

    public function test_kommando_schliesst_liegengebliebene_sitzungen(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $enrollment = $this->enrollmentFor(LearningTimePolicy::AlwaysCounts);
        $session = $this->time()->start($enrollment);

        Carbon::setTestNow(Carbon::parse('2026-09-01 20:10:00'));
        $this->time()->heartbeat($session);

        Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00'));

        $this->artisan('learning:close-stale-sessions')
            ->expectsOutputToContain('1')
            ->assertSuccessful();

        $this->assertNotNull($session->refresh()->ended_at);
    }

    public function test_kehraus_schliesst_liegengebliebene_sitzungen(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $enrollment = $this->enrollmentFor(LearningTimePolicy::AlwaysCounts);
        $session = $this->time()->start($enrollment);

        Carbon::setTestNow(Carbon::parse('2026-09-01 20:10:00'));
        $this->time()->heartbeat($session);

        // Browser zu — es kommt kein Stopp mehr.
        Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00'));
        $closed = $this->time()->closeStaleSessions();

        $this->assertSame(1, $closed);
        $this->assertSame('2026-09-01 20:10:00', $session->refresh()->ended_at?->format('Y-m-d H:i:s'));
    }

    // ── Freigabeweg (MVP-749) ───────────────────────────────────────────

    public function test_freigabepflichtige_zeit_wird_erst_mit_der_zusage_gebucht(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $enrollment = $this->enrollmentFor(LearningTimePolicy::ApprovalRequired);
        $session = $this->time()->start($enrollment);

        Carbon::setTestNow(Carbon::parse('2026-09-01 21:00:00'));
        $this->time()->heartbeat($session);
        $stopped = $this->time()->stop($session->refresh());

        // Noch keine Anwesenheit: entschieden hat darüber niemand.
        $this->assertSame(LearningTimeSession::APPROVAL_PENDING, $stopped->approval_status);
        $this->assertNull($stopped->attendance_id);
        $this->assertSame(0, Attendance::query()->where('source', AttendanceSource::Learning->value)->count());

        $manager = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
        $approved = $this->time()->approve($stopped, $manager);

        $this->assertSame(LearningTimeSession::APPROVAL_APPROVED, $approved->approval_status);
        $this->assertNotNull($approved->attendance_id);
        $this->assertSame(60, Attendance::query()->where('source', AttendanceSource::Learning->value)->firstOrFail()->duration_minutes);
    }

    public function test_ablehnung_braucht_eine_begruendung_und_bucht_nichts(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $enrollment = $this->enrollmentFor(LearningTimePolicy::ApprovalRequired);
        $session = $this->time()->start($enrollment);

        Carbon::setTestNow(Carbon::parse('2026-09-01 21:00:00'));
        $this->time()->heartbeat($session);
        $stopped = $this->time()->stop($session->refresh());

        $manager = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        try {
            $this->time()->reject($stopped, $manager, '   ');
            $this->fail('Eine Ablehnung ohne Begründung muss scheitern.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $rejected = $this->time()->reject($stopped->refresh(), $manager, 'Nicht abgestimmt');

        // Die Sitzung bleibt im Journal — gelöscht wird nichts, sonst wäre
        // die Ablehnung nicht nachvollziehbar.
        $this->assertSame(LearningTimeSession::APPROVAL_REJECTED, $rejected->approval_status);
        $this->assertNull($rejected->attendance_id);
        $this->assertSame('Nicht abgestimmt', $rejected->approval_note);
        $this->assertSame(0, Attendance::query()->where('source', AttendanceSource::Learning->value)->count());
    }

    public function test_freigabeliste_zeigt_offene_faelle(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $user = User::factory()->aussendienst()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Petra Meier',
        ]);
        $enrollment = $this->enrollmentFor(LearningTimePolicy::ApprovalRequired, $user);
        $session = $this->time()->start($enrollment);

        Carbon::setTestNow(Carbon::parse('2026-09-01 21:00:00'));
        $this->time()->heartbeat($session);
        $this->time()->stop($session->refresh());

        $manager = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($manager)
            ->get(route('learning.time-approvals.index'))
            ->assertOk()
            ->assertSee('Petra Meier');
    }
}
