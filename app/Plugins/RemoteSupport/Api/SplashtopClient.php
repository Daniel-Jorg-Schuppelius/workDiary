<?php
/*
 * Created on   : Wed Nov 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SplashtopClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\RemoteSupport\Api;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Plugins\RemoteSupport\Exceptions\RemoteProviderException;
use App\Plugins\RemoteSupport\Providers\{RemoteProvider, RemoteSession};
use App\Plugins\RemoteSupport\RemoteSupportPlugin;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, OffsetPage, RemotePages};
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Splashtop Open API (Referenz 2.1, 2026-08-04; nur Splashtop Enterprise):
 * abgeschlossene Sitzungen über `POST /api/open/v1/teams/logs/closed_sessions`
 * mit API-Token (Bearer), je Sitzungsart eine Anfrage, Fenster in Stücken bis
 * 90 Tage und höchstens ein Jahr zurück, Seiten zu 500. Gematcht wird das
 * Gerät über `srs_uuid` (Team-Computer-UUID), der Name `srs_name` ist der
 * Alias. Ein Ende liefert die API nicht: Ende = `created_at` + `duration`
 * (Sekunden; die Doku nennt keine Einheit, der Pilot bestätigt sie).
 */
class SplashtopClient implements RemoteProvider {
    public const string ID = 'splashtop';

    public const string DEFAULT_REGION = 'eu';

    public const array REGIONS = [
        'com' => 'https://webapi.splashtop.com',
        'eu' => 'https://webapi.splashtop.eu',
        'nr' => 'https://webapi.splashtop.nr',
    ];

    public const string DEFAULT_SERVICE_KINDS = 'unattended_remote,attended_remote';

    public const int PAGE_SIZE = 500;

    public const int MAX_PAGES = 200;

    /** Längstes Fenster einer Anfrage laut Doku. */
    public const int CHUNK_DAYS = 90;

    /** Frühester erlaubter Beginn laut Doku: ein Jahr zurück. */
    public const int MAX_AGE_DAYS = 365;

