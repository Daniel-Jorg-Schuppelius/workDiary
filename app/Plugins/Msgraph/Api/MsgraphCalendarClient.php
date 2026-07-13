<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphCalendarClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\MsgraphConnection;
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Plugins\Support\Calendar\{RemoteCalendarEvent, RemoteCalendarGateway};
use App\Plugins\Support\Msgraph\GraphTokenStore;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use RuntimeException;
use Throwable;

/**
 * Microsoft-Graph-Kalender-Gateway (MVP-328, Bauturbo A8) auf dem
 * `php-api-toolkit`-Fundament: OAuth2-Bearer über den org-gebundenen
 * {@see GraphTokenStore} inkl. transparentem Refresh (abgelaufenes Token
 * vor dem Request; 401 ⇒ Refresh ⇒ genau ein Retry im ClientAbstract).
 *
 * - Anlegen: POST `/me/events` bzw. `/me/calendars/{id}/events` mit
 *   `transactionId` = stabile UID (Graph-seitige Create-Idempotenz);
 *   die Remote-Event-ID trägt die {@see \App\Models\ExternalReference}.
 * - Ändern: PATCH `/me/events/{id}`; Löschen: DELETE (404 = idempotent ok).
 * - Fehlersemantik wie CalDAV-Gateway: Transport-/HTTP-Fehler ⇒ null/false.
 */
class MsgraphCalendarClient implements RemoteCalendarGateway {
    private PluginApiClient $api;

    private string $base;

    public function __construct(private readonly MsgraphConnection $connection) {
        $this->base = MsgraphConfig::resolve()['api_base'];
        $this->api = app(PluginHttpFactory::class)->client(MsgraphPlugin::ID, $this->base);

        // Grant nur bei vorhandener Installation-Konfiguration — ohne ihn
        // bleibt das Bearer-Token nutzbar, nur ohne Refresh-Möglichkeit.
        $grant = MsgraphConfig::isConfigured() ? app(MsgraphOAuth::class)->grant() : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new GraphTokenStore($this->connection), $grant));
    }

    public function createEvent(RemoteCalendarEvent $event): ?string {
        $calendarId = trim((string) $this->connection->calendar_id);
        $url = $calendarId !== ''
            ? $this->base . '/me/calendars/' . rawurlencode($calendarId) . '/events'
            : $this->base . '/me/events';

        try {
            // transactionId (≤ 255 Zeichen, nur beim Anlegen erlaubt) macht
            // den Create Graph-seitig idempotent — Queue-/Lauf-Wiederholungen
            // erzeugen kein Duplikat, selbst wenn die Referenz noch fehlt.
            $response = $this->api->postJson($url, $this->payload($event) + ['transactionId' => $event->uid]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $id = $response->json('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function updateEvent(string $remoteId, RemoteCalendarEvent $event): bool {
        try {
            $response = $this->api->requestResponse('patch', $this->eventUrl($remoteId), ['json' => $this->payload($event)]);
        } catch (Throwable) {
            return false;
        }

        return $response->successful();
    }

    public function deleteEvent(string $remoteId): bool {
        try {
            $response = $this->api->deleteResponse($this->eventUrl($remoteId));
        } catch (Throwable) {
            return false;
        }

        // 404 = bereits entfernt → idempotenter Erfolg.
        return $response->successful() || $response->status() === 404;
    }

    public function listCalendars(): array {
        $response = $this->api->getResponse($this->base . '/me/calendars', ['$select' => 'id,name']);
        if (! $response->successful()) {
            // Nur Statuscode + Pfad — nie Payload/Token in Fehlermeldungen.
            throw new RuntimeException(sprintf('Microsoft Graph /me/calendars antwortete mit HTTP %d.', $response->status()));
        }

        $calendars = [];
        foreach ((array) $response->json('value', []) as $row) {
            if (is_array($row) && is_string($row['id'] ?? null) && $row['id'] !== '') {
                $calendars[] = ['id' => $row['id'], 'name' => (string) ($row['name'] ?? $row['id'])];
            }
        }

        return $calendars;
    }

    public function ping(): bool {
        try {
            $this->listCalendars();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Graph-Event-Payload aus dem providerneutralen Element; Zeiten als
     * lokale dateTime + `timeZone` (Graph interpretiert die Kombination).
     *
     * @return array<string, mixed>
     */
    private function payload(RemoteCalendarEvent $event): array {
        $payload = [
            'subject' => $event->title,
            'body' => ['contentType' => 'text', 'content' => (string) $event->description],
            'start' => ['dateTime' => $event->start->format('Y-m-d\TH:i:s'), 'timeZone' => $event->timezone],
            'end' => ['dateTime' => $event->end->format('Y-m-d\TH:i:s'), 'timeZone' => $event->timezone],
        ];
        if ($event->location !== '') {
            $payload['location'] = ['displayName' => $event->location];
        }

        return $payload;
    }

    private function eventUrl(string $remoteId): string {
        return $this->base . '/me/events/' . rawurlencode($remoteId);
    }
}
