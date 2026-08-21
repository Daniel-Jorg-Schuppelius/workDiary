<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Clockify\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{Organization, TimeTrackingWebhookDelivery};
use App\Plugins\Clockify\ClockifyPlugin;
use App\Plugins\Support\{RecordsWebhookDeliveries, WebhookSignature};
use App\Plugins\Support\TimeTracking\{TimeTrackingWebhookGate, WebhookImportJob};
use Illuminate\Http\{JsonResponse, Request};

/**
 * Sessionloser Clockify-Webhook (Feature 124, MVP-613).
 *
 * Clockify signiert NICHT per HMAC, sondern schickt das bei der Einrichtung
 * vergebene Geheimnis im Header `Clockify-Signature`. Verglichen wird
 * trotzdem in Konstantzeit — ein Token-Vergleich mit `===` verrät über die
 * Laufzeit Präfixe.
 *
 * Wie beim Toggl-Zwilling weckt der Webhook nur; das Polling bleibt die
 * verlässliche Quelle.
 *
 * **Tarif:** Webhooks sind bei Clockify ein Bestandteil der kostenpflichtigen
 * Tarife. Ohne Tarif-Deckung bleibt der Endpunkt ungenutzt — er stört dann
 * niemanden, aber er hilft auch nicht.
 */
class ClockifyWebhookController extends Controller {
    use RecordsWebhookDeliveries;

    public function __invoke(Request $request, TimeTrackingWebhookGate $gate): JsonResponse {
        $raw = (string) $request->getContent();
        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($raw, true);

        $organization = $gate->organizationFor(ClockifyPlugin::ID, (string) ($payload['workspaceId'] ?? ''));
        if (! $organization instanceof Organization) {
            return response()->json(['status' => 'ignored']);
        }

        $secret = $gate->secretFor(ClockifyPlugin::ID, (int) $organization->id);
        if (! WebhookSignature::tokenValid($secret, (string) $request->header('Clockify-Signature', ''))) {
            return response()->json(['message' => 'invalid signature'], 401);
        }

        $deliveryId = trim((string) ($payload['id'] ?? ''));
        if ($deliveryId === '') {
            $deliveryId = $this->deliveryHash($raw);
        }

        $delivery = $this->recordDelivery(fn (): TimeTrackingWebhookDelivery => TimeTrackingWebhookDelivery::query()->create([
            'plugin_id' => ClockifyPlugin::ID,
            'delivery_id' => $deliveryId,
            'event_name' => mb_substr((string) $request->header('Clockify-Webhook-Event-Type', ''), 0, 128) ?: null,
            'organization_id' => (int) $organization->id,
            'received_at' => now(),
        ]));
        if ($delivery === null) {
            return response()->json(['status' => 'duplicate']);
        }

        if (! $gate->shouldRun(ClockifyPlugin::ID, (int) $organization->id)) {
            return response()->json(['status' => 'debounced']);
        }

        WebhookImportJob::dispatch(ClockifyPlugin::ID, (int) $organization->id, (int) $delivery->id);

        return response()->json(['status' => 'queued']);
    }
}
