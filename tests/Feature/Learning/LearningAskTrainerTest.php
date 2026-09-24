<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAskTrainerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Notification\NotificationEvent;
use App\Enums\ServiceTicket\ServiceTicketKind;
use App\Mail\LearningTrainerQuestionMail;
use App\Models\Learning\LearningEnrollment;
use App\Models\Notification\NotificationRule;
use App\Models\Platform\{LicenseFlagOverride, User};
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningQuestionService};
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Frage an den Trainer (Feature 149, MVP-789): mit Helpdesk entsteht ein
 * Ticket der Art „Frage" mit Bezug zur Einschreibung, ohne Helpdesk geht
 * eine Mail an die verantwortliche Person; in beiden Fällen werden
 * Verantwortliche und Trainer benachrichtigt.
 */
class LearningAskTrainerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    private User $trainer;

    private User $learner;

    private LearningEnrollment $enrollment;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        NotificationRule::factory()->forEvent(NotificationEvent::LearningQuestionAsked)->create([
            'organization_id' => $this->organization->id,
            'notify_affected' => true,
            'recipient_roles' => [],
        ]);

        $this->owner = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id, 'email' => 'trainerin@example.test']);
        $this->trainer = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id, 'name' => 'Lisa Lernend']);

        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, $this->owner, ['title' => 'Brandschutz']);
        $courses->addUnit($course, ['title' => 'Grundlagen']);
        $course->trainers()->attach($this->trainer->id, ['organization_id' => $this->organization->id, 'role' => 'trainer']);
        $courses->release($course->refresh(), null);
        $this->enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $this->learner);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    public function test_mit_helpdesk_entsteht_ein_ticket_und_beide_werden_benachrichtigt(): void {
        $this->assertTrue(app(FeatureFlagResolver::class)->isEnabled('module.helpdesk'));

        $this->actingAs($this->learner)
            ->get(route('learning.my.ask.create', $this->enrollment))
            ->assertOk()
            ->assertSee(__('learning.help.ask_via_ticket'));

        $this->actingAs($this->learner)
            ->post(route('learning.my.ask.store', $this->enrollment), ['question' => 'Gilt die Unterweisung auch für Zeitarbeitskräfte?'])
            ->assertRedirect(route('learning.my.show', $this->enrollment));

        $ticket = ServiceTicket::query()->firstOrFail();
        $this->assertSame(ServiceTicketKind::Question, $ticket->kind);
        $this->assertSame('learning:' . $this->enrollment->sqid, $ticket->source_reference);
        $this->assertSame($this->owner->id, (int) $ticket->assigned_to_user_id);
        $this->assertSame($this->learner->id, (int) $ticket->reported_by_user_id);
        $this->assertStringContainsString('Zeitarbeitskräfte', (string) $ticket->description);

        $events = fn (User $user): array => $user->notifications()->get()->map(fn ($n): string => (string) (((array) $n->data)['event'] ?? ''))->all();
        $this->assertSame([NotificationEvent::LearningQuestionAsked->value], $events($this->owner));
        $this->assertSame([NotificationEvent::LearningQuestionAsked->value], $events($this->trainer));
        // Die Zuweisung selbst hat die lernende Person benachrichtigt — die Frage nicht.
        $this->assertNotContains(NotificationEvent::LearningQuestionAsked->value, $events($this->learner));
    }

    public function test_ohne_helpdesk_geht_eine_mail_an_die_verantwortliche_person(): void {
        Mail::fake();
        LicenseFlagOverride::query()->create(['organization_id' => $this->organization->id, 'flag' => 'module.helpdesk', 'disabled_at' => now()]);
        app(FeatureFlagResolver::class)->flush();

        $result = app(LearningQuestionService::class)->ask($this->enrollment, $this->learner, 'Wo finde ich die Sammelstelle?');

        $this->assertNull($result['ticket']);
        $this->assertSame(0, ServiceTicket::query()->count());
        Mail::assertQueued(LearningTrainerQuestionMail::class, function (LearningTrainerQuestionMail $mail): bool {
            return $mail->hasTo('trainerin@example.test')
                && $mail->hasReplyTo($this->learner->email)
                && str_contains($mail->question, 'Sammelstelle');
        });
    }

    public function test_zu_kurze_frage_wird_abgelehnt(): void {
        $this->actingAs($this->learner)
            ->post(route('learning.my.ask.store', $this->enrollment), ['question' => 'Hm?'])
            ->assertSessionHasErrors('question');
        $this->assertSame(0, ServiceTicket::query()->count());
    }
}
