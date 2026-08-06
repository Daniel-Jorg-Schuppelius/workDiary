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
use App\Plugins\Support\Calendar\{RemoteCalendarEvent, RemoteCalendarGateway, RemoteCalendarItem};
use App\Plugins\Support\{ConnectionTokenStore, PluginApiClient, PluginHttpFactory};
use RuntimeException;
use Throwable;

/**
 * Microsoft-Graph-Kalender-Gateway (MVP-328, Bauturbo A8) auf dem
 * `php-api-toolkit`-Fundament: OAuth2-Bearer über den org-gebundenen
 * {@see ConnectionTokenStore} inkl. transparentem Refresh (abgelaufenes Token
 * vor dem Request; 401 ⇒ Refresh ⇒ genau ein Retry im ClientAbstract).
 *
 * - Anlegen: POST `/me/events` bzw. `/me/calendars/{id}/events` mit
 *   `transactionId` = stabile UID (Graph-seitige Create-Idempotenz);
 *   die Remote-Event-ID trägt die {@see \App\Models\ExternalReference}.
 * - Ändern: PATCH `/me/events/{id}`; Löschen: DELETE (404 = idempotent ok).
 * - Fehlersemantik wie CalDAV-Gateway: Transport-/HTTP-Fehler ⇒ null/false.
 */
class MsgraphCalendarClient implements RemoteCalendarGateway, GraphSubscriptionClient {
    use Concerns\ManagesGraphSubscriptions;

    private PluginApiClient $api;

    private string $base;

