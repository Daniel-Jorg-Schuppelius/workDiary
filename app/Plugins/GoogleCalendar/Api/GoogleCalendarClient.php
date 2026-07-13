<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleCalendar\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\GoogleCalendarConnection;
use App\Plugins\GoogleCalendar\{GoogleCalendarConfig, GoogleCalendarPlugin};
use App\Plugins\Support\Calendar\{RemoteCalendarEvent, RemoteCalendarGateway};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use RuntimeException;
use Throwable;

/**
 * Google-Calendar-Gateway (API v3, MVP-328, Bauturbo A8) auf dem
 * `php-api-toolkit`-Fundament: OAuth2-Bearer über den org-gebundenen
 * {@see GoogleCalendarTokenStore} inkl. transparentem Refresh (abgelaufenes
 * Token vor dem Request; 401 ⇒ Refresh ⇒ genau ein Retry im ClientAbstract).
 *
 * - Anlegen: `events.insert` mit **deterministischer Event-ID** aus der
 *   stabilen UID (sha1-Hex ⊂ base32hex-Alphabet der API) — ein erneuter
 *   Insert derselben UID antwortet 409 und wird als Update behandelt
 *   (kein Duplikat, selbst wenn die Referenz verloren ging).
 * - Ändern: `events.update` (PUT); Löschen: `events.delete`
 *   (404/410 = idempotent ok). Ziel-Kalender: `calendar_id` (leer = primary).
 * - Fehlersemantik wie CalDAV-Gateway: Transport-/HTTP-Fehler ⇒ null/false.
 */
class GoogleCalendarClient implements RemoteCalendarGateway {
    private PluginApiClient $api;

    private string $base;

    public function __construct(private readonly GoogleCalendarConnection $connection) {
        $this->base = GoogleCalendarConfig::resolve()['api_base'];
        $this->api = app(PluginHttpFactory::class)->client(GoogleCalendarPlugin::ID, $this->base);

        // Grant nur bei vorhandener Installation-Konfiguration — ohne ihn
        // bleibt das Bearer-Token nutzbar, nur ohne Refresh-Möglichkeit.
        $grant = GoogleCalendarConfig::isConfigured() ? app(GoogleCalendarOAuth::class)->grant() : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new GoogleCalendarTokenStore($this->connection), $grant));
    }

    /**
     * Deterministische Google-Event-ID aus der stabilen UID: sha1-Hex nutzt
     * nur 0-9a-f und liegt damit im erlaubten base32hex-Alphabet (0-9a-v),
     * Länge 40 (erlaubt 5–1024).
     */
    public static function eventId(string $uid): string {
        return sha1($uid);
    }

    public function createEvent(RemoteCalendarEvent $event): ?string {
        $id = self::eventId($event->uid);

        try {
            $response = $this->api->postJson($this->eventsUrl(), ['id' => $id] + $this->payload($event));
        } catch (Throwable) {
            return null;
        }

        // 409 = ID existiert bereits (Referenz verloren/früherer Lauf) →
        // Update statt Duplikat.
        if ($response->status() === 409) {
            return $this->updateEvent($id, $event) ? $id : null;
        }

        return $response->successful() ? $id : null;
    }

    public function updateEvent(string $remoteId, RemoteCalendarEvent $event): bool {
        try {
            $response = $this->api->putJson($this->eventsUrl() . '/' . rawurlencode($remoteId), $this->payload($event));
        } catch (Throwable) {
            return false;
        }

        return $response->successful();
    }

    public function deleteEvent(string $remoteId): bool {
        try {
            $response = $this->api->deleteResponse($this->eventsUrl() . '/' . rawurlencode($remoteId));
        } catch (Throwable) {
            return false;
        }

        // 404/410 = bereits entfernt → idempotenter Erfolg.
        return $response->successful() || in_array($response->status(), [404, 410], true);
    }

    public function listCalendars(): array {
        $response = $this->api->getResponse($this->base . '/users/me/calendarList', ['fields' => 'items(id,summary)']);
        if (! $response->successful()) {
            // Nur Statuscode + Pfad — nie Payload/Token in Fehlermeldungen.
            throw new RuntimeException(sprintf('Google Calendar /users/me/calendarList antwortete mit HTTP %d.', $response->status()));
        }

        $calendars = [];
        foreach ((array) $response->json('items', []) as $row) {
            if (is_array($row) && is_string($row['id'] ?? null) && $row['id'] !== '') {
                $calendars[] = ['id' => $row['id'], 'name' => (string) ($row['summary'] ?? $row['id'])];
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
     * Google-Event-Payload aus dem providerneutralen Element; dateTime als
     * RFC 3339 mit Offset + `timeZone` (API-Anforderung).
     *
     * @return array<string, mixed>
     */
    private function payload(RemoteCalendarEvent $event): array {
        $payload = [
            'summary' => $event->title,
            'start' => ['dateTime' => $event->start->format('c'), 'timeZone' => $event->timezone],
            'end' => ['dateTime' => $event->end->format('c'), 'timeZone' => $event->timezone],
        ];
        if ($event->description !== null) {
            $payload['description'] = $event->description;
        }
        if ($event->location !== '') {
            $payload['location'] = $event->location;
        }

        return $payload;
    }

    private function eventsUrl(): string {
        return $this->base . '/calendars/' . rawurlencode($this->connection->targetCalendarId()) . '/events';
    }
}
