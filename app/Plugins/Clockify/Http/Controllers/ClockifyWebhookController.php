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

use App\Plugins\Clockify\ClockifyPlugin;
use App\Plugins\Support\TimeTracking\TimeTrackingWebhookController;
use App\Plugins\Support\WebhookSignature;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\Request;

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
class ClockifyWebhookController extends TimeTrackingWebhookController {
    protected function pluginId(): string {
        return ClockifyPlugin::ID;
    }

    protected function workspaceId(array $payload): string {
        return (string) ($payload['workspaceId'] ?? '');
    }

    protected function signatureValid(Request $request, string $raw, ?string $secret): bool {
        return WebhookSignature::tokenValid($secret, (string) $request->header('Clockify-Signature', ''));
    }

    /**
     * Der Rumpf ist der Zeiteintrag; seine `id` bleibt über jede Änderung
     * gleich und taugt nicht als Zustell-ID. Ereignisart + Rumpf: dieselbe
     * Zustellung ergibt denselben Wert, eine spätere Änderung einen neuen.
     */
    protected function deliveryId(Request $request, array $payload, string $raw): string {
        return CryptoHelper::hash($this->eventName($request, $payload) . "\n" . $raw);
    }

    protected function eventName(Request $request, array $payload): string {
        return (string) $request->header('Clockify-Webhook-Event-Type', '');
    }
}