    public function __construct(private readonly MsgraphConnection $connection) {
        $this->base = MsgraphConfig::resolve()['api_base'];
        $this->api = app(PluginHttpFactory::class)->client(MsgraphPlugin::ID, $this->base);

        // Grant nur bei vorhandener Konfiguration — ohne ihn bleibt das
        // Bearer-Token nutzbar, nur ohne Refresh-Möglichkeit. Org der
        // Verbindung explizit (Variante B: per-Org-App, queue-sicher).
        $orgId = (int) $connection->organization_id;
        $grant = MsgraphConfig::isConfigured($orgId) ? app(MsgraphOAuth::class)->grantFor($orgId) : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($this->connection), $grant));
    }

    public function createEvent(RemoteCalendarItem $event): ?string {
        if (! $event instanceof RemoteCalendarEvent) {
            return null; // dieses Gateway publiziert nur strukturierte Events
        }
        $calendarId = trim((string) $this->connection->calendar_id);
        $url = $calendarId !== ''
            ? $this->base . '/me/calendars/' . rawurlencode($calendarId) . '/events'
            : $this->base . '/me/events';

        $payload = $this->payload($event) + ['transactionId' => $event->uid];
        // Teams-Meeting-Link (MS365-Plan C1): Opt-in je Verbindung, NUR beim
        // Anlegen — Graph kann ein Online-Meeting nicht wieder entfernen,
        // Bestandstermine bleiben deshalb unangetastet.
        if ($this->connection->teams_meetings) {
            $payload += ['isOnlineMeeting' => true, 'onlineMeetingProvider' => 'teamsForBusiness'];
        }

        try {
            // transactionId (≤ 255 Zeichen, nur beim Anlegen erlaubt) macht
            // den Create Graph-seitig idempotent — Queue-/Lauf-Wiederholungen
            // erzeugen kein Duplikat, selbst wenn die Referenz noch fehlt.
            $response = $this->api->postJson($url, $payload);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $id = $response->json('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function updateEvent(string $remoteId, RemoteCalendarItem $event): bool {
        if (! $event instanceof RemoteCalendarEvent) {
            return false;
        }

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
     * calendarView-DELTA des Ziel-Kalenders (Zwei-Wege C3): erste Seite ab
     * Zeitfenster bzw. Folge-Aufruf über die absolute Checkpoint-URL.
     * 410 Gone (Delta-Token abgelaufen) ⇒ {@see \App\Services\CloudIntake\StaleCheckpointException}
     * — der Aufrufer startet neu ab Zeitfenster.
     *
     * @return array{items: list<array<string, mixed>>, checkpoint: string, hasMore: bool}
     */
    public function calendarDelta(?string $checkpoint, \DateTimeInterface $windowStart, \DateTimeInterface $windowEnd): array {
        if ($checkpoint === null || $checkpoint === '') {
            $calendarId = trim((string) $this->connection->calendar_id);
            $url = $calendarId !== ''
                ? $this->base . '/me/calendars/' . rawurlencode($calendarId) . '/calendarView/delta'
                : $this->base . '/me/calendarView/delta';
            $response = $this->api->getResponse($url, [
                'startDateTime' => $windowStart->format('Y-m-d\TH:i:s\Z'),
                'endDateTime' => $windowEnd->format('Y-m-d\TH:i:s\Z'),
            ]);
        } else {
            $response = $this->api->getResponse($checkpoint); // absolute next-/deltaLink-URL
        }

        if ($response->status() === 410) {
            throw new \App\Services\CloudIntake\StaleCheckpointException('Kalender-Delta-Token abgelaufen (410 Gone).');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Graph calendarView/delta fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{value?: list<array<string, mixed>>, '@odata.nextLink'?: string, '@odata.deltaLink'?: string} $data */
        $data = (array) $response->json();

        $items = $data['value'] ?? [];
        // Direkt aus dem Array — json('@odata.…') würde die Punkte als
        // Pfad-Notation deuten (Intake-Client-Muster).
        $nextLink = isset($data['@odata.nextLink']) ? (string) $data['@odata.nextLink'] : null;
        $deltaLink = isset($data['@odata.deltaLink']) ? (string) $data['@odata.deltaLink'] : null;

        return [
            'items' => $items,
            'checkpoint' => (string) ($nextLink ?? $deltaLink ?? ''),
            'hasMore' => $nextLink !== null && $nextLink !== '',
        ];
    }

    /**
     * AAD-User-ID zu einer E-Mail/UPN (Presence, MS365-Plan F — braucht
     * `User.ReadBasic.All`). null = nicht auflösbar/kein Zugriff.
     */
    public function userIdByEmail(string $email): ?string {
        try {
            $response = $this->api->getResponse($this->base . '/users/' . rawurlencode($email), ['$select' => 'id']);
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }
        $id = $response->json('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Teams-Presence je AAD-User-ID (MS365-Plan F — braucht `Presence.Read.All`;
     * Limit 1.500 Requests/30 s, Aufrufer cachen).
     *
     * @param  list<string>  $userIds
     * @return array<string, string> id → availability (Available/Busy/Away/…)
     */
    public function presencesByUserIds(array $userIds): array {
        if ($userIds === []) {
            return [];
        }

        try {
            $response = $this->api->postJson($this->base . '/communications/getPresencesByUserId', [
                'ids' => $userIds,
            ]);
        } catch (Throwable) {
            return [];
        }
        if (! $response->successful()) {
            return [];
        }

        $out = [];
        foreach ((array) $response->json('value', []) as $row) {
            if (is_array($row) && is_string($row['id'] ?? null)) {
                $out[$row['id']] = (string) ($row['availability'] ?? 'PresenceUnknown');
            }
        }

        return $out;
    }

    /**
     * Free/Busy je Adresse im Zeitfenster (MS365-Plan C2, `getSchedule` —
     * `Calendars.ReadBasic` genügt, der Kalender-Grant deckt das ab).
     * Rückgabe je Adresse: 'free' | 'busy' | 'unknown' (Fehler/kein Zugriff).
     *
     * @param  list<string>  $addresses
     * @return array<string, string>
     */
    public function freeBusy(array $addresses, \DateTimeInterface $start, \DateTimeInterface $end): array {
        $result = array_fill_keys($addresses, 'unknown');
        if ($addresses === []) {
            return $result;
        }

        try {
            $response = $this->api->postJson($this->base . '/me/calendar/getSchedule', [
                'schedules' => $addresses,
                'startTime' => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
                'endTime' => ['dateTime' => $end->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
                'availabilityViewInterval' => 30,
            ]);
        } catch (Throwable) {
            return $result;
        }
        if (! $response->successful()) {
            return $result;
        }

        foreach ((array) $response->json('value', []) as $schedule) {
            if (! is_array($schedule) || ! is_string($schedule['scheduleId'] ?? null)) {
                continue;
            }
            if (isset($schedule['error'])) {
                continue; // kein Zugriff auf dieses Postfach → unknown
            }
            $busy = false;
            foreach ((array) ($schedule['scheduleItems'] ?? []) as $item) {
                $status = is_array($item) ? (string) ($item['status'] ?? '') : '';
                if (in_array($status, ['busy', 'oof', 'tentative'], true)) {
                    $busy = true;

                    break;
                }
            }
            $result[$schedule['scheduleId']] = $busy ? 'busy' : 'free';
        }

        return $result;
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
