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
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Stellt eine Benachrichtigung an einen Team-Messenger-Kanal zu (Feature 056,
 * MVP-119): Microsoft Teams (MessageCard) bzw. Mattermost/Rocket.Chat (`{text}`).
 * HTTP-POST über die `Http`-Fassade mit SSRF-Guard und Timeout; Wiederholung mit
 * Backoff über die Queue, Auto-Deaktivierung des Kanals nach zu vielen
 * aufeinanderfolgenden Fehlern (analog `webhook_endpoints`).
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
        // Keine Redirects folgen: sonst könnte ein zunächst öffentliches Ziel
        // per 30x auf einen internen Host umleiten und den SSRF-Guard oben
        // umgehen (Whitebox-Befund 2026-07).
        $response = Http::timeout(10)->withoutRedirecting()->asJson()->post($url, $body); // Netzfehler → Exception → Retry

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
