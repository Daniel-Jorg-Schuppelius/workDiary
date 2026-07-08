<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Notification;

use App\Enums\Notification\NotificationEvent;
use App\Jobs\Notification\ChatWebhookDeliveryJob;
use App\Models\{ChatWebhook, Customer};
use App\Models\Notification\NotificationRule;
use App\Services\Notification\{ChatMessageFormatter, NotificationDispatcher};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 056, MVP-119: Team-Messenger-Kanäle. Prüft die Payload-Formatierung
 * (Teams-MessageCard vs. Mattermost-{text}), die Auswahl über die
 * Ereignis→Kanal-Matrix (an/aus) und die Auto-Deaktivierung nach Fehlern.
 */
final class ChatWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function webhook(string $kind, string $url): ChatWebhook {
        return ChatWebhook::query()->create([
            'organization_id' => $this->organization->id,
            'name' => ucfirst($kind),
            'kind' => $kind,
            'webhook_url' => $url,
            'active' => true,
        ]);
    }

    /** @param array{title: string, message?: string|null, url?: string|null} $payload */
    private function runJob(ChatWebhook $webhook, array $payload): void {
        (new ChatWebhookDeliveryJob((int) $webhook->id, 'Ereignis', $payload))
            ->handle(app(ChatMessageFormatter::class));
    }

    public function test_teams_delivery_posts_adaptive_card(): void {
        Http::fake(['*' => Http::response('1', 200)]);
        $webhook = $this->webhook(ChatWebhook::KIND_TEAMS, 'https://hooks.teams.example/x');

        $this->runJob($webhook, ['title' => 'SLA verletzt', 'message' => 'Ticket #7', 'url' => 'https://app.example/t/7']);

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $content = $data['attachments'][0]['content'] ?? [];

            return $request->url() === 'https://hooks.teams.example/x'
                && ($data['type'] ?? null) === 'message'
                && ($data['attachments'][0]['contentType'] ?? null) === 'application/vnd.microsoft.card.adaptive'
                && ($content['type'] ?? null) === 'AdaptiveCard'
                && ($content['body'][0]['text'] ?? null) === 'SLA verletzt'
                && ($content['body'][2]['text'] ?? null) === 'Ticket #7'
                && ($content['actions'][0]['url'] ?? null) === 'https://app.example/t/7';
        });

        $webhook->refresh();
        $this->assertSame(0, $webhook->consecutive_failures);
        $this->assertNotNull($webhook->last_delivery_at);
    }

    public function test_mattermost_delivery_posts_text(): void {
        Http::fake(['*' => Http::response('ok', 200)]);
        $webhook = $this->webhook(ChatWebhook::KIND_MATTERMOST, 'https://hooks.mm.example/y');

        $this->runJob($webhook, ['title' => 'Neuer Auftrag', 'message' => 'von ACME', 'url' => 'https://app.example/o/1']);

        Http::assertSent(function ($request): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://hooks.mm.example/y'
                && str_contains($text, '**Neuer Auftrag**')
                && str_contains($text, 'von ACME')
                && str_contains($text, 'https://app.example/o/1');
        });
    }

    public function test_failed_delivery_throws_for_retry(): void {
        Http::fake(['*' => Http::response('boom', 500)]);
        $webhook = $this->webhook(ChatWebhook::KIND_MATTERMOST, 'https://hooks.mm.example/z');

        $this->expectException(RuntimeException::class);
        $this->runJob($webhook, ['title' => 'X']);
    }

    public function test_repeated_failures_auto_disable_channel(): void {
        $webhook = $this->webhook(ChatWebhook::KIND_TEAMS, 'https://hooks.teams.example/d');
        $webhook->forceFill(['consecutive_failures' => ChatWebhook::AUTO_DISABLE_THRESHOLD - 1])->save();

        (new ChatWebhookDeliveryJob((int) $webhook->id, 'Ereignis', ['title' => 'X']))->failed(new RuntimeException('down'));

        $webhook->refresh();
        $this->assertSame(ChatWebhook::AUTO_DISABLE_THRESHOLD, $webhook->consecutive_failures);
        $this->assertFalse($webhook->active);
        $this->assertNotNull($webhook->disabled_at);
    }

    public function test_dispatcher_posts_to_chat_channel_per_matrix(): void {
        Http::fake(['*' => Http::response('1', 200)]);
        $event = NotificationEvent::cases()[0];
        NotificationRule::query()->create([
            'organization_id' => $this->organization->id,
            'event' => $event,
            'enabled' => true,
            'channels' => ['inApp', 'teams'],
        ]);
        $this->webhook(ChatWebhook::KIND_TEAMS, 'https://hooks.teams.example/matrix');
        $subject = Customer::factory()->create(['organization_id' => $this->organization->id]);

        app(NotificationDispatcher::class)->notify($event, $subject, null, ['title' => 'Hallo']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.teams.example/matrix');
    }

    public function test_dispatcher_skips_channel_when_matrix_disabled(): void {
        Http::fake(['*' => Http::response('1', 200)]);
        $event = NotificationEvent::cases()[0];
        NotificationRule::query()->create([
            'organization_id' => $this->organization->id,
            'event' => $event,
            'enabled' => true,
            'channels' => ['inApp'], // Teams NICHT angehakt
        ]);
        $this->webhook(ChatWebhook::KIND_TEAMS, 'https://hooks.teams.example/off');
        $subject = Customer::factory()->create(['organization_id' => $this->organization->id]);

        app(NotificationDispatcher::class)->notify($event, $subject, null, ['title' => 'Hallo']);

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'hooks.teams.example'));
    }
}
