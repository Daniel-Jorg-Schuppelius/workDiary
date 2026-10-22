<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningExternalReminderAndVersionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningBlockKind, LearningEnrollmentStatus};
use App\Mail\LearningAccessLinkMail;
use App\Models\Communication\ExternalParticipant;
use App\Models\Learning\{LearningCourse, LearningEnrollmentEvent};
use App\Models\Platform\User;
use App\Services\Learning\{LearningContentService, LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Fristerinnerung für Externe (MVP-1270) und Versionswechsel laufender Einschreibungen (MVP-1272). */
final class LearningExternalReminderAndVersionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function course(): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Sicherheitsunterweisung']);
        $courses->addUnit($course, ['title' => 'Grundlagen']);
        $courses->addUnit($course, ['title' => 'Gefahrstoffe']);
        $courses->release($course->refresh(), null);

        return $course->refresh();
    }

    public function test_externals_get_one_reminder_ahead_and_one_the_day_before(): void {
        Mail::fake();
        $course = $this->course();
        $enrollments = app(LearningEnrollmentService::class);
        $ahead = $enrollments->enroll($course, ExternalParticipant::factory()->create(['organization_id' => $this->organization->id, 'email' => 'ahead@example.test']), ['due_at' => now()->addDays(2)->toDateString()]);
        $tomorrow = $enrollments->enroll($course, ExternalParticipant::factory()->create(['organization_id' => $this->organization->id, 'email' => 'tomorrow@example.test']), ['due_at' => now()->addDay()->toDateString()]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        Mail::assertQueued(LearningAccessLinkMail::class, 2);
        Mail::assertQueued(LearningAccessLinkMail::class, fn (LearningAccessLinkMail $mail): bool => $mail->hasTo('ahead@example.test') && ! $mail->final && $mail->dueAt !== null);
        Mail::assertQueued(LearningAccessLinkMail::class, fn (LearningAccessLinkMail $mail): bool => $mail->hasTo('tomorrow@example.test') && $mail->final);
        $this->assertNotNull($ahead->fresh()->reminded_at);
        $this->assertNull($ahead->fresh()->final_reminded_at);
        $this->assertNotNull($tomorrow->fresh()->final_reminded_at);

        // Eine verschobene Frist bekommt ihre eigenen Erinnerungen.
        $enrollments->extendAccess($tomorrow->fresh(), now()->addDays(20)->toDateString(), null, null, 'Verlängert');
        $this->assertNull($tomorrow->fresh()->reminded_at);
    }

    public function test_open_enrollments_move_to_the_current_version_with_preview_and_reason(): void {
        $course = $this->course();
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $done = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollments = app(LearningEnrollmentService::class);
        $open = $enrollments->enroll($course, $learner);
        $closed = $enrollments->enroll($course, $done);
        [$basics, $hazards] = $course->units()->orderBy('position')->get()->all();
        $enrollments->completeUnit($open, $basics);
        $enrollments->completeUnit($closed, $basics);
        $enrollments->completeUnit($closed, $hazards);
        $v1 = (int) $open->fresh()->learning_course_version_id;

        $courses = app(LearningCourseService::class);
        $courses->reopen($course);
        app(LearningContentService::class)->appendBlock($hazards->fresh(), LearningBlockKind::Callout, ['text' => 'Neue Kennzeichnung nach GHS', 'tone' => 'warning']);
        $courses->addUnit($course->refresh(), ['title' => 'Notfallplan']);
        $v2 = $courses->release($course->refresh(), null);
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->get(route('learning.courses.enrollments.index', $course))
            ->assertSee(__('learning.action.move_to_version', ['version' => $v2->version]));
        $this->actingAs($admin)->get(route('learning.courses.enrollments.version', $course))
            ->assertOk()
            ->assertSee(__('learning.version.added', ['count' => 1, 'units' => 'Notfallplan']))
            ->assertSee(__('learning.version.changed', ['count' => 1, 'units' => 'Gefahrstoffe']));

        $this->actingAs($admin)->post(route('learning.courses.enrollments.version.update', $course), ['reason' => 'Neue GHS-Kennzeichnung'])
            ->assertSessionHas('success');

        $this->assertSame((int) $v2->id, (int) $open->fresh()->learning_course_version_id);
        $this->assertSame($v1, (int) $closed->fresh()->learning_course_version_id);
        $this->assertSame(LearningEnrollmentStatus::InProgress, $open->fresh()->status);
        $event = LearningEnrollmentEvent::query()->where('learning_enrollment_id', $open->id)->where('event', 'version_changed')->sole();
        $this->assertSame('Neue GHS-Kennzeichnung', $event->reason);
        $this->assertSame(['from_version' => 1, 'to_version' => 2, 'kept' => 1, 'changed' => 1, 'added' => 1, 'removed' => 0], $event->payload);
        $this->assertSame(1, $open->fresh()->progress()->where('status', 'completed')->count());
    }
}
