<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatWebhookDeliveryJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Notification;

use App\Models\ChatWebhook;
use App\Services\Notification\ChatMessageFormatter;
use App\Support\UrlSafety;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Stellt eine Benachrichtigung an einen Team-Messenger-Kanal zu (Feature 056,
 * MVP-119): Teams (MessageCard) bzw. Mattermost/Rocket.Chat (`{text}`). POST mit
 * SSRF-Guard und Timeout; Retry/Backoff über die Queue, Auto-Deaktivierung nach
 * zu vielen aufeinanderfolgenden Fehlern.
 */
class ChatWebhookDeliveryJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array{title: string, message?: string|null, url?: string|null}  $payload
     */
    public function __construct(
        public readonly int $chatWebhookId,
        public readonly string $eventLabel,
        public readonly array $payload,
    ) {}

    public function handle(ChatMessageFormatter $formatter): void {
        $webhook = ChatWebhook::query()->withoutGlobalScopes()->find($this->chatWebhookId);
        if (! $webhook instanceof ChatWebhook || ! $webhook->isActive()) {
            return;
        }

        $url = $webhook->webhook_url;
        if (! UrlSafety::isPubliclyRoutableHttpUrl($url)) {
            $this->registerFailure($webhook); // terminal, kein Retry
            return;
        }

        $body = $formatter->format($webhook->kind, $this->eventLabel, $this->payload);
        $client = app(\App\Plugins\Support\PluginHttpFactory::class)->coreClient('chat-webhook', $url);
        // Keine Redirects: ein 30x auf internen Host würde den SSRF-Guard umgehen (Whitebox 2026-07).
        $client->setFollowRedirects(false);
        $client->setTimeout(10.0);
        // Kein HTTP-Retry: die Queue ist die Retry-Ebene dieses Jobs.
        $client->setMaxRetries(1);
        $response = $client->postJson($url, $body); // Netzfehler → Exception → Retry

        if ($response->successful()) {
            $webhook->forceFill(['last_delivery_at' => Carbon::now(), 'consecutive_failures' => 0])->saveQuietly();

            return;
        }

        throw new RuntimeException('chat delivery failed: HTTP ' . $response->status());
    }

    /** Nach Aufbrauchen aller Versuche: den Fehlschlag zählen (Auto-Disable). */
    public function failed(?Throwable $e): void {
        $webhook = ChatWebhook::query()->withoutGlobalScopes()->find($this->chatWebhookId);
        if ($webhook instanceof ChatWebhook) {
            $this->registerFailure($webhook);
        }
    }

    private function registerFailure(ChatWebhook $webhook): void {
        $failures = $webhook->consecutive_failures + 1;
        $attributes = ['consecutive_failures' => $failures];
        if ($failures >= ChatWebhook::AUTO_DISABLE_THRESHOLD) {
            $attributes['active'] = false;
            $attributes['disabled_at'] = Carbon::now();
        }
        $webhook->forceFill($attributes)->saveQuietly();
    }
}
