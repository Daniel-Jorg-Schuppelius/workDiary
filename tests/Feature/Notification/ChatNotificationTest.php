<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatNotificationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Notification;

use App\Jobs\Notification\WebPushDeliveryJob;
use App\Models\Chat\{Channel, Message, Reminder};
use App\Models\{PushSubscription, User};
use App\Services\Chat\ChatNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Notification};
use Tests\TestCase;

/**
 * Chat-Benachrichtigungen über den zentralen Dispatcher (Vollscan 2026-08-23,
 * B7): Direktnachricht/@-Erwähnung/muted wie im Legacy-PushNotifier, Zustellung
 * als WebPushDeliveryJob (Default-Kanal push, bewusst ohne In-App-Duplikat).
 */
class ChatNotificationTest extends TestCase {
    use RefreshDatabase;

    private function subscribe(User $user): void {
        PushSubscription::create(['user_id' => $user->id, 'endpoint' => 'https://push.example.com/' . $user->id, 'p256dh' => 'p', 'auth' => 'a']);
    }

    /** @param list<User> $members */
    private function channel(string $type, array $members): Channel {
        $creator = $members[0];
        $channel = Channel::create([
            'organization_id' => $creator->organization_id,
            'name' => $type === 'direct' ? null : 'Team',
            'slug' => 'c-' . fake()->unique()->numberBetween(1, 100000),
            'type' => $type,
            'visibility' => 'private',
            'created_by' => $creator->id,
        ]);
        foreach ($members as $member) {
            $channel->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        }

        return $channel;
    }

    private function message(Channel $channel, User $sender, string $body): Message {
        /** @var Message */
        return $channel->messages()->create([
            'organization_id' => $channel->organization_id,
            'user_id' => $sender->id,
            'body' => $body,
            'type' => 'text',
        ]);
    }

    public function test_direct_message_pushes_to_other_member_only(): void {
        $sender = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $sender->organization_id]);
        $this->subscribe($sender);
        $this->subscribe($other);
        $channel = $this->channel('direct', [$sender, $other]);
        $message = $this->message($channel, $sender, 'Hallo');

        Notification::fake();
        Bus::fake([WebPushDeliveryJob::class]);
        app(ChatNotificationService::class)->messageCreated($message);

        Bus::assertDispatched(WebPushDeliveryJob::class, fn(WebPushDeliveryJob $job): bool => $job->userId === (int) $other->id);
        Bus::assertNotDispatched(WebPushDeliveryJob::class, fn(WebPushDeliveryJob $job): bool => $job->userId === (int) $sender->id);
        // Default-Kanal ist nur push — kein In-App-/Mail-Duplikat zum Chat.
        Notification::assertNothingSent();
    }

    public function test_group_message_pushes_only_mentioned_members(): void {
        $sender = User::factory()->user()->create();
        $mentioned = User::factory()->user()->create(['organization_id' => $sender->organization_id]);
        $bystander = User::factory()->user()->create(['organization_id' => $sender->organization_id]);
        $this->subscribe($mentioned);
        $this->subscribe($bystander);
        $channel = $this->channel('channel', [$sender, $mentioned, $bystander]);
        $message = $this->message($channel, $sender, 'Bitte @' . $mentioned->name . ' übernehmen');

        Bus::fake([WebPushDeliveryJob::class]);
        app(ChatNotificationService::class)->messageCreated($message);

        Bus::assertDispatched(WebPushDeliveryJob::class, fn(WebPushDeliveryJob $job): bool => $job->userId === (int) $mentioned->id);
        Bus::assertNotDispatched(WebPushDeliveryJob::class, fn(WebPushDeliveryJob $job): bool => $job->userId === (int) $bystander->id);
    }

    public function test_muted_member_is_never_notified(): void {
        $sender = User::factory()->user()->create();
        $muted = User::factory()->user()->create(['organization_id' => $sender->organization_id]);
        $this->subscribe($muted);
        $channel = $this->channel('direct', [$sender, $muted]);
        $channel->members()->updateExistingPivot($muted->id, ['muted_at' => now()]);
        $message = $this->message($channel, $sender, 'Hallo');

        Bus::fake([WebPushDeliveryJob::class]);
        app(ChatNotificationService::class)->messageCreated($message);

        Bus::assertNotDispatched(WebPushDeliveryJob::class);
    }

    public function test_due_reminder_is_sent_and_marked(): void {
        $user = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $user->organization_id]);
        $this->subscribe($user);
        $channel = $this->channel('channel', [$user, $other]);
        $message = $this->message($channel, $other, 'Wichtig');
        $reminder = Reminder::create([
            'user_id' => $user->id,
            'message_id' => $message->id,
            'channel_id' => $channel->id,
            'remind_at' => now()->subMinute(),
        ]);

        Bus::fake([WebPushDeliveryJob::class]);
        $this->artisan('chat:send-reminders')->assertExitCode(0);

        Bus::assertDispatched(WebPushDeliveryJob::class, fn(WebPushDeliveryJob $job): bool => $job->userId === (int) $user->id);
        $this->assertNotNull($reminder->fresh()?->sent_at);
    }
}
