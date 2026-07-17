<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphIntakeWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Http\Controllers;

use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Http\Controllers\Controller;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Support\WebhookSignature;
use App\Services\CloudIntake\IntakeWakeSignal;
use Illuminate\Http\{Request, Response};

/**
 * Graph-Change-Notification für den Dokumenteingang (Feature 080, MVP-354) —
 * NUR Aufwecksignal (adr-webhook-security.md): Subscription-Validierung
 * spiegelt den `validationToken` als text/plain; Notifications werden über
 * `subscriptionId` + `clientState` (Konstantzeit-Vergleich gegen das je
 * Verbindung gespeicherte Secret) serverseitig einer Verbindung zugeordnet —
 * nie über Payload-Mandantendaten.
 */
class MsgraphIntakeWebhookController extends Controller {
    public function __invoke(Request $request, IntakeWakeSignal $wake): Response {
        // Subscription-Validierung (Graph sendet POST mit validationToken).
        $validationToken = (string) $request->query('validationToken', '');
        if ($validationToken !== '') {
            return response($validationToken, 200, [
                'Content-Type' => 'text/plain',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        /** @var list<array{subscriptionId?: string, clientState?: string}> $notifications */
        $notifications = (array) $request->json('value', []);

        foreach ($notifications as $notification) {
            $subscriptionId = (string) ($notification['subscriptionId'] ?? '');
            $clientState = (string) ($notification['clientState'] ?? '');
            if ($subscriptionId === '' || $clientState === '') {
                continue;
            }

            $connection = CloudDocumentConnection::query()
                ->withoutGlobalScopes()
                ->where('provider', CloudIntakeProvider::Microsoft->value)
                ->where('subscription_id', $subscriptionId)
                ->first();

            if ($connection === null) {
                continue;
            }

            if (! WebhookSignature::tokenValid((string) ($connection->webhook_secret ?? ''), $clientState)) {
                continue; // falsches clientState ⇒ still ignorieren, kein Oracle
            }

            $wake->signal((int) $connection->id);
        }

        // Graph erwartet zügig 202.
        return response('', 202);
    }
}
