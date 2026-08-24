<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryNotificationsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Notification;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\{Attachment, Comment, DiaryEntry, User};
use App\Models\Notification\NotificationRule;
use App\Notifications\GenericEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Auftragsbuch-Benachrichtigungen über den zentralen Dispatcher (Vollscan
 * 2026-08-23, B7) — Nachfolger der Legacy-Tests für MailNotifier/PushNotifier.
 */
class DiaryNotificationsTest extends TestCase {
    use RefreshDatabase;

    public function test_comment_notifies_entry_owner_and_other_commenters_without_author(): void {
        $owner = User::factory()->user()->create();
        $first = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $second = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $entry = DiaryEntry::factory()->for($owner)->create();
        Comment::create(['commentable_type' => DiaryEntry::class, 'commentable_id' => $entry->id, 'user_id' => $first->id, 'body' => 'Erster']);

        Notification::fake();
        $this->actingAs($second);
        Comment::create(['commentable_type' => DiaryEntry::class, 'commentable_id' => $entry->id, 'user_id' => $second->id, 'body' => 'Zweiter']);

        $isCommentEvent = fn(GenericEventNotification $n): bool => $n->event === NotificationEvent::DiaryCommentCreated;
        Notification::assertSentTo($owner, GenericEventNotification::class, $isCommentEvent);
        Notification::assertSentTo($first, GenericEventNotification::class, $isCommentEvent);
        Notification::assertNotSentTo($second, GenericEventNotification::class);
    }

    public function test_own_comment_sends_nothing(): void {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();

        Notification::fake();
        $this->actingAs($owner);
        Comment::create(['commentable_type' => DiaryEntry::class, 'commentable_id' => $entry->id, 'user_id' => $owner->id, 'body' => 'selbst']);

        Notification::assertNothingSent();
    }

    public function test_status_change_to_problem_notifies_owner_and_admins(): void {
        $owner = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $owner->organization_id]);
        $entry = DiaryEntry::factory()->for($owner)->create(['status' => 2]);

        Notification::fake();
        $this->actingAs($admin);
        $entry->update(['status' => 3]);

        $isProblem = fn(GenericEventNotification $n): bool => $n->event === NotificationEvent::DiaryProblem;
        Notification::assertSentTo($owner, GenericEventNotification::class, $isProblem);
        // Wie im Legacy-Push: die Admin-Rolle wird benachrichtigt — auch der Auslöser.
        Notification::assertSentTo($admin, GenericEventNotification::class, $isProblem);
    }

    public function test_status_change_to_open_sends_nothing(): void {
        $owner = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $owner->organization_id]);
        $entry = DiaryEntry::factory()->for($owner)->create(['status' => 3]);

        Notification::fake();
        $this->actingAs($admin);
        $entry->update(['status' => 2]);

        Notification::assertNothingSent();
    }

    public function test_completion_notifies_owner_in_app_only(): void {
        $owner = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $owner->organization_id]);
        $entry = DiaryEntry::factory()->for($owner)->create(['status' => 2]);

        Notification::fake();
        $this->actingAs($admin);
        $entry->update(['status' => -1]);

        Notification::assertSentTo(
            $owner,
            GenericEventNotification::class,
            fn(GenericEventNotification $n): bool => $n->event === NotificationEvent::DiaryCompleted
                && $n->channels === ['database'],
        );
    }

    public function test_mail_channel_can_be_enabled_per_rule(): void {
        $owner = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $owner->organization_id]);
        NotificationRule::factory()->forEvent(NotificationEvent::DiaryCompleted)->create([
            'organization_id' => $owner->organization_id,
            'channels' => [NotificationChannel::InApp->value, NotificationChannel::Mail->value],
        ]);
        $entry = DiaryEntry::factory()->for($owner)->create(['status' => 2]);

        Notification::fake();
        $this->actingAs($admin);
        $entry->update(['status' => -1]);

        Notification::assertSentTo(
            $owner,
            GenericEventNotification::class,
            fn(GenericEventNotification $n): bool => $n->channels === ['database', 'mail'],
        );
    }

    public function test_disabled_rule_suppresses_comment_notifications(): void {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        NotificationRule::factory()->forEvent(NotificationEvent::DiaryCommentCreated)->create([
            'organization_id' => $owner->organization_id,
            'enabled' => false,
        ]);
        $entry = DiaryEntry::factory()->for($owner)->create();

        Notification::fake();
        $this->actingAs($other);
        Comment::create(['commentable_type' => DiaryEntry::class, 'commentable_id' => $entry->id, 'user_id' => $other->id, 'body' => 'Hi']);

        Notification::assertNothingSent();
    }

    public function test_foreign_attachment_notifies_entry_owner(): void {
        $owner = User::factory()->user()->create();
        $uploader = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $entry = DiaryEntry::factory()->for($owner)->create();

        Notification::fake();
        $this->actingAs($uploader);
        Attachment::factory()->for($uploader, 'uploader')->create([
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $entry->id,
        ]);

        Notification::assertSentTo(
            $owner,
            GenericEventNotification::class,
            fn(GenericEventNotification $n): bool => $n->event === NotificationEvent::DiaryAttachmentAdded,
        );
        Notification::assertNotSentTo($uploader, GenericEventNotification::class);
    }

    public function test_own_attachment_sends_nothing(): void {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();

        Notification::fake();
        $this->actingAs($owner);
        Attachment::factory()->for($owner, 'uploader')->create([
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $entry->id,
        ]);

        Notification::assertNothingSent();
    }
}
