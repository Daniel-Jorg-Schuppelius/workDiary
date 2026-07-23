<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Calendly\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\CalendlyConnection;
use App\Plugins\Calendly\{CalendlyConfig, CalendlyPlugin};
use App\Plugins\Support\{ConnectionTokenStore, PluginApiClient, PluginHttpFactory};
use Throwable;

/**
 * Calendly-API-v2-Gateway (Feature 095) auf dem `php-api-toolkit`-Fundament:
 * OAuth2-Bearer über den org-gebundenen {@see ConnectionTokenStore} inkl.
 * transparentem Refresh. Fehlersemantik wie die übrigen Plugin-Gateways:
 * HTTP-Fehler kommen als Response zurück (kein throw), Methoden liefern
 * null/false/[] bei Misserfolg. Endpunkte werden teils per absoluter URI
 * angesprochen (Calendly referenziert Ressourcen als URIs).
 */
class CalendlyClient {
    private PluginApiClient $api;

    private string $base;

    public function __construct(private readonly CalendlyConnection $connection) {
        $this->base = CalendlyConfig::resolve()['api_base'];
        $this->api = app(PluginHttpFactory::class)->client(CalendlyPlugin::ID, $this->base);

        $grant = CalendlyConfig::isConfigured() ? app(CalendlyOAuth::class)->grant() : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($this->connection), $grant));
    }

    /**
     * `GET /users/me` — der verbundene Nutzer inkl. `current_organization`.
     *
     * @return array<string, mixed>|null
     */
    public function currentUser(): ?array {
        try {
            $response = $this->api->getResponse($this->base . '/users/me');
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }
        $resource = $response->json('resource');

        return is_array($resource) ? $resource : null;
    }

    public function ping(): bool {
        return $this->currentUser() !== null;
    }

    /**
     * `GET /scheduled_events` (eine Seite). Liefert `collection` +
     * `next_page_token` (null = letzte Seite) + `success` (false bei
     * HTTP-Fehler oder Exception, damit Aufrufer Connection-Health korrekt
     * setzen können).
     *
     * @return array{collection: list<array<string, mixed>>, next_page_token: ?string, success: bool}
     */
    public function listScheduledEvents(string $organizationUri, string $minStartTime, string $maxStartTime, ?string $pageToken = null): array {
        $query = [
            'organization' => $organizationUri,
            'min_start_time' => $minStartTime,
            'max_start_time' => $maxStartTime,
            'count' => 100,
        ];
        if ($pageToken !== null && $pageToken !== '') {
            $query['page_token'] = $pageToken;
        }

        try {
            $response = $this->api->getResponse($this->base . '/scheduled_events', $query);
        } catch (Throwable) {
            return ['collection' => [], 'next_page_token' => null, 'success' => false];
        }
        if (! $response->successful()) {
            return ['collection' => [], 'next_page_token' => null, 'success' => false];
        }

        return [
            'collection' => $this->collection($response->json('collection')),
            'next_page_token' => $this->pageToken($response->json('pagination')),
            'success' => true,
        ];
    }

    /**
     * `GET {eventUri}/invitees` — Invitees eines Scheduled Events.
     *
     * @return list<array<string, mixed>>
     */
    public function listEventInvitees(string $eventUri): array {
        try {
            $response = $this->api->getResponse(rtrim($eventUri, '/') . '/invitees', ['count' => 100]);
        } catch (Throwable) {
            return [];
        }

        return $response->successful() ? $this->collection($response->json('collection')) : [];
    }

    /**
     * `POST /webhook_subscriptions`. Liefert die angelegte Subscription-Resource.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function createWebhookSubscription(array $payload): ?array {
        try {
            $response = $this->api->postJson($this->base . '/webhook_subscriptions', $payload);
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }
        $resource = $response->json('resource');

        return is_array($resource) ? $resource : null;
    }

    /**
     * `GET /webhook_subscriptions` (eine Seite) für Org/Scope.
     *
     * @return list<array<string, mixed>>
     */
    public function listWebhookSubscriptions(string $organizationUri, string $scope): array {
        try {
            $response = $this->api->getResponse($this->base . '/webhook_subscriptions', [
                'organization' => $organizationUri,
                'scope' => $scope,
                'count' => 100,
            ]);
        } catch (Throwable) {
            return [];
        }

        return $response->successful() ? $this->collection($response->json('collection')) : [];
    }

    /** `DELETE {subscriptionUri}`. 404/410 = bereits entfernt → idempotenter Erfolg. */
    public function deleteWebhookSubscription(string $subscriptionUri): bool {
        try {
            $response = $this->api->deleteResponse($subscriptionUri);
        } catch (Throwable) {
            return false;
        }

        return $response->successful() || in_array($response->status(), [404, 410], true);
    }

    /**
     * `POST /one_off_event_types` — Einmal-Buchung; liefert die EventType-Resource
     * (mit `scheduling_url`).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function createOneOffEventType(array $payload): ?array {
        try {
            $response = $this->api->postJson($this->base . '/one_off_event_types', $payload);
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }
        $resource = $response->json('resource');

        return is_array($resource) ? $resource : null;
    }

    /** `POST /scheduled_events/{uuid}/cancellation` — Termin absagen. */
    public function cancelScheduledEvent(string $eventUuid, ?string $reason = null): bool {
        try {
            $response = $this->api->postJson($this->base . '/scheduled_events/' . rawurlencode($eventUuid) . '/cancellation', [
                'reason' => (string) $reason,
            ]);
        } catch (Throwable) {
            return false;
        }

        return $response->successful();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collection(mixed $collection): array {
        if (! is_array($collection)) {
            return [];
        }
        $rows = [];
        foreach ($collection as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function pageToken(mixed $pagination): ?string {
        if (is_array($pagination) && is_string($pagination['next_page_token'] ?? null) && $pagination['next_page_token'] !== '') {
            return $pagination['next_page_token'];
        }

        return null;
    }
}
