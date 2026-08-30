<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAssignmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningEnrollmentStatus, LearningSubmissionStatus, LearningUnitKind};
use App\Models\Learning\{LearningAssignment, LearningEnrollment, LearningSubmission};
use App\Models\User;
use App\Services\Learning\{LearningAssignmentService, LearningCourseService, LearningEnrollmentService, LearningGradebookService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Aufgaben, Bewertung und Notenbuch (Feature 149, MVP-739): eingefrorene
 * Rubrik, Rückgabe zur Überarbeitung, Vier-Augen-Bestätigung und der
 * Abschluss der Lerneinheit über den regulären Weg.
 */
class LearningAssignmentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function service(): LearningAssignmentService {
        return app(LearningAssignmentService::class);
    }

    /** @return array{0: LearningEnrollment, 1: LearningAssignment, 2: User} */
    private function scenario(array $attributes = []): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Erste Hilfe']);
        $courses->addUnit($course, ['title' => 'Praxisbericht', 'kind' => LearningUnitKind::Assignment->value]);
        $unit = $course->refresh()->units()->firstOrFail();

        $assignment = LearningAssignment::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => 'Praxisbericht',
            'submission_kind' => 'text',
            'points' => 10,
            'pass_percent' => 50,
        ], $attributes));

        $courses->release($course->refresh(), null);
        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $user);

        return [$enrollment, $assignment->refresh(), $user];
    }

    private function grader(): User {
        return User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    public function test_abgabe_landet_in_der_bewertungsliste(): void {
        [$enrollment, $assignment] = $this->scenario();

        $submission = $this->service()->submit($enrollment, $assignment, 'Mein Bericht');

        $this->assertSame(LearningSubmissionStatus::Submitted, $submission->status);
        $this->assertNotNull($submission->submitted_at);
        $this->assertSame(1, $this->service()->pendingQuery()->count());
    }

    public function test_aufgabe_mit_textpflicht_weist_leere_abgabe_ab(): void {
        [$enrollment, $assignment] = $this->scenario();

        $this->expectException(ValidationException::class);
        $this->service()->submit($enrollment, $assignment, '   ');
    }

    public function test_bewertung_ueber_die_rubrik_schliesst_die_einheit_ab(): void {
        [$enrollment, $assignment] = $this->scenario([
            'rubric' => [
                ['key' => 'inhalt', 'label' => 'Inhalt', 'weight' => 1, 'max_points' => 6],
                ['key' => 'form', 'label' => 'Form', 'weight' => 1, 'max_points' => 4],
            ],
        ]);
        $submission = $this->service()->submit($enrollment, $assignment, 'Bericht');

        $submission = $this->service()->grade($submission, ['inhalt' => 5, 'form' => 3], null, 'Gut gemacht', $this->grader());

        $this->assertSame(8, $submission->points_awarded);
        $this->assertSame(80, $submission->score_percent);
        $this->assertTrue($submission->passed);
        $this->assertSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);
        $this->assertNotNull($submission->rubric_snapshot, 'Die Rubrik der Bewertung wird eingefroren.');
    }

    public function test_rubrik_deckelt_punkte_je_kriterium(): void {
        [$enrollment, $assignment] = $this->scenario([
            'rubric' => [['key' => 'inhalt', 'label' => 'Inhalt', 'weight' => 1, 'max_points' => 4]],
        ]);
        $submission = $this->service()->submit($enrollment, $assignment, 'Bericht');

        $submission = $this->service()->grade($submission, ['inhalt' => 99], null, null, $this->grader());

        $this->assertSame(4, $submission->points_awarded, 'Mehr als das Kriterienmaximum ist nicht möglich.');
    }

    public function test_bewertung_ohne_rubrik_nutzt_die_gesamtpunktzahl(): void {
        [$enrollment, $assignment] = $this->scenario();
        $submission = $this->service()->submit($enrollment, $assignment, 'Bericht');

        $submission = $this->service()->grade($submission, [], 4, null, $this->grader());

        $this->assertSame(4, $submission->points_awarded);
        $this->assertSame(40, $submission->score_percent);
        $this->assertFalse($submission->passed);
        $this->assertNotSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_rueckgabe_erlaubt_eine_zweite_abgabe(): void {
        [$enrollment, $assignment] = $this->scenario();
        $submission = $this->service()->submit($enrollment, $assignment, 'Zu knapp');

        $submission = $this->service()->returnForRevision($submission, 'Bitte ausführlicher.', $this->grader());
        $this->assertSame(LearningSubmissionStatus::Returned, $submission->status);

        $submission = $this->service()->submit($enrollment, $assignment, 'Ausführlicher Bericht');
        $this->assertSame(LearningSubmissionStatus::Submitted, $submission->status);
        $this->assertSame(2, $submission->attempt_no, 'Der zweite Anlauf wird gezählt.');
    }

    public function test_rueckgabe_ohne_begruendung_wird_abgewiesen(): void {
        [$enrollment, $assignment] = $this->scenario();
        $submission = $this->service()->submit($enrollment, $assignment, 'Bericht');

        $this->expectException(ValidationException::class);
        $this->service()->returnForRevision($submission, '  ', $this->grader());
    }

    public function test_vier_augen_verlangt_eine_zweite_person(): void {
        [$enrollment, $assignment] = $this->scenario(['requires_second_opinion' => true]);
        $submission = $this->service()->submit($enrollment, $assignment, 'Bericht');
        $first = $this->grader();

        $submission = $this->service()->grade($submission, [], 9, null, $first);
        $this->assertTrue($submission->passed);
        $this->assertNotSame(
            LearningEnrollmentStatus::Completed,
            $enrollment->refresh()->status,
            'Ohne Zweitbewertung ist die Bewertung nicht endgültig.'
        );

        try {
            $this->service()->secondOpinion($submission, $first);
            $this->fail('Die erstbewertende Person darf nicht selbst bestätigen.');
        } catch (ValidationException) {
            // erwartet
        }

        $submission = $this->service()->secondOpinion($submission->refresh(), $this->grader());
        $this->assertNotNull($submission->second_opinion_at);
        $this->assertSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_notenbuch_summiert_aufgaben_und_zeigt_offene_bewertung(): void {
        [$enrollment, $assignment] = $this->scenario();
        $submission = $this->service()->submit($enrollment, $assignment, 'Bericht');

        $book = app(LearningGradebookService::class)->forEnrollment($enrollment);
        $this->assertTrue($book['pending'], 'Solange die Bewertung offen ist, gibt es keine Note.');
        $this->assertNull($book['grade']);

        $this->service()->grade($submission, [], 9, null, $this->grader());

        $book = app(LearningGradebookService::class)->forEnrollment($enrollment->refresh());
        $this->assertFalse($book['pending']);
        $this->assertSame(9, $book['points']);
        $this->assertSame(10, $book['max']);
        $this->assertSame(90, $book['percent']);
    }

    public function test_notenschema_der_organisation_liefert_die_note(): void {
        $this->organization->update(['settings' => ['learning' => ['grade_scale' => [
            ['min_percent' => 90, 'label' => 'sehr gut'],
            ['min_percent' => 70, 'label' => 'gut'],
            ['min_percent' => 0, 'label' => 'nicht bestanden'],
        ]]]]);
        [$enrollment, $assignment] = $this->scenario();
        $submission = $this->service()->submit($enrollment, $assignment, 'Bericht');
        $this->service()->grade($submission, [], 8, null, $this->grader());

        $book = app(LearningGradebookService::class)->forEnrollment($enrollment->refresh());

        $this->assertSame('gut', $book['grade']);
    }

    public function test_cockpit_braucht_das_bewertungsrecht(): void {
        [$enrollment, $assignment] = $this->scenario();
        $this->service()->submit($enrollment, $assignment, 'Bericht');

        $this->actingAs($this->grader())
            ->get(route('learning.grading.index'))
            ->assertOk()
            ->assertSee($assignment->title);

        $this->actingAs(User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]))
            ->get(route('learning.grading.index'))
            ->assertForbidden();
    }

    // ── Datei-Abgaben (MVP-739) ─────────────────────────────────────────

    public function test_dateipflicht_verhindert_eine_leere_abgabe(): void {
        [$enrollment, $assignment] = $this->scenario(['submission_kind' => 'file']);

        // Ohne Anhang landete die Abgabe sonst als „zu bewerten" im Cockpit
        // und die bewertende Person sucht die Datei.
        $this->expectException(ValidationException::class);
        $this->service()->submit($enrollment, $assignment, null);
    }

    public function test_datei_wird_hochgeladen_und_ist_wieder_abrufbar(): void {
        Storage::fake('local');
        [$enrollment, $assignment, $user] = $this->scenario(['submission_kind' => 'file']);

        $this->actingAs($user)
            ->post(route('learning.my.assignments.submit', [$enrollment->sqid, $assignment->sqid]), [
                'files' => [UploadedFile::fake()->create('bericht.pdf', 40, 'application/pdf')],
            ])
            ->assertRedirect();

        $submission = LearningSubmission::query()->where('learning_enrollment_id', $enrollment->id)->firstOrFail();
        $attachment = $submission->attachments()->firstOrFail();

        $this->assertSame(LearningSubmissionStatus::Submitted, $submission->refresh()->status);
        $this->assertSame('bericht.pdf', $attachment->original_name);

        $this->actingAs($user)
            ->get(route('learning.my.submissions.file', [$enrollment->sqid, $submission->sqid, $attachment->sqid]))
            ->assertOk();
    }

    public function test_bewertende_person_kann_die_datei_oeffnen(): void {
        Storage::fake('local');
        [$enrollment, $assignment, $user] = $this->scenario(['submission_kind' => 'file']);

        $this->actingAs($user)
            ->post(route('learning.my.assignments.submit', [$enrollment->sqid, $assignment->sqid]), [
                'files' => [UploadedFile::fake()->create('bericht.pdf', 40, 'application/pdf')],
            ])
            ->assertRedirect();

        $submission = LearningSubmission::query()->where('learning_enrollment_id', $enrollment->id)->firstOrFail();
        $attachment = $submission->attachments()->firstOrFail();

        $this->actingAs($this->grader())
            ->get(route('learning.grading.submission.file', [$submission->sqid, $attachment->sqid]))
            ->assertOk();
    }

    public function test_fremder_anhang_ist_ueber_die_abgabe_nicht_erreichbar(): void {
        Storage::fake('local');
        [$enrollmentA, $assignmentA, $userA] = $this->scenario(['submission_kind' => 'file']);
        [$enrollmentB, $assignmentB, $userB] = $this->scenario(['submission_kind' => 'file']);

        foreach ([[$userA, $enrollmentA, $assignmentA], [$userB, $enrollmentB, $assignmentB]] as [$user, $enrollment, $assignment]) {
            $this->actingAs($user)
                ->post(route('learning.my.assignments.submit', [$enrollment->sqid, $assignment->sqid]), [
                    'files' => [UploadedFile::fake()->create('bericht.pdf', 40, 'application/pdf')],
                ])
                ->assertRedirect();
        }

        $submissionA = LearningSubmission::query()->where('learning_enrollment_id', $enrollmentA->id)->firstOrFail();
        $attachmentB = LearningSubmission::query()->where('learning_enrollment_id', $enrollmentB->id)
            ->firstOrFail()->attachments()->firstOrFail();

        // Sonst wäre die Route ein Leseschlüssel auf jede Datei der Anwendung.
        $this->actingAs($userA)
            ->get(route('learning.my.submissions.file', [$enrollmentA->sqid, $submissionA->sqid, $attachmentB->sqid]))
            ->assertNotFound();
    }
}
