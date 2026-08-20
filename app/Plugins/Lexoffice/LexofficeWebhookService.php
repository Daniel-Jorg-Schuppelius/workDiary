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

namespace App\Plugins\Lexoffice;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use RuntimeException;

/**
 * Verwaltung der Lexoffice-Event-Subscriptions (Audit 2026-08, Welle 1.3):
 * legt je Organisation die Webhook-Abos für Kontakt-/Beleg-/Zahlungs-Events
 * an bzw. räumt sie ab. Läuft — wie der VoucherSync — über die
 * PluginHttpFactory (kein SDK nötig; die Http::fake-Blockade der
 * SDK-Migration betrifft diesen Pfad nicht).
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

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
    ) {}

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('lexoffice', $this->baseUrl);
            $this->api->setAuthentication(new BearerAuthentication((string) $this->apiKey));
        }

        return $this->api;
    }

    /**
     * Bestehende Subscriptions des API-Keys.
     *
     * @return list<array{id: string, eventType: string, callbackUrl: string}>
     */
    public function subscriptions(): array {
        $response = $this->api()->getResponse($this->baseUrl . '/event-subscriptions');
        if (! $response->successful()) {
            throw new LexofficeApiException('Lexoffice: Event-Subscriptions nicht abrufbar (HTTP ' . $response->status() . ').', $response->status());
        }

        $rows = $response->json('content');

        $result = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
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
            $response = $this->api()->postJson($this->baseUrl . '/event-subscriptions', [
                'eventType' => $eventType,
                'callbackUrl' => $callbackUrl,
            ]);
            if (! $response->successful()) {
                throw new LexofficeApiException('Lexoffice: Subscription für ' . $eventType . ' nicht anlegbar (HTTP ' . $response->status() . ').', $response->status());
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
            $response = $this->api()->deleteResponse($this->baseUrl . '/event-subscriptions/' . $subscription['id']);
            if (! $response->successful()) {
                throw new LexofficeApiException('Lexoffice: Subscription ' . $subscription['id'] . ' nicht löschbar (HTTP ' . $response->status() . ').', $response->status());
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
