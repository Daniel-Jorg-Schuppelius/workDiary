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
use App\Plugins\Todoist\Jobs\TodoistWebhookSyncJob;
use App\Plugins\Todoist\TodoistConfig;
use Illuminate\Database\UniqueConstraintViolationException;
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
 * Der Webhook ist nur IMPULS: er stößt einen gezielten Abgleich als Queue-Job
 * an und schreibt nie direkt Felder. Verlässliche Quelle bleibt das Polling.
 */
class TodoistWebhookController extends Controller {
    public function __invoke(Request $request): JsonResponse {
        $secret = TodoistConfig::resolve()['client_secret'];
        $signature = (string) $request->header('X-Todoist-Hmac-SHA256', '');
        $raw = (string) $request->getContent();

        if ($secret === '' || $signature === ''
            || ! hash_equals(base64_encode(hash_hmac('sha256', $raw, $secret, true)), $signature)) {
            return response()->json(['message' => 'invalid signature'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($raw, true);
        $deliveryId = (string) $request->header('X-Todoist-Delivery-ID', '');
        if ($deliveryId === '') {
            $deliveryId = hash('sha256', $raw); // Fallback: inhaltsbasierte Dedup
        }

        try {
            $delivery = TodoistWebhookDelivery::query()->create([
                'delivery_id' => $deliveryId,
                'event_name' => isset($payload['event_name']) ? (string) $payload['event_name'] : null,
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate']);
        }

        $todoistUserId = (string) ($payload['user_id'] ?? '');
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
}
