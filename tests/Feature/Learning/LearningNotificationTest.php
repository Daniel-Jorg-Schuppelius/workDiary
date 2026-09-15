<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningNotificationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningEnrollmentSource, LearningTimePolicy, LearningUnitKind};
use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\Learning\{LearningAssignment, LearningCourse};
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\User;
use App\Services\Learning\{LearningAssignmentService, LearningBookingService, LearningCompletionService, LearningCourseService, LearningEnrollmentService, LearningTimeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Benachrichtigungen der Lernplattform (Feature 149, MVP-780): synchrone
 * Ereignisse aus den Diensten und der Fälligkeits-Scan — jede Meldung genau
 * einmal, Pflicht-Einschreibungen ohne zweite Post.
 */
class LearningNotificationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        // Determinismus: nur In-App, damit Mail-Rendering den Test nicht berührt.
        foreach (NotificationEvent::cases() as $event) {
            if (str_starts_with($event->value, 'learning.')) {
                NotificationRule::factory()->forEvent($event)->create([
                    'organization_id' => $this->organization->id,
                    'channels' => [NotificationChannel::InApp->value],
                    'notify_affected' => $event->defaultNotifyAffected(),
                    'recipient_roles' => $event->defaultRecipientRoles(),
                ]);
            }
        }
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function learner(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    private function releasedCourse(array $attributes = [], ?string $unitKind = null): LearningCourse {
        $service = app(LearningCourseService::class);
        $course = $service->createCourse($this->organization, null, ['title' => 'Brandschutz kompakt'] + $attributes);
        $service->addUnit($course, ['title' => 'Einführung', 'kind' => $unitKind ?? LearningUnitKind::Content->value]);
        $service->release($course->refresh(), null);

        return $course->refresh();
    }

    /** @return list<string> Ereignisse der In-App-Benachrichtigungen einer Person */
    private function eventsOf(User $user): array {
        return $user->notifications()->get()->map(fn ($n): string => (string) (((array) $n->data)['event'] ?? ''))->all();
    }

    public function test_zuweisung_meldet_sich_bei_der_person(): void {
        $course = $this->releasedCourse();
        $learner = $this->learner();

        app(LearningEnrollmentService::class)->enroll($course, $learner, ['due_at' => now()->addWeek()->toDateString()]);

        $this->assertSame([NotificationEvent::LearningEnrolled->value], $this->eventsOf($learner));
        $data = (array) $learner->notifications()->firstOrFail()->data;
        $this->assertSame('notification.message.learning_enrolled_with_due', $data['message_key'] ?? null);
    }

    public function test_pflicht_einschreibung_meldet_nichts_doppelt(): void {
        $course = $this->releasedCourse();
        $learner = $this->learner();

        app(LearningEnrollmentService::class)->enroll($course, $learner, ['source' => LearningEnrollmentSource::Requirement->value]);
        // Zweite Zuweisung derselben Person ist ein No-Op — auch für die Post.
        app(LearningEnrollmentService::class)->enroll($course, $learner, ['source' => LearningEnrollmentSource::Manual->value]);

        $this->assertSame([], $this->eventsOf($learner));
    }

    public function test_faelligkeitsscan_meldet_bald_faellig_und_ueberfaellig_je_einmal(): void {
        $course = $this->releasedCourse();
        $soon = $this->learner();
        $late = $this->learner();
        app(LearningEnrollmentService::class)->enroll($course, $soon, ['due_at' => now()->addDays(2)->toDateString(), 'source' => LearningEnrollmentSource::Requirement->value]);
        app(LearningEnrollmentService::class)->enroll($course, $late, ['due_at' => now()->subDay()->toDateString(), 'source' => LearningEnrollmentSource::Requirement->value]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame([NotificationEvent::LearningDueSoon->value], $this->eventsOf($soon));
        $this->assertSame([NotificationEvent::LearningOverdue->value], $this->eventsOf($late));
        $this->assertSame(2, NotificationDispatchLog::query()->withoutGlobalScopes()
            ->whereIn('event', [NotificationEvent::LearningDueSoon->value, NotificationEvent::LearningOverdue->value])
            ->count());
    }

    public function test_abgeschlossene_einschreibung_wird_nicht_mehr_gemahnt(): void {
        $course = $this->releasedCourse();
        $learner = $this->learner();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $learner, ['due_at' => now()->subDay()->toDateString(), 'source' => LearningEnrollmentSource::Requirement->value]);
        app(LearningEnrollmentService::class)->completeUnit($enrollment, $course->units()->firstOrFail());

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertNotContains(NotificationEvent::LearningOverdue->value, $this->eventsOf($learner));
    }

    public function test_abgabe_und_bewertung_melden_sich_gegenseitig(): void {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Erste Hilfe']);
        $courses->addUnit($course, ['title' => 'Praxisbericht', 'kind' => LearningUnitKind::Assignment->value]);
        $unit = $course->refresh()->units()->firstOrFail();
        $assignment = LearningAssignment::query()->create([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => 'Praxisbericht',
            'submission_kind' => 'text',
            'points' => 10,
            'pass_percent' => 50,
        ]);
        $courses->release($course->refresh(), null);

        $grader = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
        $learner = $this->learner();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $submission = app(LearningAssignmentService::class)->submit($enrollment, $assignment->refresh(), 'Mein Bericht');
        $this->assertContains(NotificationEvent::LearningSubmissionReceived->value, $this->eventsOf($grader));
        $this->assertNotContains(NotificationEvent::LearningSubmissionReceived->value, $this->eventsOf($learner));

        app(LearningAssignmentService::class)->grade($submission, [], 8, 'Gut', $grader);
        $this->assertContains(NotificationEvent::LearningGraded->value, $this->eventsOf($learner));
    }

    public function test_zertifikat_meldet_sich_bei_der_person(): void {
        $course = $this->releasedCourse(['certificate_enabled' => true]);
        $learner = $this->learner();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $learner);

        app(LearningCompletionService::class)->issueCertificate($enrollment);

        $this->assertContains(NotificationEvent::LearningCertificateIssued->value, $this->eventsOf($learner));
    }

    public function test_buchungsentscheidung_meldet_sich_bei_der_buchenden_person(): void {
        $course = $this->releasedCourse(['access_kind' => 'bookable']);
        $learner = $this->learner();
        $bookings = app(LearningBookingService::class);

        $bookings->confirm($bookings->request($course, $learner), null);

        $events = $this->eventsOf($learner);
        $this->assertContains(NotificationEvent::LearningBookingDecided->value, $events);
        $this->assertContains(NotificationEvent::LearningEnrolled->value, $events);
    }

    public function test_freigabepflichtige_lernzeit_meldet_sich_bei_der_teamleitung(): void {
        Carbon::setTestNow(Carbon::parse('2026-09-01 20:00:00'));
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $course = $this->releasedCourse(['time_policy' => LearningTimePolicy::ApprovalRequired->value]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $this->learner());
        $time = app(LearningTimeService::class);
        $session = $time->start($enrollment);

        Carbon::setTestNow(Carbon::parse('2026-09-01 21:00:00'));
        $time->heartbeat($session);
        $time->stop($session->refresh());

        $this->assertContains(NotificationEvent::LearningTimeApprovalRequested->value, $this->eventsOf($lead));
    }
}
