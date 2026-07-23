<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamViewerClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport\Providers;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Carbon\CarbonImmutable;

/**
 * Client für die TeamViewer Web-API (https://webapi.teamviewer.com/api/v1).
 * Authentifizierung per Script-Token (Bearer). Liest Verbindungs-Reports über
 * `/reports/connections` und mappt sie auf {@see RemoteSession}.
 *
 * Gematcht wird das Asset später über das Feld `deviceid` (TeamViewer-ID).
 */
class TeamViewerClient implements RemoteProvider {
    public const ID = 'teamviewer';

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://webapi.teamviewer.com/api/v1',
    ) {}

    public function id(): string {
        return self::ID;
    }

    public function isConfigured(): bool {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    public function ping(): bool {
        if (! $this->isConfigured()) {
            return false;
        }

        return $this->api()
            ->getResponse($this->baseUrl . '/ping', [], ['timeout' => 5])
            ->successful();
    }

    public function fetchSessions(CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->isConfigured()) {
            return [];
        }

        $sessions = [];
        $offset = null;

        do {
            $query = [
                'from_date' => $from->toIso8601String(),
                'to_date' => $to->toIso8601String(),
            ];
            if ($offset !== null) {
                $query['offset_id'] = $offset;
            }

            $response = $this->api()->getResponse($this->baseUrl . '/reports/connections', $query, ['timeout' => 15]);

            if (! $response->successful()) {
                break;
            }

            foreach ((array) ($response->json('records') ?? []) as $record) {
                $session = $this->mapRecord((array) $record);
                if ($session !== null) {
                    $sessions[] = $session;
                }
            }

            $offset = $response->json('next_offset');
        } while (is_string($offset) && $offset !== '');

        return $sessions;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function mapRecord(array $record): ?RemoteSession {
        $deviceId = (string) ($record['deviceid'] ?? '');
        $start = (string) ($record['start_date'] ?? '');
        $end = (string) ($record['end_date'] ?? '');
        if ($deviceId === '' || $start === '' || $end === '') {
            return null;
        }

        $note = trim(implode(' · ', array_filter([
            isset($record['username']) ? (string) $record['username'] : null,
            isset($record['notes']) ? (string) $record['notes'] : null,
        ])));

        // Klartext-Gerätename — Pendant zum AnyDesk-Alias (Inbox-Badge und
        // Namensvorschlag beim Anlegen); nur übernehmen, wenn er sich von der ID unterscheidet.
        $alias = trim((string) ($record['devicename'] ?? ''));
        $alias = ($alias !== '' && $alias !== $deviceId) ? $alias : null;

        return new RemoteSession(
            provider: self::ID,
            sessionId: (string) ($record['id'] ?? ($deviceId . '|' . $start)),
            remoteId: $deviceId,
            startedAt: CarbonImmutable::parse($start),
            endedAt: CarbonImmutable::parse($end),
            note: $note !== '' ? $note : null,
            alias: $alias,
        );
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('remote-support', $this->baseUrl);
            $this->api->setAuthentication(new BearerAuthentication((string) $this->apiKey));
        }

        return $this->api;
    }
}
