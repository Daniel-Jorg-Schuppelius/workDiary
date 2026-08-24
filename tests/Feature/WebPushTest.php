<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebPushTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Timesheet\TimesheetStatus;
use App\Jobs\Notification\WebPushDeliveryJob;
use App\Models\{Comment, DiaryEntry, EmergencyAssignment, Project, PushSubscription, Timesheet, User};
use App\Notifications\GenericEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Config, Notification};
use Tests\TestCase;

class WebPushTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Config::set('webpush.public_key', 'BO-uHxBTpw50e_ZPfZDwBYFhNhEP38RiyWF7ppyCrrSoC6sYSO8ILUZ6_MX1c-iSgOJrTbcuhvMoA_fFnsZSnx0');
        Config::set('webpush.private_key', 'GCpBG_ivebc2Sm61xQtqkwBOWhzQrvJmBnaSoXE7PMs');
    }

    public function test_vapid_endpoint_returns_public_key(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user)->getJson(route('push.vapid'))
            ->assertOk()
            ->assertJsonStructure(['publicKey']);
    }

    public function test_subscribe_persists_subscription(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user)->postJson(route('push.subscribe'), [
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'pkey', 'auth' => 'akey'],
            'contentEncoding' => 'aesgcm',
        ])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc',
        ]);
    }

    public function test_unsubscribe_removes_only_own_subscription(): void {
        $user = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/x',
            'p256dh' => 'p',
            'auth' => 'a',
        ]);
        PushSubscription::create([
            'user_id' => $other->id,
            'endpoint' => 'https://push.example.com/y',
            'p256dh' => 'p',
            'auth' => 'a',
        ]);

        $this->actingAs($user)->deleteJson(route('push.unsubscribe'), [
            'endpoint' => 'https://push.example.com/x',
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example.com/x']);
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example.com/y']);
    }

    // Ab hier: Push-Zustellung über den zentralen NotificationDispatcher
    // (Vollscan 2026-08-23, B7 — Nachfolger der Legacy-PushNotifier-Tests).
    // Zustellung läuft asynchron über WebPushDeliveryJob je Empfänger.

    public function test_new_comment_dispatches_push_job_to_entry_owner(): void {
        $owner = User::factory()->user()->create();
        $commenter = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $entry = DiaryEntry::factory()->for($owner)->create();
        PushSubscription::create(['user_id' => $owner->id, 'endpoint' => 'https://push.example.com/o', 'p256dh' => 'p', 'auth' => 'a']);

        Notification::fake();
        Bus::fake([WebPushDeliveryJob::class]);
        $this->actingAs($commenter);
        Comment::factory()->for($commenter)->create(['commentable_type' => DiaryEntry::class, 'commentable_id' => $entry->id, 'body' => 'Hi']);

        Bus::assertDispatched(WebPushDeliveryJob::class, fn(WebPushDeliveryJob $job): bool => $job->userId === (int) $owner->id
            && $job->payload['title'] !== '');
    }

    public function test_own_comment_does_not_push(): void {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();
        PushSubscription::create(['user_id' => $owner->id, 'endpoint' => 'https://push.example.com/o', 'p256dh' => 'p', 'auth' => 'a']);

        Notification::fake();
        Bus::fake([WebPushDeliveryJob::class]);
        $this->actingAs($owner);
        Comment::factory()->for($owner)->create(['commentable_type' => DiaryEntry::class, 'commentable_id' => $entry->id, 'body' => 'self']);

        Bus::assertNotDispatched(WebPushDeliveryJob::class);
    }

    public function test_emergency_assignment_notifies_assignee(): void {
        $user = User::factory()->user()->create();

        Notification::fake();
        EmergencyAssignment::factory()->for($user)->create();

        Notification::assertSentTo(
            $user,
            GenericEventNotification::class,
            fn(GenericEventNotification $n): bool => $n->event === NotificationEvent::EmergencyAssigned,
        );
    }

    public function test_problem_diary_entry_notifies_admins(): void {
        // Benachrichtigung geht an die Admin-/Callcenter-Rolle derselben
        // Organisation; der meldende Besitzer selbst wird nicht adressiert.
        $author = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $author->organization_id]);

        Notification::fake();
        $this->actingAs($author);
        DiaryEntry::factory()->for($author)->create(['status' => 3, 'content' => 'Notlage']);

        Notification::assertSentTo(
            $admin,
            GenericEventNotification::class,
            fn(GenericEventNotification $n): bool => $n->event === NotificationEvent::DiaryProblem,
        );
        Notification::assertNotSentTo($author, GenericEventNotification::class);
    }

    public function test_timesheet_signed_notifies_owner(): void {
        $owner = User::factory()->user()->create();
        $project = Project::factory()->create(['organization_id' => $owner->organization_id]);
        $timesheet = Timesheet::create([
            'organization_id' => $owner->organization_id,
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'work_date' => now()->toDateString(),
            'status' => TimesheetStatus::Draft->value,
        ]);

        Notification::fake();
        $timesheet->update(['status' => TimesheetStatus::Signed->value]);

        Notification::assertSentTo(
            $owner,
            GenericEventNotification::class,
            fn(GenericEventNotification $n): bool => $n->event === NotificationEvent::TimesheetSigned,
        );
    }
}
