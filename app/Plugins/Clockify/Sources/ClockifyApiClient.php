<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Clockify\Sources;

use APIToolkit\API\Authentication\ApiKeyAuthentication;
use App\Plugins\Clockify\Exceptions\ClockifyApiException;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory, RemoteTimeWriter, StartStopFingerprint};
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/**
 * Clockify-API-Client auf dem `php-api-toolkit`-Fundament
 * ({@see PluginApiClient}): `X-Api-Key`-Auth, Benutzer/Workspace über die
 * Basis-API (`/v1/user`), Zeiteinträge aller Benutzer über die Reports-API
 * (`POST …/reports/detailed`, `pageSize` ≤ 1000, Antwortfeld `timeentries`,
 * `timeZone` immer mitsenden → Zeiten kommen in UTC). Free-Plan: 30
 * API-Requests/h — 429 wird mit CSV-Hinweis gemeldet. Tests ersetzen die
 * {@see PluginHttpFactory} durch {@see \Tests\Support\FakePluginHttp}.
 */
class ClockifyApiClient implements RemoteTimeWriter {
    use StartStopFingerprint;

    public const PAGE_SIZE = 1000;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly string $reportsBaseUrl,
        private readonly ?string $workspaceId = null,
    ) {}

    public function isConfigured(): bool {
        return $this->apiKey !== null && trim($this->apiKey) !== '';
    }

    /**
     * Eingeloggter API-Benutzer (Health-Check + Standard-Workspace).
     *
     * @return array<string, mixed>
     */
    public function currentUser(): array {
        $response = $this->api()->getResponse($this->url($this->baseUrl, '/v1/user'));
        $this->assertOk($response, '/v1/user');

        /** @var array<string, mixed> */
        return (array) $response->json();
    }

    /** Konfigurierter Workspace oder Standard-Workspace des API-Keys. */
    public function resolveWorkspaceId(): string {
        if ($this->workspaceId !== null && trim($this->workspaceId) !== '') {
            return trim($this->workspaceId);
        }

        $user = $this->currentUser();
        $workspace = $user['defaultWorkspace'] ?? ($user['activeWorkspace'] ?? null);
        if (! is_string($workspace) || $workspace === '') {
            throw new ClockifyApiException((string) __('Clockify: kein Workspace ermittelbar — Workspace-ID in den Plugin-Einstellungen hinterlegen.'));
        }

        return $workspace;
    }

    /**
     * Detailed-Report seitenweise vollständig: Zeiteinträge aller Benutzer im
     * Fenster (UTC).
     *
     * @return list<array<string, mixed>>
     */
    public function getTimeEntries(CarbonImmutable $from, CarbonImmutable $to): array {
        $workspaceId = $this->resolveWorkspaceId();

        $rows = [];
        $page = 1;

        do {
            $response = $this->api()->postJson(
                $this->url($this->reportsBaseUrl, '/workspaces/' . $workspaceId . '/reports/detailed'),
                [
                    'dateRangeStart' => $from->utc()->format('Y-m-d\TH:i:s.v\Z'),
                    'dateRangeEnd' => $to->utc()->format('Y-m-d\TH:i:s.v\Z'),
                    'detailedFilter' => ['page' => $page, 'pageSize' => self::PAGE_SIZE],
                    'timeZone' => 'UTC',
                ],
            );
            $this->assertOk($response, '/reports/detailed');

            /** @var list<array<string, mixed>> $batch */
            $batch = array_values((array) $response->json('timeentries'));
            $rows = array_merge($rows, $batch);
            $page++;
        } while (count($batch) === self::PAGE_SIZE);

        return $rows;
    }

    /**
     * Aktueller Clockify-Stand (Rückrichtung).
     *
     * Clockify-Projekt-IDs sind Zeichenketten und gehen deshalb — wie beim
     * Import — nicht in den Fingerabdruck ein.
     *
     * @param  array<string, mixed>  $context
     * @return array{description: ?string, date: ?CarbonImmutable, started_at: ?CarbonImmutable, ended_at: ?CarbonImmutable, minutes: int, billable: bool}|null
     */
    public function fetchRemoteState(string $externalId, array $context): ?array {
        $row = $this->fetchEntry($externalId);
        if ($row === null) {
            return null;
        }

        $interval = is_array($row['timeInterval'] ?? null) ? $row['timeInterval'] : [];
        $start = $interval['start'] ?? null;
        $end = $interval['end'] ?? null;
        if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
            return null; // laufender Eintrag
        }

        $startedAt = CarbonImmutable::parse($start);
        $endedAt = CarbonImmutable::parse($end);

        return [
            'description' => isset($row['description']) ? (string) $row['description'] : null,
            'date' => $startedAt,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'minutes' => (int) round($startedAt->diffInSeconds($endedAt) / 60),
            'billable' => (bool) ($row['billable'] ?? false),
        ];
    }

    /**
     * Überträgt den lokalen Stand. Clockifys PUT ersetzt den Eintrag —
     * Projekt, Aufgabe und Tags müssen deshalb aus dem aktuellen Stand
     * mitgeschickt werden, sonst gehen sie verloren.
     *
     * @param  array{description: ?string, date: ?CarbonImmutable, started_at: ?CarbonImmutable, ended_at: ?CarbonImmutable, minutes: int, billable: bool}  $entry
     * @param  array<string, mixed>  $context
     */
    public function pushEntryUpdate(string $externalId, array $entry, array $context): bool {
        if ($entry['started_at'] === null || $entry['ended_at'] === null) {
            return false;
        }

        $current = $this->fetchEntry($externalId);
        if ($current === null) {
            return false;
        }

        $tagIds = [];
        foreach ((array) ($current['tagIds'] ?? []) as $tagId) {
            $tagIds[] = (string) $tagId;
        }

        $response = $this->api()->putJson($this->entryUrl($externalId), array_filter([
            'start' => $entry['started_at']->utc()->format('Y-m-d\TH:i:s\Z'),
            'end' => $entry['ended_at']->utc()->format('Y-m-d\TH:i:s\Z'),
            'billable' => $entry['billable'],
            'description' => (string) $entry['description'],
            'projectId' => isset($current['projectId']) ? (string) $current['projectId'] : null,
            'taskId' => isset($current['taskId']) ? (string) $current['taskId'] : null,
            'tagIds' => $tagIds,
        ], static fn ($v): bool => $v !== null));

        return $response->successful();
    }

    /**
     * Löscht den Zeiteintrag; ein bereits gelöschter (404) gilt als erledigt.
     *
     * @param  array<string, mixed>  $context
     */
    public function pushEntryDeletion(string $externalId, array $context): bool {
        $response = $this->api()->deleteResponse($this->entryUrl($externalId));

        return $response->successful() || $response->status() === 404;
    }

    /**
     * Einzelner Zeiteintrag; null wenn nicht erreichbar oder gelöscht.
     *
     * @return array<string, mixed>|null
     */
    private function fetchEntry(string $externalId): ?array {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = $this->api()->getResponse($this->entryUrl($externalId));
        if (! $response->successful()) {
            return null;
        }

        $row = $response->json();

        return is_array($row) ? $row : null;
    }

    private function entryUrl(string $externalId): string {
        return $this->url($this->baseUrl, '/v1/workspaces/' . $this->resolveWorkspaceId() . '/time-entries/' . $externalId);
    }

    private function url(string $base, string $path): string {
        return rtrim($base, '/') . $path;
    }

    private function assertOk(Response $response, string $context): void {
        if ($response->successful()) {
            return;
        }

        $detail = (string) ($response->json('message') ?? $response->body());
        $message = sprintf('Clockify-API %s: HTTP %d — %s', $context, $response->status(), mb_substr($detail, 0, 300));
        if ($response->status() === 429) {
            $message .= ' ' . __('(Free-Plan: 30 Requests/h — für Free-Konten den CSV-Weg nutzen.)');
        }

        throw new ClockifyApiException($message, $response->status());
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('clockify', rtrim($this->baseUrl, '/'));
            $this->api->setAuthentication(new ApiKeyAuthentication((string) $this->apiKey, 'X-Api-Key'));
        }

        return $this->api;
    }
}
