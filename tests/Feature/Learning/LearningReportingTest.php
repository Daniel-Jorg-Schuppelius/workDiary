<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningReportingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Models\Learning\LearningCourse;
use App\Models\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningGamificationService, LearningReportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kursanalyse und Gamification (Feature 149, MVP-747).
 *
 * Zwei Schranken aus dem Konzept werden hier geprüft: kleine Gruppen
 * bekommen **keine Quote** (sonst Rückrechnung auf Personen), und die
 * Bestenliste erscheint nur mit **Organisations-Freigabe und persönlichem
 * Opt-in**.
 */
class LearningReportingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function reports(): LearningReportService {
        return app(LearningReportService::class);
    }

    private function game(): LearningGamificationService {
        return app(LearningGamificationService::class);
    }

    private function releasedCourse(string $title = 'Brandschutz', int $points = 10): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => $title]);
        $courses->addUnit($course, ['title' => 'Einheit', 'points' => $points]);
        $courses->release($course->refresh(), null);

        return $course->refresh();
    }

    private function learner(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    private function complete(LearningCourse $course, User $user): void {
        $enrollments = app(LearningEnrollmentService::class);
        $enrollment = $enrollments->enroll($course, $user);
        $enrollments->completeUnit($enrollment, $course->units()->firstOrFail());
    }

    public function test_kleine_gruppen_bekommen_keine_quote(): void {
        $course = $this->releasedCourse();
        $enrollments = app(LearningEnrollmentService::class);

        // Drei Einschreibungen — unter der Mindestgröße von fünf.
        for ($i = 0; $i < 3; $i++) {
            $enrollments->enroll($course, $this->learner());
        }

        $row = collect($this->reports()->courseCompletion($this->organization))->firstOrFail();

        $this->assertSame(3, $row['enrolled']);
        $this->assertNull($row['rate'], 'Unter der Mindestgröße darf keine Quote erscheinen.');
        $this->assertTrue($row['suppressed']);
    }

    public function test_ab_der_mindestgroesse_gibt_es_eine_quote(): void {
        $course = $this->releasedCourse();
        $enrollments = app(LearningEnrollmentService::class);

        for ($i = 0; $i < LearningReportService::MIN_GROUP; $i++) {
            $user = $this->learner();
            $enrollment = $enrollments->enroll($course, $user);
            if ($i < 2) {
                $enrollments->completeUnit($enrollment, $course->units()->firstOrFail());
            }
        }

        $row = collect($this->reports()->courseCompletion($this->organization))->firstOrFail();

        $this->assertSame(LearningReportService::MIN_GROUP, $row['enrolled']);
        $this->assertSame(2, $row['completed']);
        $this->assertSame(40.0, $row['rate']);
    }

    public function test_kennzahlen_zaehlen_kurse_und_abschluesse(): void {
        $course = $this->releasedCourse();
        $this->complete($course, $this->learner());

        $summary = $this->reports()->summary($this->organization);

        $this->assertSame(1, $summary['courses']);
        $this->assertSame(1, $summary['enrollments']);
        $this->assertSame(1, $summary['completed']);
    }

    public function test_gamification_ist_im_auslieferungszustand_aus(): void {
        $this->assertFalse($this->game()->isEnabled($this->organization));
        $this->assertFalse($this->game()->isLeaderboardEnabled($this->organization));
        $this->assertSame([], $this->game()->leaderboard($this->organization));
    }

    public function test_bestenliste_zeigt_nur_personen_mit_opt_in(): void {
        $this->organization->update(['settings' => ['learning' => ['gamification' => [
            'enabled' => true,
            'leaderboard' => true,
        ]]]]);

        $course = $this->releasedCourse(points: 10);
        $willing = $this->learner();
        $willing->update(['preferences' => ['learning' => ['leaderboard_opt_in' => true]]]);
        $quiet = $this->learner();

        $this->complete($course, $willing);
        $this->complete($course, $quiet);

        $board = $this->game()->leaderboard($this->organization->refresh());

        $this->assertCount(1, $board, 'Ohne Opt-in erscheint niemand.');
        $this->assertSame($willing->id, $board[0]['user']->id);
        $this->assertSame(10, $board[0]['points']);
    }

    public function test_abzeichen_folgen_der_zahl_abgeschlossener_kurse(): void {
        $user = $this->learner();
        $this->assertSame([], $this->game()->badgesFor($user));

        $this->complete($this->releasedCourse('Kurs A'), $user);

        $this->assertSame(['first_course'], $this->game()->badgesFor($user->refresh()));
    }

    public function test_auswertung_braucht_das_leserecht(): void {
        $this->actingAs(User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]))
            ->get(route('reports.learning'))
            ->assertOk();

        $this->actingAs($this->learner())
            ->get(route('reports.learning'))
            ->assertForbidden();
    }

    // ── Export der Kursanalyse (MVP-747) ────────────────────────────────

    public function test_export_haelt_die_unterdrueckung_ein(): void {
        // Vier Einschreibungen: unter der Schwelle von fünf. Ein Export darf
        // die Datensparsamkeit nicht aushebeln — sonst wäre sie eine
        // Anzeigefrage statt einer Regel.
        $course = $this->releasedCourse('Kleine Gruppe');

        for ($i = 0; $i < 4; $i++) {
            $user = $this->learner();
            $this->complete($course, $user);
        }

        $viewer = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($viewer)->get(route('reports.learning', ['export' => 'csv']));

        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('Kleine Gruppe', $body);
        $this->assertStringContainsString((string) __('learning.field.suppressed'), $body);
        $this->assertStringNotContainsString('100', $body);
    }

    public function test_export_als_pdf_kommt_zustande(): void {
        $this->releasedCourse('Brandschutz');
        $viewer = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($viewer)->get(route('reports.learning', ['export' => 'pdf']));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
