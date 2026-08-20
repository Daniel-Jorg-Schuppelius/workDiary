<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LexofficeWebhookDelivery;
use App\Plugins\Lexoffice\Jobs\{SyncContactsJob, SyncVouchersJob};
use App\Plugins\Lexoffice\LexofficeConfig;
use App\Plugins\Support\{RecordsWebhookDeliveries, WebhookSignature};
use Illuminate\Http\{JsonResponse, Request};

/**
 * Sessionloser Lexoffice-Webhook-Endpunkt (Audit 2026-08, Welle 1.3).
 * Reihenfolge ist sicherheitsrelevant (Muster Todoist/Zammad):
 *  1. URL-Token je Organisation (Lexoffice erlaubt keine eigenen Header —
 *     das Secret steckt als Pfadsegment in der Callback-URL), Konstantzeit-
 *     Vergleich; 404 statt 401/403, keine Auskunft über Existenz/Zustand.
 *  2. Optional zusätzlich RSA-Prüfung des `X-Lxo-Signature`-Headers
 *     (base64, SHA-512 über den Raw-Body) gegen den konfigurierten
 *     Lexoffice-Public-Key — wenn ein Key hinterlegt ist, ist die Prüfung
 *     Pflicht (kein Fail-Open).
 *  3. Deduplizierung über den Inhalts-Hash, persistiert VOR der Verarbeitung.
 * Der Webhook ist nur IMPULS: er stößt die vorhandenen idempotenten
 * Pull-Syncs als unique Queue-Jobs an (Burst-Dedup über ShouldBeUnique) und
 * übernimmt NIE Daten aus dem Payload. Fällt er aus, holt der geplante
 * Sync lückenlos nach.
 */
class LexofficeWebhookController extends Controller {
    use RecordsWebhookDeliveries;

    public function __invoke(Request $request, int $organization, string $token): JsonResponse {
        $config = LexofficeConfig::resolve($organization);
        $secret = is_string($config['webhook_secret']) ? $config['webhook_secret'] : null;

        if (! $config['enabled'] || ! WebhookSignature::tokenValid($secret, $token)) {
            return response()->json(['status' => 'ignored'], 404);
        }

        $raw = (string) $request->getContent();

        // RSA-Signatur (Defense-in-Depth): nur prüfbar, wenn der Public Key
        // hinterlegt ist — dann aber verbindlich.
        $publicKey = is_string($config['webhook_public_key']) ? trim($config['webhook_public_key']) : '';
        if ($publicKey !== '' && ! $this->rsaSignatureValid($raw, (string) $request->header('X-Lxo-Signature', ''), $publicKey)) {
            return response()->json(['status' => 'invalid_signature'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($raw, true);
        $eventType = isset($payload['eventType']) ? (string) $payload['eventType'] : '';
        $resourceId = isset($payload['resourceId']) ? (string) $payload['resourceId'] : '';

        $delivery = $this->recordDelivery(fn (): LexofficeWebhookDelivery => LexofficeWebhookDelivery::query()->create([
            'delivery_hash' => $this->deliveryHash($raw),
            'event_type' => $eventType !== '' ? $eventType : null,
            'resource_id' => $resourceId !== '' ? $resourceId : null,
            'organization_id' => $organization,
            'received_at' => now(),
        ]));
        if ($delivery === null) {
            return response()->json(['status' => 'duplicate']);
        }

        // Impuls → vorhandene idempotente Syncs; unbekannte Events bleiben
        // protokolliert liegen (kein Fehler — Abo-Umfang kann drüben wachsen).
        if (str_starts_with($eventType, 'contact.')) {
            SyncContactsJob::dispatch($organization);
        } elseif (str_starts_with($eventType, 'voucher.') || str_starts_with($eventType, 'payment.')) {
            SyncVouchersJob::dispatch($organization);
        }

        $delivery->forceFill(['processed_at' => now()])->save();

        return response()->json(['status' => 'queued']);
    }

    /** base64-RSA-SHA512-Signatur des Raw-Bodys gegen den Lexoffice-Public-Key. */
    private function rsaSignatureValid(string $payload, string $provided, string $publicKeyPem): bool {
        if ($provided === '') {
            return false;
        }
        $signature = base64_decode($provided, true);
        if ($signature === false) {
            return false;
        }
        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            return false; // unbrauchbarer Key = fail closed
        }

        return openssl_verify($payload, $signature, $key, OPENSSL_ALGO_SHA512) === 1;
    }
}
