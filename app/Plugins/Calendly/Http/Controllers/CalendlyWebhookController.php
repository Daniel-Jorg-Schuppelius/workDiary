<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{CalendlyWebhookDelivery, CalendlyWebhookSubscription};
use App\Plugins\Calendly\Jobs\CalendlyIngestJob;
use App\Plugins\Support\WebhookSignature;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\{JsonResponse, Request};

/**
 * Sessionloser Calendly-Webhook-Endpunkt (Feature 095). Reihenfolge ist
 * sicherheitsrelevant:
 *  1. Der opake `{token}` aus der URL schlägt die Subscription → Organisation
 *     + `signing_key` in O(1) nach (Org NIE aus dem Payload).
 *  2. Signaturprüfung des UNVERÄNDERTEN Raw-Bodys: Header
 *     `Calendly-Webhook-Signature: t=<ts>,v1=<hex>`, signiert wird `"<ts>.<body>"`
 *     per HMAC-SHA256, konstantzeitlich (hash_equals). Timestamp-Skew ≤ 5 min
 *     (Replay-Schutz).
 *  3. Deduplizierung über den Body-Hash, persistiert VOR der Verarbeitung.
 * Der Webhook ist nur Impuls: er stößt einen Queue-Job an und schreibt nie
 * direkt Felder. Verlässliche Quelle bleibt der Polling-Backfill.
 */
class CalendlyWebhookController extends Controller {
    private const MAX_SKEW_SECONDS = 300;

    public function __invoke(Request $request, string $token): JsonResponse {
        $subscription = CalendlyWebhookSubscription::query()
            ->withoutGlobalScopes()
            ->where('url_token', $token)
            ->where('status', CalendlyWebhookSubscription::STATUS_ACTIVE)
            ->first();

        if (! $subscription instanceof CalendlyWebhookSubscription) {
            return response()->json(['message' => 'not found'], 404);
        }

        $raw = (string) $request->getContent();
        [$timestamp, $signature] = $this->parseSignature((string) $request->header('Calendly-Webhook-Signature', ''));

        if ($timestamp === null || $signature === null) {
            return response()->json(['message' => 'invalid signature'], 401);
        }
        if (abs(now()->getTimestamp() - $timestamp) > self::MAX_SKEW_SECONDS) {
            return response()->json(['message' => 'stale'], 401);
        }
        if (! WebhookSignature::hmacValid($timestamp . '.' . $raw, $subscription->signing_key, $signature, 'sha256', encoding: 'hex')) {
            return response()->json(['message' => 'invalid signature'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($raw, true);
        $inviteePayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
        $inviteeUri = is_string($inviteePayload['uri'] ?? null) ? $inviteePayload['uri'] : null;

        try {
            $delivery = CalendlyWebhookDelivery::query()->create([
                'delivery_hash' => CryptoHelper::hash($raw),
                'event_name' => isset($payload['event']) ? (string) $payload['event'] : null,
                'invitee_uri' => $inviteeUri,
                'organization_id' => (int) $subscription->organization_id,
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate']);
        }

        $subscription->forceFill(['last_delivery_at' => now()])->save();
        CalendlyIngestJob::dispatch((int) $subscription->organization_id, $raw, (int) $delivery->id);

        return response()->json(['status' => 'queued']);
    }

    /**
     * Parst den `Calendly-Webhook-Signature`-Header (`t=<ts>,v1=<hex>`).
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function parseSignature(string $header): array {
        $timestamp = null;
        $signature = null;

        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 't=')) {
                $value = substr($part, 2);
                if ($value !== '' && ctype_digit($value)) {
                    $timestamp = (int) $value;
                }
            } elseif (str_starts_with($part, 'v1=')) {
                $value = substr($part, 3);
                $signature = $value !== '' ? $value : null;
            }
        }

        return [$timestamp, $signature];
    }
}
