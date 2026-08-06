<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphChangeWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{EmailConnection, MsgraphConnection, MsgraphTaskListLink};
use App\Plugins\Msgraph\Jobs\{MsgraphCalendarWakeJob, MsgraphMailWakeJob, MsgraphTodoWakeJob};
use App\Plugins\Support\WebhookSignature;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Cache;

/**
 * Generische Graph-Change-Notification (Feature 102, Folgeausbau) für
 * Zwei-Wege-Kalender, To-Do-Listen und Graph-Postfächer — NUR Aufwecksignal
 * (adr-webhook-security.md, Muster {@see MsgraphIntakeWebhookController}):
 * Subscription-Validierung spiegelt den `validationToken` als text/plain;
 * Notifications werden über `subscriptionId` + `clientState`
 * (Konstantzeit-Vergleich gegen das je Träger gespeicherte Secret)
 * serverseitig zugeordnet — nie über Payload-Mandantendaten. Der Impuls
 * dispatcht den regulären Delta-/Sync-Lauf als Queue-Job (60 s Debounce je
 * Träger gegen Notification-Bursts); das Polling bleibt die heilende Quelle.
 */
class MsgraphChangeWebhookController extends Controller {
    /** Debounce je Träger — Graph schickt Bursts, ein Lauf genügt. */
    private const DEBOUNCE_SECONDS = 60;

    public function __invoke(Request $request): Response {
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

            $this->wake($subscriptionId, $clientState);
        }

        // Graph erwartet zügig 202.
        return response('', 202);
    }

    /** Träger der Subscription auflösen und den passenden Lauf anstoßen. */
    private function wake(string $subscriptionId, string $clientState): void {
        $calendar = MsgraphConnection::query()
            ->withoutGlobalScopes()
            ->where('subscription_id', $subscriptionId)
            ->first();
        if ($calendar instanceof MsgraphConnection) {
            if (WebhookSignature::tokenValid((string) ($calendar->webhook_secret ?? ''), $clientState)
                && Cache::add('msgraph:wake:calendar:' . $calendar->id, true, self::DEBOUNCE_SECONDS)) {
                MsgraphCalendarWakeJob::dispatch((int) $calendar->organization_id);
            }

            return; // Zuordnung gefunden — falsches clientState still ignorieren, kein Oracle
        }

        $link = MsgraphTaskListLink::query()
            ->withoutGlobalScopes()
            ->where('subscription_id', $subscriptionId)
            ->first();
        if ($link instanceof MsgraphTaskListLink) {
            if (WebhookSignature::tokenValid((string) ($link->webhook_secret ?? ''), $clientState)
                && Cache::add('msgraph:wake:todo:' . $link->id, true, self::DEBOUNCE_SECONDS)) {
                MsgraphTodoWakeJob::dispatch((int) $link->organization_id, (int) $link->id);
            }

            return;
        }

        $mailbox = EmailConnection::query()
            ->withoutGlobalScopes()
            ->where('transport', EmailConnection::TRANSPORT_MSGRAPH)
            ->where('subscription_id', $subscriptionId)
            ->first();
        if ($mailbox instanceof EmailConnection
            && WebhookSignature::tokenValid((string) ($mailbox->webhook_secret ?? ''), $clientState)
            && Cache::add('msgraph:wake:mail:' . $mailbox->id, true, self::DEBOUNCE_SECONDS)) {
            MsgraphMailWakeJob::dispatch((int) $mailbox->organization_id, (int) $mailbox->id);
        }
    }
}
