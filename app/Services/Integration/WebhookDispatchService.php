<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookDispatchService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Enums\Integration\{WebhookDeliveryStatus, WebhookEvent};
use App\Jobs\Integration\WebhookDeliveryJob;
use App\Models\Integration\{WebhookDelivery, WebhookEndpoint};
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Veröffentlicht fachliche Domänen-Ereignisse als ausgehende Webhooks
 * (Feature 008). Aufgerufen wird der Service additiv aus dem zentralen
 * NotificationDispatcher: jedes real gefeuerte Benachrichtigungs-Ereignis,
 * das eine {@see WebhookEvent}-Entsprechung hat, wird hier an alle aktiven,
 * abonnierten Endpunkte der Organisation gefächert.
 *
 * Der eigentliche HTTP-Versand inkl. HMAC-Signatur, Timeout, Retry und
 * Auto-Deaktivierung passiert im {@see WebhookDeliveryJob} (Queue), damit
 * synchrone Geschäftslogik nie durch langsame/fehlerhafte Empfänger blockiert.
 */
class WebhookDispatchService {
    /**
     * Fächert ein Ereignis an alle passenden Endpunkte einer Organisation.
     *
     * @param  array<string, mixed>  $data  minimaler, dokumentierter Payload je Event
     * @return int  Anzahl ausgelöster Zustellungen
     */
    public function publish(WebhookEvent $event, int $organizationId, array $data): int {
        // `withoutGlobalScopes()` nimmt neben dem OrganizationScope auch den
        // SoftDeletingScope weg — gelöschte Endpunkte wurden deshalb weiter
        // beliefert (Sicherheitsscan 2026-08-23, S-27). Nur der Org-Scope
        // gehört hier abgeschaltet; die Organisation kommt als Parameter.
        $endpoints = WebhookEndpoint::query()
            ->withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->whereNull('disabled_at')
            ->get()
            ->filter(fn(WebhookEndpoint $e): bool => $e->subscribesTo($event));

        $count = 0;
        foreach ($endpoints as $endpoint) {
            try {
                $this->queueDelivery($endpoint, $event, $data);
                $count++;
            } catch (Throwable $e) {
                // Webhook-Fehler dürfen die auslösende Geschäftslogik nie
                // scheitern lassen (additive Veröffentlichung).
                Log::warning('webhook: publish failed', [
                    'event' => $event->value,
                    'endpoint' => $endpoint->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Manuelles Test-Event („Test-Event senden"-Button). Sendet unabhängig
     * vom Abonnement, damit die Erreichbarkeit/Signatur geprüft werden kann.
     */
    public function sendTest(WebhookEndpoint $endpoint): WebhookDelivery {
        return $this->queueDelivery($endpoint, WebhookEvent::OpenIssueAssigned, [
            'test' => true,
            'message' => 'WorkDiary webhook test event',
        ]);
    }

    /**
     * Baut den signierfähigen, minimalen Payload für ein Ereignis.
     *
     * Bewusst flach und arm an personenbezogenen Daten: nur Event-Schlüssel,
     * Zeitstempel, Organisations-ID und ein knappes, je Event dokumentiertes
     * data-Objekt. Empfänger reichern bei Bedarf über die REST-API an.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function buildPayload(WebhookEvent $event, int $organizationId, array $data, Carbon $occurredAt): array {
        return [
            'event' => $event->value,
            'occurred_at' => $occurredAt->toIso8601String(),
            'organization' => ['id' => $organizationId],
            'data' => $data,
        ];
    }

    /**
     * Erzeugt die pending-Delivery und stellt den Versand-Job in die Queue.
     *
     * @param  array<string, mixed>  $data
     */
    private function queueDelivery(WebhookEndpoint $endpoint, WebhookEvent $event, array $data): WebhookDelivery {
        $occurredAt = Carbon::now();
        $payload = $this->buildPayload($event, (int) $endpoint->organization_id, $data, $occurredAt);
        $body = JsonHelper::encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $delivery = new WebhookDelivery([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => $event->value,
            'payload_hash' => CryptoHelper::hash($body),
            'status' => WebhookDeliveryStatus::Pending,
            'attempt' => 1,
            'dispatched_at' => $occurredAt,
        ]);
        $delivery->organization_id = (int) $endpoint->organization_id;
        $delivery->save();

        WebhookDeliveryJob::dispatch($delivery->id, $body, $occurredAt->getTimestamp());

        return $delivery;
    }
}
