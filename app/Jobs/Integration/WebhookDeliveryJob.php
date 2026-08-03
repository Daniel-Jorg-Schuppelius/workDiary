<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookDeliveryJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Integration;

use App\Enums\Integration\WebhookDeliveryStatus;
use App\Models\Integration\{WebhookDelivery, WebhookEndpoint};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\{Carbon, Str};
use Throwable;

/**
 * Stellt eine einzelne Webhook-Nutzlast zu (Feature 008): HMAC-SHA256-Signatur
 * über `<timestamp>.<body>` (Replay-Schutz), POST mit kurzem Timeout, jeder
 * Versuch in {@see WebhookDelivery} protokolliert. Retry/Backoff über die Queue;
 * nach {@see WebhookEndpoint::MAX_CONSECUTIVE_FAILURES} Fehlern Auto-Deaktivierung.
 * Der Body wird vorab serialisiert übergeben, damit Signatur und payload_hash
 * über dieselben Bytes laufen.
 */
class WebhookDeliveryJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** HTTP-Header der Signatur und Begleit-Metadaten. */
    public const SIGNATURE_HEADER = 'X-WorkDiary-Signature';
    public const TIMESTAMP_HEADER = 'X-WorkDiary-Timestamp';
    public const EVENT_HEADER = 'X-WorkDiary-Event';
    public const DELIVERY_HEADER = 'X-WorkDiary-Delivery';

    /** Kurzer Timeout, damit ein hängender Empfänger die Queue nicht blockiert. */
    public int $timeout = 30;
    public int $tries = 4;

    /** @var list<int> Backoff in Sekunden je Wiederholung (exponentiell). */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $deliveryId,
        public readonly string $body,
        public readonly int $timestamp,
    ) {}

    public function handle(): void {
        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->find($this->deliveryId);
        if ($delivery === null) {
            return;
        }

        $endpoint = WebhookEndpoint::query()->withoutGlobalScopes()->find($delivery->webhook_endpoint_id);
        if ($endpoint === null || ! $endpoint->isDeliverable()) {
            // Endpunkt entfernt oder zwischenzeitlich deaktiviert → nicht zustellen.
            $delivery->status = WebhookDeliveryStatus::Failed;
            $delivery->response_excerpt = 'endpoint inactive';
            $delivery->completed_at = Carbon::now();
            $delivery->save();

            return;
        }

        $delivery->attempt = $this->attempts();
        $delivery->save();

        // SSRF-Laufzeit-Guard (DNS-Rebinding/Altbestand): nie an interne Ziele zustellen.
        if (! \App\Support\UrlSafety::isPubliclyRoutableHttpUrl((string) $endpoint->url)) {
            $this->markFailure($delivery, $endpoint, 'Blocked: non-public URL');

            return;
        }

        $signature = $this->sign($endpoint->secret);

        try {
            $client = app(\App\Plugins\Support\PluginHttpFactory::class)->coreClient('webhook', (string) $endpoint->url);
            // Keine Redirects: ein 30x auf internen Host würde den SSRF-Guard umgehen (Whitebox 2026-07).
            $client->setFollowRedirects(false);
            $client->setTimeout(10.0);
            $client->setUserAgent('WorkDiary-Webhook/1');
            // Kein HTTP-Retry: die Queue ist die Retry-Ebene dieses Jobs.
            $client->setMaxRetries(1);
            // Signatur über den Roh-Body — deshalb body statt json-Option.
            $response = $client->requestResponse('POST', (string) $endpoint->url, [
                'headers' => [
                    self::SIGNATURE_HEADER => 'sha256=' . $signature,
                    self::TIMESTAMP_HEADER => (string) $this->timestamp,
                    self::EVENT_HEADER => (string) $delivery->event,
                    self::DELIVERY_HEADER => (string) $delivery->id,
                    'Content-Type' => 'application/json',
                ],
                'body' => $this->body,
            ]);

            $status = $response->status();
            $delivery->http_status = $status;
            $delivery->response_excerpt = Str::limit((string) $response->body(), 480, '…');

            if ($response->successful()) {
                $this->markSuccess($delivery, $endpoint);

                return;
            }

            // 410 Gone: Subscription beim Empfänger gelöscht → Auto-Unsubscribe, kein Retry.
            if ($status === 410) {
                $this->autoUnsubscribe($delivery, $endpoint);

                return;
            }

            $this->markFailure($delivery, $endpoint, 'HTTP ' . $status);
        } catch (Throwable $e) {
            $delivery->response_excerpt = Str::limit($e->getMessage(), 480, '…');
            $this->markFailure($delivery, $endpoint, $e->getMessage());
        }
    }

    /** Queue-Netz nach Aufbrauchen aller Versuche: letzten Fehlschlag protokollieren/zählen. */
    public function failed(?Throwable $e): void {
        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->find($this->deliveryId);
        if ($delivery === null || $delivery->status === WebhookDeliveryStatus::Success) {
            return;
        }

        $endpoint = WebhookEndpoint::query()->withoutGlobalScopes()->find($delivery->webhook_endpoint_id);
        if ($delivery->status !== WebhookDeliveryStatus::Failed) {
            $delivery->status = WebhookDeliveryStatus::Failed;
            $delivery->completed_at = Carbon::now();
            if ($e !== null && $delivery->response_excerpt === null) {
                $delivery->response_excerpt = Str::limit($e->getMessage(), 480, '…');
            }
            $delivery->save();
        }

        if ($endpoint !== null) {
            $this->registerEndpointFailure($endpoint);
        }
    }

    /** HMAC-SHA256 über `<timestamp>.<body>` (Replay-Bindung). */
    private function sign(string $secret): string {
        return hash_hmac('sha256', $this->timestamp . '.' . $this->body, $secret);
    }

    private function markSuccess(WebhookDelivery $delivery, WebhookEndpoint $endpoint): void {
        $delivery->status = WebhookDeliveryStatus::Success;
        $delivery->completed_at = Carbon::now();
        $delivery->save();

        $endpoint->forceFill([
            'last_delivery_at' => Carbon::now(),
            'consecutive_failures' => 0,
        ])->saveQuietly();
    }

    /**
     * REST-Hooks-Selbstheilung (Feature 008): bei 410 Gone abbestellen (deaktivieren
     * + Soft-Delete), kein Retry. `active`/`disabled_at` wird gesetzt, weil der
     * Dispatch-Service `withoutGlobalScopes()` nutzt (Soft-Delete allein reicht nicht).
     */
    private function autoUnsubscribe(WebhookDelivery $delivery, WebhookEndpoint $endpoint): void {
        $delivery->status = WebhookDeliveryStatus::Failed;
        $delivery->completed_at = Carbon::now();
        $delivery->save();

        $endpoint->forceFill(['active' => false, 'disabled_at' => Carbon::now()])->saveQuietly();
        $endpoint->delete();
    }

    private function markFailure(WebhookDelivery $delivery, WebhookEndpoint $endpoint, string $reason): void {
        $delivery->status = WebhookDeliveryStatus::Failed;
        $delivery->completed_at = Carbon::now();
        $delivery->save();

        $endpoint->forceFill(['last_delivery_at' => Carbon::now()])->saveQuietly();

        // Nur beim endgültigen Fehlschlag zählen; Zwischen-Retries lassen den Zähler unberührt.
        if ($this->attempts() >= $this->tries) {
            $this->registerEndpointFailure($endpoint);
        }

        // Exception werfen, damit die Queue den Retry-Mechanismus auslöst.
        if ($this->attempts() < $this->tries) {
            throw new \RuntimeException('webhook delivery failed: ' . $reason);
        }
    }

    /** Erhöht den Fehlerzähler und deaktiviert den Endpunkt bei Erreichen der Schwelle. */
    private function registerEndpointFailure(WebhookEndpoint $endpoint): void {
        $endpoint->refresh();
        $failures = (int) $endpoint->consecutive_failures + 1;

        $attributes = ['consecutive_failures' => $failures];
        if ($failures >= WebhookEndpoint::MAX_CONSECUTIVE_FAILURES && $endpoint->disabled_at === null) {
            $attributes['disabled_at'] = Carbon::now();
            $attributes['active'] = false;
        }

        $endpoint->forceFill($attributes)->saveQuietly();
    }
}
