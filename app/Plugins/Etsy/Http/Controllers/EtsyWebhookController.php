<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{EtsyConnection, EtsyWebhookDelivery};
use App\Plugins\Etsy\EtsyConfig;
use App\Plugins\Etsy\Jobs\EtsyWebhookIngestJob;
use App\Plugins\Support\{RecordsWebhookDeliveries, SvixWebhookSignature};
use Illuminate\Http\{JsonResponse, Request};

/**
 * Sessionsloser Etsy-Webhook-Endpunkt (Feature 101, MVP-496). Reihenfolge
 * ist sicherheitsrelevant:
 *  1. Der opake `{token}` aus der URL schlägt die Connection → Organisation
 *     + Webhook-Secret in O(1) nach (Org NIE aus dem Payload).
 *  2. Svix-Signaturprüfung des UNVERÄNDERTEN Raw-Bodys (Header `webhook-id`/
 *     `webhook-timestamp`/`webhook-signature`, HMAC-SHA256 über
 *     `id.timestamp.body`, `whsec_`-Secret) — konstantzeitlich, Skew ≤ 5 min.
 *  3. `shop_id` aus dem Payload ist NUR Konsistenz-Check gegen die Connection.
 *  4. Deduplizierung über den Body-Hash, persistiert VOR der Verarbeitung.
 * Der Webhook ist nur Impuls: der Queue-Job lädt das Receipt selbst über die
 * fixe Base-URL nach (die `resource_url` aus dem Payload wird nie abgerufen —
 * SSRF-Disziplin). Verlässliche Quelle bleibt der Polling-Sweep (etsy:sync).
 */
class EtsyWebhookController extends Controller {
    use RecordsWebhookDeliveries;

    private const MAX_SKEW_SECONDS = 300;

    public function __invoke(Request $request, string $token): JsonResponse {
        $connection = EtsyConnection::query()
            ->withoutGlobalScopes()
            ->where('webhook_token', $token)
            ->where('status', EtsyConnection::STATUS_ACTIVE)
            ->first();

        if (! $connection instanceof EtsyConnection) {
            return response()->json(['message' => 'not found'], 404);
        }

        $raw = (string) $request->getContent();
        $webhookId = (string) $request->header('webhook-id', '');
        $timestamp = (string) $request->header('webhook-timestamp', '');
        $signature = (string) $request->header('webhook-signature', '');

        if ($timestamp === '' || ! ctype_digit($timestamp)
            || abs(now()->getTimestamp() - (int) $timestamp) > self::MAX_SKEW_SECONDS) {
            return response()->json(['message' => 'stale'], 401);
        }

        $secret = EtsyConfig::resolve((int) $connection->organization_id)['webhook_secret'] ?? null;
        if (! SvixWebhookSignature::valid($webhookId, $timestamp, $raw, $secret, $signature)) {
            return response()->json(['message' => 'invalid signature'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($raw, true);
        $eventType = isset($payload['event_type']) ? (string) $payload['event_type'] : null;
        $receiptId = $this->receiptIdFrom($payload);

        // shop_id nur als Konsistenz-Check — nie zur Org-Auflösung.
        $shopId = is_numeric($payload['shop_id'] ?? null) ? (int) $payload['shop_id'] : null;
        if ($receiptId === null || ($shopId !== null && $shopId !== (int) $connection->shop_id)) {
            return response()->json(['status' => 'ignored']);
        }

        $delivery = $this->recordDelivery(fn(): EtsyWebhookDelivery => EtsyWebhookDelivery::query()->create([
            'delivery_hash' => $this->deliveryHash($raw),
            'webhook_id' => $webhookId !== '' ? mb_substr($webhookId, 0, 64) : null,
            'event_type' => $eventType !== null ? mb_substr($eventType, 0, 32) : null,
            'receipt_id' => $receiptId,
            'organization_id' => (int) $connection->organization_id,
            'received_at' => now(),
        ]));
        if ($delivery === null) {
            return response()->json(['status' => 'duplicate']);
        }

        EtsyWebhookIngestJob::dispatch((int) $connection->organization_id, $receiptId, (int) $delivery->id);

        return response()->json(['status' => 'queued']);
    }

    /**
     * Receipt-ID aus der `resource_url` parsen — der Payload trägt kein
     * eigenes receipt_id-Feld (W0 §7); die URL selbst wird nie abgerufen.
     *
     * @param  array<string, mixed>  $payload
     */
    private function receiptIdFrom(array $payload): ?int {
        $resourceUrl = is_string($payload['resource_url'] ?? null) ? $payload['resource_url'] : '';
        if (preg_match('#/receipts/(\d+)#', $resourceUrl, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
