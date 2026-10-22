<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeWebhookService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use APIToolkit\Entities\ID;
use APIToolkit\Exceptions\ApiException;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\Exceptions\LexofficeApiException;
use CommonToolkit\Helper\Data\JsonHelper;
use Lexoffice\API\Client;
use Lexoffice\API\Endpoints\EventSubscriptionsEndpoint;
use Lexoffice\Entities\EventSubscriptions\EventSubscription;
use RuntimeException;

/**
 * Verwaltung der Lexoffice-Event-Subscriptions (Audit 2026-08, Welle 1.3):
 * legt je Organisation die Webhook-Abos für Kontakt-/Beleg-/Zahlungs-Events
 * an bzw. räumt sie ab. Läuft über den SDK-Client der
 * {@see LexofficeClientFactory}.
 */
class LexofficeWebhookService {
    /** Abonnierte Events: Webhook ist nur Anstoß für gezielte Pull-Syncs. */
    public const EVENTS = [
        'contact.created',
        'contact.changed',
        'voucher.created',
        'voucher.changed',
        'payment.changed',
    ];

    private ?Client $client = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.lexware.io/v1',
        private readonly ?float $requestInterval = null,
    ) {}

    private function client(): Client {
        return $this->client ??= app(LexofficeClientFactory::class)->sdk((string) $this->apiKey, $this->baseUrl, $this->requestInterval);
    }

    /**
     * Bestehende Subscriptions des API-Keys.
     *
     * @return list<array{id: string, eventType: string, callbackUrl: string}>
     */
    public function subscriptions(): array {
        // Gleicher SDK-Client (Drosselung), aber ohne Entitäten-Mapping: Das Enum
        // EventType des SDK kennt nicht jede Ereignisart der API (etwa
        // delivery-note.status.changed), und ein fremdes Abo des API-Schlüssels
        // ließe sonst die ganze Liste scheitern.
        try {
            $response = $this->client()->get('event-subscriptions');
        } catch (ApiException $e) {
            throw new LexofficeApiException('Lexoffice: Event-Subscriptions nicht abrufbar (HTTP ' . $e->getCode() . ').', $e->getCode());
        }
        if ($response->getStatusCode() !== 200) {
            throw new LexofficeApiException('Lexoffice: Event-Subscriptions nicht abrufbar (HTTP ' . $response->getStatusCode() . ').', $response->getStatusCode());
        }

        $decoded = JsonHelper::decode((string) $response->getBody(), true);
        $rows = is_array($decoded) && is_array($decoded['content'] ?? null) ? $decoded['content'] : [];
        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $result[] = [
                'id' => (string) ($row['subscriptionId'] ?? ($row['id'] ?? '')),
                'eventType' => (string) ($row['eventType'] ?? ''),
                'callbackUrl' => (string) ($row['callbackUrl'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Legt fehlende Subscriptions für die Callback-URL an (idempotent über
     * die Liste des API-Keys).
     *
     * @return array{created: int, existing: int}
     */
    public function ensureSubscriptions(string $callbackUrl): array {
        $this->assertConfigured();

        $existing = [];
        foreach ($this->subscriptions() as $subscription) {
            if ($subscription['callbackUrl'] === $callbackUrl) {
                $existing[$subscription['eventType']] = true;
            }
        }

        $created = 0;
        foreach (self::EVENTS as $eventType) {
            if (isset($existing[$eventType])) {
                continue;
            }
            try {
                (new EventSubscriptionsEndpoint($this->client()))->create(EventSubscription::fromArray([
                    'eventType' => $eventType,
                    'callbackUrl' => $callbackUrl,
                ]));
            } catch (ApiException $e) {
                throw new LexofficeApiException('Lexoffice: Subscription für ' . $eventType . ' nicht anlegbar (HTTP ' . $e->getCode() . ').', $e->getCode());
            }
            $created++;
        }

        return ['created' => $created, 'existing' => count($existing)];
    }

    /**
     * Entfernt alle Subscriptions, deren Callback auf die übergebene URL
     * zeigt (z. B. beim Deaktivieren oder Token-Wechsel).
     */
    public function removeSubscriptions(string $callbackUrl): int {
        $this->assertConfigured();

        $removed = 0;
        foreach ($this->subscriptions() as $subscription) {
            if ($subscription['callbackUrl'] !== $callbackUrl || $subscription['id'] === '') {
                continue;
            }
            try {
                (new EventSubscriptionsEndpoint($this->client()))->delete(new ID($subscription['id']));
            } catch (ApiException $e) {
                throw new LexofficeApiException('Lexoffice: Subscription ' . $subscription['id'] . ' nicht löschbar (HTTP ' . $e->getCode() . ').', $e->getCode());
            }
            $removed++;
        }

        return $removed;
    }

    private function assertConfigured(): void {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }
    }
}
