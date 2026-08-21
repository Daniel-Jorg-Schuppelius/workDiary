<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{Organization, TimeTrackingWebhookDelivery};
use App\Plugins\Support\{RecordsWebhookDeliveries, WebhookSignature};
use App\Plugins\Support\TimeTracking\{TimeTrackingWebhookGate, WebhookImportJob};
use App\Plugins\Toggl\TogglPlugin;
use Illuminate\Http\{JsonResponse, Request};

/**
 * Sessionloser Toggl-Webhook (Feature 124, MVP-613).
 *
 * Reihenfolge ist sicherheitsrelevant:
 *  1. Workspace lesen und Mandant auflösen — ohne ihn gibt es kein Geheimnis.
 *  2. HMAC-SHA256 über den UNVERÄNDERTEN Raw-Body, konstantzeitlicher
 *     Vergleich, VOR jeder Verarbeitung.
 *  3. Dedup über die Delivery-ID, persistiert VOR der Verarbeitung.
 *
 * Toggl schickt beim Anlegen einer Subscription eine Prüfnachricht mit
 * `validation_code`; die wird unsigniert zurückgespiegelt — das ist der
 * dokumentierte Ablauf und der einzige Fall ohne Signaturprüfung.
 *
 * Der Webhook ERSETZT das Polling nicht. Toggl sichert keine Zustellung zu;
 * ein verlorener Aufruf würde sonst einen Zeiteintrag kosten.
 */
class TogglWebhookController extends Controller {
    use RecordsWebhookDeliveries;

    public function __invoke(Request $request, TimeTrackingWebhookGate $gate): JsonResponse {
        $raw = (string) $request->getContent();
        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($raw, true);

        // Ping beim Anlegen der Subscription: Code zurückspiegeln.
        $validation = trim((string) ($payload['validation_code'] ?? ''));
        if ($validation !== '') {
            return response()->json(['validation_code' => $validation]);
        }

        $organization = $gate->organizationFor(TogglPlugin::ID, (string) ($payload['metadata']['workspace_id'] ?? ($payload['payload']['workspace_id'] ?? '')));
        if (! $organization instanceof Organization) {
            // Kein Rückschluss nach außen, welcher Workspace bekannt ist.
            return response()->json(['status' => 'ignored']);
        }

        $secret = $gate->secretFor(TogglPlugin::ID, (int) $organization->id);
        if (! WebhookSignature::hmacValid($raw, $secret, (string) $request->header('X-Webhook-Signature-256', ''), 'sha256', prefix: 'sha256=')) {
            return response()->json(['message' => 'invalid signature'], 401);
        }

        $deliveryId = trim((string) ($payload['event_id'] ?? ''));
        if ($deliveryId === '') {
            $deliveryId = $this->deliveryHash($raw);
        }

        $delivery = $this->recordDelivery(fn (): TimeTrackingWebhookDelivery => TimeTrackingWebhookDelivery::query()->create([
            'plugin_id' => TogglPlugin::ID,
            'delivery_id' => $deliveryId,
            'event_name' => mb_substr((string) ($payload['metadata']['action'] ?? ''), 0, 128) ?: null,
            'organization_id' => (int) $organization->id,
            'received_at' => now(),
        ]));
        if ($delivery === null) {
            return response()->json(['status' => 'duplicate']);
        }

        // Entprellt: Ein Lauf je Zeiteintrag würde genau die Quote sprengen,
        // die der Webhook entlasten soll.
        if (! $gate->shouldRun(TogglPlugin::ID, (int) $organization->id)) {
            return response()->json(['status' => 'debounced']);
        }

        WebhookImportJob::dispatch(TogglPlugin::ID, (int) $organization->id, (int) $delivery->id);

        return response()->json(['status' => 'queued']);
    }
}