    private const string FIELDS = 'id,created_at,duration,srs_uuid,srs_name,service_kind,status,note,full_note,src_user_email';

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $apiToken,
        private readonly string $region = self::DEFAULT_REGION,
        private readonly ?string $teamId = null,
        private readonly string $serviceKinds = self::DEFAULT_SERVICE_KINDS,
    ) {}

    public function id(): string {
        return self::ID;
    }

    public function isConfigured(): bool {
        return $this->apiToken !== null && $this->apiToken !== '';
    }

    public function probe(): void {
        $this->basicInfo();
    }

    public function fetchSessions(CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->isConfigured()) {
            return [];
        }

        $earliest = CarbonImmutable::now()->subDays(self::MAX_AGE_DAYS)->addHour();
        if ($from < $earliest) {
            $from = $earliest;
        }
        if ($to <= $from) {
            return [];
        }

        $teamId = $this->resolveTeamId();
        $sessions = [];
        foreach ($this->serviceKindList() as $kind) {
            $chunkStart = $from;
            while ($chunkStart < $to) {
                $chunkEnd = min($chunkStart->addDays(self::CHUNK_DAYS), $to);
                try {
                    foreach (RemotePages::offset(fn (int $page): OffsetPage => $this->page($teamId, $kind, $chunkStart, $chunkEnd, $page), self::MAX_PAGES) as $item) {
                        $session = $this->mapItem((array) $item);
                        if ($session !== null) {
                            $sessions[] = $session;
                        }
                    }
                } catch (IncompleteRemoteReadException $e) {
                    throw RemoteProviderException::incomplete(self::ID, $e->reason, $sessions);
                }
                $chunkStart = $chunkEnd;
            }
        }

        return $sessions;
    }

    /**
     * @return OffsetPage<array<mixed>>
     */
    private function page(?int $teamId, string $kind, CarbonImmutable $from, CarbonImmutable $to, int $page): OffsetPage {
        $payload = [
            'service_kinds' => $kind,
            'start_time' => $from->utc()->format('Y-m-d H:i:s'),
            'end_time' => $to->utc()->format('Y-m-d H:i:s'),
            'page' => (string) $page,
            'per_page_size' => (string) self::PAGE_SIZE,
            'fields' => self::FIELDS,
        ];
        if ($teamId !== null) {
            $payload['team_id'] = $teamId;
        }

        // Lesender POST: das Toolkit wiederholt ihn bei 429/503 nur mit dieser Option.
        $response = $this->api()->postJson($this->baseUrl() . '/api/open/v1/teams/logs/closed_sessions', $payload, ['timeout' => 30, 'retry_non_idempotent' => true]);
        $data = $this->data($response, 'Sitzungsliste');

        // Die Doku zeigt `data` als Objekt mit `items`, die Hülle beschreibt `data` als Liste — beides lesen.
        $items = $data['items'] ?? $data;
        $items = array_values(array_filter(is_array($items) ? $items : [], 'is_array'));
        $totalPages = $data['total_pages'] ?? null;
        $totalEntries = $data['total_entries'] ?? null;
        $hasMore = is_numeric($totalPages) ? $page < (int) $totalPages : count($items) === self::PAGE_SIZE;

        return new OffsetPage($items, $hasMore, is_numeric($totalEntries) ? (int) $totalEntries : null);
    }

    /**
     * @param  array<mixed>  $item
     */
    private function mapItem(array $item): ?RemoteSession {
        $remoteId = trim((string) ($item['srs_uuid'] ?? ''));
        $createdAt = $item['created_at'] ?? null;
        $duration = $item['duration'] ?? null;
        if ($remoteId === '' || ! is_string($createdAt) || $createdAt === '' || ! is_numeric($duration)) {
            return null;
        }

        try {
            $startedAt = CarbonImmutable::parse($createdAt)->utc();
        } catch (Throwable) {
            return null;
        }

        $alias = trim((string) ($item['srs_name'] ?? ''));
        $alias = ($alias !== '' && $alias !== $remoteId) ? $alias : null;

        $noteText = trim((string) ($item['full_note'] ?? ''));
        if ($noteText === '') {
            $noteText = trim((string) ($item['note'] ?? ''));
        }
        $note = implode(' · ', array_filter([
            $noteText,
            trim((string) ($item['src_user_email'] ?? '')),
        ]));

        return new RemoteSession(
            provider: self::ID,
            sessionId: (string) ($item['id'] ?? ($remoteId . '|' . $startedAt->getTimestamp())),
            remoteId: $remoteId,
            startedAt: $startedAt,
            endedAt: $startedAt->addSeconds((int) $duration),
            note: $note !== '' ? $note : null,
            alias: $alias,
        );
    }

    /** Team des Tokens aus den Kontoinformationen, sofern nicht konfiguriert. */
    private function resolveTeamId(): ?int {
        if ($this->teamId !== null && is_numeric($this->teamId)) {
            return (int) $this->teamId;
        }

        $scopes = $this->basicInfo()['scopes'] ?? null;
        if (! is_array($scopes)) {
            return null;
        }
        foreach (['logs', 'team_computers', 'team_users'] as $scope) {
            $teamId = is_array($scopes[$scope] ?? null) ? ($scopes[$scope]['stb_team_id'] ?? $scopes[$scope]['team_id'] ?? null) : null;
            if (is_numeric($teamId)) {
                return (int) $teamId;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function basicInfo(): array {
        $response = $this->api()->getResponse($this->baseUrl() . '/api/open/v1/users/basic_info', [], ['timeout' => 10]);

        return $this->data($response, 'Kontoinformationen');
    }

    /**
     * Antworthülle: `result` 200 oder 20200 ist Erfolg, `messages` nennt den Grund.
     *
     * @return array<string, mixed>
     */
    private function data(Response $response, string $what): array {
        $result = $response->json('result');
        if (! $response->successful() || ! in_array((int) $result, [200, 20200], true)) {
            $messages = $response->json('messages');
            $reason = is_array($messages) && $messages !== [] ? implode(' ', array_map('strval', $messages)) : ($response->json('data.error') ?? '');
            throw RemoteProviderException::for(self::ID, sprintf('%s abgelehnt (HTTP %d, result %s)%s', $what, $response->status(), (string) ($result ?? '-'), is_string($reason) && $reason !== '' ? ': ' . $reason : '.'));
        }
        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    /** @return list<string> */
    private function serviceKindList(): array {
        $kinds = array_values(array_filter(array_map('trim', explode(',', $this->serviceKinds)), static fn (string $k): bool => $k !== ''));

        return $kinds !== [] ? array_values(array_unique($kinds)) : explode(',', self::DEFAULT_SERVICE_KINDS);
    }

    public function baseUrl(): string {
        return self::REGIONS[$this->region] ?? self::REGIONS[self::DEFAULT_REGION];
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(RemoteSupportPlugin::ID, $this->baseUrl());
            $this->api->setAuthentication(new BearerAuthentication((string) $this->apiToken));
        }

        return $this->api;
    }
}
