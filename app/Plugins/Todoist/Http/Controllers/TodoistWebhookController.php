<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistWebhookController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Todoist\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{TodoistConnection, TodoistWebhookDelivery};
use App\Plugins\Support\{RecordsWebhookDeliveries, WebhookSignature};
use App\Plugins\Todoist\Jobs\TodoistWebhookSyncJob;
use App\Plugins\Todoist\TodoistConfig;
use Illuminate\Http\{JsonResponse, Request};

/**
 * Sessionloser Todoist-Webhook-Endpunkt (Feature 055, MVP-115).
 * Reihenfolge ist sicherheitsrelevant (Plan 055):
 *  1. HMAC-SHA256 über den UNVERÄNDERTEN Raw-Body mit dem Client-Secret,
 *     konstantzeitlicher Vergleich (hash_equals) — vor jeder Verarbeitung.
 *  2. Deduplizierung über die Delivery-ID, persistiert VOR der Verarbeitung —
 *     Replays enden idempotent.
 *  3. Org-Zuordnung erst NACH der Signaturprüfung über
 *     `todoist_connections.todoist_user_id`.
 *
 * Seit den per-Organisation hinterlegbaren App-Registrierungen kann die
 * Signatur aus einer FREMDEN Todoist-App stammen. Der Endpunkt kennt die
 * Organisation zu diesem Zeitpunkt noch nicht, darf dem Rumpf aber nichts
 * glauben. Deshalb liest er die `user_id` nur als HINWEIS, um die Kandidaten
 * einzugrenzen, und prüft dann gegen das Instanz-Secret und die Secrets der
 * aktiven Anschlüsse dieses Todoist-Kontos. Erst eine passende Signatur
 * begründet irgendeine Verarbeitung; ein Fehlschlag antwortet unabhängig von
 * der Zahl der Kandidaten gleich.
 * Der Webhook ist nur IMPULS: er stößt einen gezielten Abgleich als Queue-Job
 * an und schreibt nie direkt Felder. Verlässliche Quelle bleibt das Polling.
 */
class TodoistWebhookController extends Controller {
    use RecordsWebhookDeliveries;

    public function __invoke(Request $request): JsonResponse {
        $raw = (string) $request->getContent();
        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($raw, true);
        $todoistUserId = (string) ($payload['user_id'] ?? '');

        // Todoist-Signatur: base64(HMAC-SHA256(body, client_secret)).
        $signature = (string) $request->header('X-Todoist-Hmac-SHA256', '');
        $valid = false;
        foreach ($this->candidateSecrets($todoistUserId) as $secret) {
            // Nicht abbrechen: jeder Kandidat wird geprüft, damit die Dauer
            // nicht verrät, welcher gepasst hat.
            $valid = WebhookSignature::hmacValid($raw, $secret, $signature, 'sha256', encoding: 'base64') || $valid;
        }
        if (! $valid) {
            return response()->json(['message' => 'invalid signature'], 401);
        }

        $deliveryId = (string) $request->header('X-Todoist-Delivery-ID', '');
        if ($deliveryId === '') {
            $deliveryId = $this->deliveryHash($raw); // Fallback: inhaltsbasierte Dedup
        }

        $delivery = $this->recordDelivery(fn (): TodoistWebhookDelivery => TodoistWebhookDelivery::query()->create([
            'delivery_id' => $deliveryId,
            'event_name' => isset($payload['event_name']) ? (string) $payload['event_name'] : null,
            'received_at' => now(),
        ]));
        if ($delivery === null) {
            return response()->json(['status' => 'duplicate']);
        }

        $connections = $todoistUserId === ''
            ? collect()
            : TodoistConnection::query()->withoutGlobalScopes()
                ->where('todoist_user_id', $todoistUserId)
                ->where('status', TodoistConnection::STATUS_ACTIVE)
                ->get();

        if ($connections->isEmpty()) {
            // Korrekt signiert, aber keinem aktiven Anschluss zuordenbar —
            // protokolliert lassen, nichts verarbeiten (keine Rückschlüsse nach außen).
            return response()->json(['status' => 'ignored']);
        }

        $eventData = (array) ($payload['event_data'] ?? []);
        $projectId = isset($eventData['project_id']) ? (string) $eventData['project_id'] : null;

        $delivery->forceFill(['organization_id' => (int) $connections->first()->organization_id])->save();

        foreach ($connections as $connection) {
            TodoistWebhookSyncJob::dispatch((int) $connection->organization_id, $projectId, (int) $delivery->id);
        }

        return response()->json(['status' => 'queued']);
    }

    /**
     * Secrets, gegen die die Signatur geprüft wird: das der Instanz-App plus
     * die der aktiven Anschlüsse dieses Todoist-Kontos (eigene App-
     * Registrierungen). Leere Werte und Dubletten fallen raus.
     *
     * @return list<string>
     */
    private function candidateSecrets(string $todoistUserId): array {
        $secrets = [TodoistConfig::resolve(TodoistConfig::INSTANCE)['client_secret']];

        if ($todoistUserId !== '') {
            $orgIds = TodoistConnection::query()->withoutGlobalScopes()
                ->where('todoist_user_id', $todoistUserId)
                ->where('status', TodoistConnection::STATUS_ACTIVE)
                ->pluck('organization_id');
            foreach ($orgIds as $orgId) {
                $secrets[] = TodoistConfig::resolve((int) $orgId)['client_secret'];
            }
        }

        return array_values(array_unique(array_filter($secrets, static fn (string $s): bool => $s !== '')));
    }
}
