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
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Stellt eine einzelne Webhook-Nutzlast zu (Feature 008).
 *
 * Signiert den Body mit HMAC-SHA256 über `<timestamp>.<body>` (Replay-Schutz)
 * unter dem endpoint.secret und sendet ihn per POST mit kurzem Timeout. Jeder
 * Versuch wird in {@see WebhookDelivery} protokolliert. Retry mit Backoff über
 * die Laravel-Queue ($tries/$backoff). Nach
 * {@see WebhookEndpoint::MAX_CONSECUTIVE_FAILURES} aufeinanderfolgenden
 * Fehlversuchen wird der Endpunkt automatisch deaktiviert (disabled_at).
 *
 * Der Body wird bewusst vorab serialisiert übergeben (nicht das Payload-Array),
 * damit Signatur und payload_hash exakt über dieselben Bytes laufen.
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

        // SSRF-Laufzeit-Guard (auch gegen DNS-Rebinding / Altbestand-Endpunkte):
        // niemals an interne/private/reservierte Ziele zustellen.
        if (! \App\Support\UrlSafety::isPubliclyRoutableHttpUrl((string) $endpoint->url)) {
            $this->markFailure($delivery, $endpoint, 'Blocked: non-public URL');

            return;
        }

        $signature = $this->sign($endpoint->secret);

        try {
            $response = Http::withHeaders([
                self::SIGNATURE_HEADER => 'sha256=' . $signature,
                self::TIMESTAMP_HEADER => (string) $this->timestamp,
                self::EVENT_HEADER => (string) $delivery->event,
                self::DELIVERY_HEADER => (string) $delivery->id,
                'Content-Type' => 'application/json',
                'User-Agent' => 'WorkDiary-Webhook/1',
            ])
                ->timeout(10)
                // Keine Redirects folgen: ein 30x auf einen internen Host würde
                // sonst den SSRF-Guard oben umgehen (Whitebox-Befund 2026-07).
                ->withoutRedirecting()
                ->withBody($this->body, 'application/json')
                ->post($endpoint->url);

            $status = $response->status();
            $delivery->http_status = $status;
            $delivery->response_excerpt = Str::limit((string) $response->body(), 480, '…');

            if ($response->successful()) {
                $this->markSuccess($delivery, $endpoint);

                return;
            }

            // 410 Gone: der Empfänger (n8n/Make/Zapier) hat die Subscription
            // entfernt → sofortiges Auto-Unsubscribe, KEIN Retry (Selbstheilung).
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

    /**
     * Wird von der Queue nach Aufbrauchen aller Versuche aufgerufen.
     * Stellt sicher, dass auch der letzte Fehlschlag protokolliert/gezählt ist.
     */
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
     * REST-Hooks-Selbstheilung (Feature 008 → Rang 61): Antwortet der Empfänger
     * mit 410 Gone, ist die Subscription dort gelöscht. Wir bestellen sofort ab
     * (deaktivieren + Soft-Delete), ohne Retry — ein erneuter Zustellversuch wäre
     * sinnlos. Der `active`/`disabled_at`-Filter greift auch dort, wo der
     * Dispatch-Service `withoutGlobalScopes()` nutzt (Soft-Delete allein reicht
     * dort nicht).
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

        // Endgültiger Fehlschlag (letzter Versuch) → Fehlerzähler erhöhen und
        // ggf. auto-deaktivieren. Zwischen-Retries lassen den Zähler unberührt;
        // sie laufen über die Queue erneut und können noch erfolgreich werden.
        if ($this->attempts() >= $this->tries) {
            $this->registerEndpointFailure($endpoint);
        }

        // Exception werfen, damit die Queue den Retry-Mechanismus auslöst.
        if ($this->attempts() < $this->tries) {
            throw new \RuntimeException('webhook delivery failed: ' . $reason);
        }
    }

    /**
     * Erhöht den Fehlerzähler des Endpunkts und deaktiviert ihn nach Erreichen
     * der Schwelle automatisch.
     */
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
