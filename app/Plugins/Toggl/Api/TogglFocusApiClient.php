<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglFocusApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl\Api;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory, StartStopFingerprint};
use App\Plugins\Toggl\Enums\TogglSource;
use App\Plugins\Toggl\Exceptions\TogglApiException;
use App\Plugins\Toggl\Sources\TogglEntry;
use App\Plugins\Toggl\TogglConfig;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/**
 * Client für das neue Toggl (vormals Toggl 2.0 / Toggl Focus, Feature 178).
 *
 * Anmeldung per Bearer-Schlüssel (`toggl_sk_…`). Fast alle Pfade tragen
 * Organisation und Workspace; welche Workspaces ein Schlüssel sieht, kennt nur
 * der Kontodienst. Zeiten aller Mitglieder kommen aus der Report-Abfrage —
 * die Eintragsliste liefert nur die eigenen. Webhooks gibt es nicht.
 */
class TogglFocusApiClient implements TogglApi {
    use StartStopFingerprint;

    private const REPORT_PAGE_SIZE = 500;

    /** Höchstwert der Listen-Endpunkte laut Referenz. */
    private const LIST_PAGE_SIZE = 100;

    /** Spalten je Report-Zeile; Namen und Tags kommen mit, Stammdaten-Abrufe entfallen. */
    private const REPORT_ATTRIBUTES = [
        'time_entry_id', 'description', 'start_utc', 'duration', 'effective_billable',
        'user_account_id', 'project_id', 'project_name', 'client_id', 'client_name', 'tag_names',
    ];

    private ?PluginApiClient $api = null;

    private ?PluginApiClient $accounts = null;

    private bool $fetchComplete = true;

    /** @var array<int, array{id: int, name: string, organization_id: int}>|null */
    private ?array $workspaceIndex = null;

    /** @var array<int, array<string, int>> Workspace → Tag-Name (klein) → ID */
    private array $tagIds = [];

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = TogglConfig::DEFAULT_FOCUS_BASE_URL,
        private readonly string $accountsUrl = TogglConfig::DEFAULT_ACCOUNTS_URL,
        private readonly ?int $organizationId = null,
        private readonly ?int $workspaceId = null,
        private readonly float $requestInterval = 0.0,
    ) {}

    public function source(): TogglSource {
        return TogglSource::Focus;
    }

    public function isFetchComplete(): bool {
        return $this->fetchComplete;
    }

    public function isConfigured(): bool {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    public function probe(): void {
        $this->api()->getResponse($this->baseUrl . '/users/me/settings', [], ['timeout' => 5])->throw();
    }

    /** Die Report-Abfrage enthält die eigenen Einträge bereits; ein zweiter Abruf kostete nur Kontingent. */
    public function fetchEntries(CarbonImmutable $from, CarbonImmutable $to): array {
        return [];
    }

    public function fetchEntry(int $id, ?int $workspaceId = null): array {
        $url = $this->entryUrl((string) $id, ['workspace_id' => $workspaceId]);
        if ($url === null) {
            return ['status' => 'error', 'entry' => null];
        }

        $response = $this->api()->getResponse($url, [], ['timeout' => 15]);
        if ($response->status() === 404) {
            return ['status' => 'missing', 'entry' => null];
        }
        if (! $response->successful()) {
            return ['status' => 'error', 'entry' => null];
        }

        $state = $this->stateOf($response->json());
        if ($state === null) {
            return ['status' => 'missing', 'entry' => null];
        }

        return ['status' => 'ok', 'entry' => [
            'started_at' => $state['started_at']?->toIso8601String(),
            'ended_at' => $state['ended_at']?->toIso8601String(),
            'minutes' => $state['minutes'],
            'description' => (string) $state['description'],
            'billable' => $state['billable'],
        ]];
    }

    public function workspaces(): array {
        return array_values(array_map(
            static fn (array $ws): array => ['id' => $ws['id'], 'name' => $ws['name']],
            $this->workspaceIndex(),
        ));
    }

    public function workspaceClients(int $workspaceId): array {
        $out = [];
        foreach ($this->pages($this->baseUrl . '/workspaces/' . $workspaceId . '/clients', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || ! is_numeric($row['id'] ?? null)) {
                continue;
            }
            $out[] = ['id' => (int) $row['id'], 'name' => $name, 'archived' => ! (bool) ($row['active'] ?? true)];
        }

        return $out;
    }

    public function workspaceProjects(int $workspaceId): array {
        $organizationId = $this->organizationFor($workspaceId);
        if ($organizationId === null) {
            return [];
        }

        $url = $this->baseUrl . '/organizations/' . $organizationId . '/workspaces/' . $workspaceId . '/projects';
        $out = [];
        // Ohne Filter liefert die Liste nur aktive Projekte; ältere Einträge hängen auch an archivierten.
        foreach (['false', 'true'] as $archived) {
            foreach ($this->pages($url, ['archived' => $archived]) as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '' || ! is_numeric($row['id'] ?? null)) {
                    continue;
                }
                $client = is_array($row['client'] ?? null) ? $row['client'] : [];
                $clientId = $row['client_id'] ?? ($client['id'] ?? null);
                $clientName = trim((string) ($client['name'] ?? ''));
                $color = trim((string) ($row['color'] ?? ''));
                $out[] = [
                    'id' => (int) $row['id'],
                    'name' => $name,
                    'client_id' => is_numeric($clientId) ? (int) $clientId : null,
                    'client_name' => $clientName !== '' ? $clientName : null,
                    'color' => $color !== '' ? $color : null,
                    'billable' => (bool) ($row['billable'] ?? false),
                    'active' => $archived === 'false',
                    'start_date' => isset($row['start_date']) && is_string($row['start_date']) ? substr($row['start_date'], 0, 10) : null,
                ];
            }
        }

        return $out;
    }

    /** `id` ist die Konto-ID, unter der die Report-Zeilen den Benutzer nennen. */
    public function workspaceUsers(int $workspaceId): array {
        $organizationId = $this->organizationFor($workspaceId);
        if ($organizationId === null) {
            $this->fetchComplete = false;

            return [];
        }

        $out = [];
        $page = 1;
        do {
            $response = $this->api()->getResponse($this->baseUrl . '/organizations/' . $organizationId . '/users', [
                'workspaces' => $workspaceId,
                'page' => $page,
                'per_page' => self::LIST_PAGE_SIZE,
            ], ['timeout' => 20]);
            if (! $response->successful()) {
                // Ohne Benutzerliste fehlt den Report-Zeilen die E-Mail.
                $this->fetchComplete = false;

                break;
            }

            $rows = $this->rows($response);
            foreach ($rows as $row) {
                $email = trim((string) ($row['email'] ?? ''));
                if ($email === '') {
                    continue;
                }
                $out[] = [
                    'id' => is_numeric($row['user_account_id'] ?? null) ? (int) $row['user_account_id'] : null,
                    'email' => $email,
                    'name' => trim((string) ($row['name'] ?? $email)),
                    'timezone' => null,
                ];
            }
            $page++;
        } while (count($rows) >= self::LIST_PAGE_SIZE);

        return $out;
    }

    public function workspaceEntries(int $workspaceId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array {
        if (! $this->isConfigured()) {
            return [];
        }

        $emailsByAccount = [];
        foreach ($this->workspaceUsers($workspaceId) as $user) {
            if ($user['id'] !== null) {
                $emailsByAccount[$user['id']] = $user['email'];
            }
        }

        $body = ['attributes' => array_map(static fn (string $p): array => ['property' => $p], self::REPORT_ATTRIBUTES)];
        if ($from !== null || $to !== null) {
            $body['period'] = [
                'from' => ($from ?? CarbonImmutable::createFromTimestampUTC(0))->toDateString(),
                'to' => ($to ?? CarbonImmutable::now())->toDateString(),
            ];
        }

        $entries = [];
        $fetched = 0;
        $page = 1;
        do {
            $body['pagination'] = ['page' => $page, 'per_page' => self::REPORT_PAGE_SIZE];
            $response = $this->api()->postJson(
                $this->baseUrl . '/reports/workspaces/' . $workspaceId . '/query?response_format=json_row',
                $body,
                ['timeout' => 60],
            );
            if (! $response->successful()) {
                $this->fetchComplete = false;

                break;
            }

            $rows = (array) ($response->json('data_json_row') ?? []);
            foreach ($rows as $row) {
                $entry = is_array($row) ? $this->reportRowToEntry($row, $workspaceId, $emailsByAccount) : null;
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }

            $fetched += count($rows);
            $total = $response->json('total_count');
            $page++;
            // Die Gesamtzahl entscheidet; kappt der Server die Seitengröße, endete „weniger als angefragt“ zu früh.
            $more = is_numeric($total) ? $fetched < (int) $total && $rows !== [] : count($rows) >= self::REPORT_PAGE_SIZE;
        } while ($more);

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $emailsByAccount
     */
    private function reportRowToEntry(array $row, int $workspaceId, array $emailsByAccount): ?TogglEntry {
        $id = $row['time_entry_id'] ?? null;
        $start = $row['start_utc'] ?? null;
        $duration = $row['duration'] ?? null;
        // Laufende Timer haben noch keine Dauer.
        if (! is_numeric($id) || ! is_string($start) || $start === '' || ! is_numeric($duration) || (int) $duration <= 0) {
            return null;
        }

        $startedAt = CarbonImmutable::parse($start, 'UTC');
        $projectId = is_numeric($row['project_id'] ?? null) ? (int) $row['project_id'] : null;
        $clientId = is_numeric($row['client_id'] ?? null) ? (int) $row['client_id'] : null;
        $account = is_numeric($row['user_account_id'] ?? null) ? (int) $row['user_account_id'] : null;

        return new TogglEntry(
            source: TogglEntry::SOURCE_API,
            entryKey: TogglSource::Focus->entryKey((int) $id),
            clientName: self::nonEmpty($row['client_name'] ?? null),
            projectName: self::nonEmpty($row['project_name'] ?? null),
            description: self::nonEmpty($row['description'] ?? null),
            startedAt: $startedAt,
            endedAt: $startedAt->addSeconds((int) $duration),
            billable: (bool) ($row['effective_billable'] ?? false),
            userEmail: $account !== null ? ($emailsByAccount[$account] ?? null) : null,
            tags: array_values(array_filter(array_map(
                static fn ($tag): string => trim((string) $tag),
                is_array($row['tag_names'] ?? null) ? $row['tag_names'] : [],
            ), static fn (string $tag): bool => $tag !== '')),
            clientId: $clientId,
            projectId: $projectId,
            workspaceId: $workspaceId,
        );
    }

    public function fetchRemoteState(string $externalId, array $context): ?array {
        $url = $this->entryUrl($externalId, $context);
        if ($url === null) {
            return null;
        }

        $response = $this->api()->getResponse($url, [], ['timeout' => 20]);

        return $response->successful() ? $this->stateOf($response->json()) : null;
    }

    public function pushEntryUpdate(string $externalId, array $entry, array $context): bool {
        $url = $this->entryUrl($externalId, $context);
        if ($url === null) {
            return false;
        }

        $changes = ['description' => (string) $entry['description'], 'billable' => $entry['billable']];
        if ($entry['started_at'] !== null && $entry['ended_at'] !== null) {
            $changes['start'] = $entry['started_at']->utc()->toIso8601String();
            $changes['duration'] = (int) $entry['started_at']->diffInSeconds($entry['ended_at'], absolute: true);
        }
        if (isset($entry['project_id'])) {
            $changes['project_id'] = (int) $entry['project_id'];
        }
        // Eine leere Liste setzte die Tags auf „keine“ und kappte die von der Aufgabe geerbten.
        $workspaceId = $this->workspaceOf($context);
        if (($entry['tags'] ?? []) !== [] && $workspaceId !== null) {
            $changes['tag_ids'] = $this->tagIdsFor($workspaceId, $entry['tags']);
        }

        return $this->api()->requestResponse('patch', $url, ['json' => $changes, 'timeout' => 20])->successful();
    }

    public function pushEntryDeletion(string $externalId, array $context): bool {
        $url = $this->entryUrl($externalId, $context);
        if ($url === null) {
            return false;
        }

        $response = $this->api()->deleteResponse($url, ['timeout' => 20]);

        return $response->successful() || $response->status() === 404;
    }

    public function createTimeEntry(
        int $workspaceId,
        CarbonImmutable $start,
        CarbonImmutable $stop,
        string $description,
        ?int $projectId,
        bool $billable,
        array $tags = [],
    ): array {
        $organizationId = $this->organizationFor($workspaceId);
        if ($organizationId === null) {
            throw new TogglApiException('Toggl-Organisation zum Workspace ' . $workspaceId . ' nicht ermittelbar');
        }

        $payload = [
            'description' => $description,
            'start' => $start->utc()->toIso8601String(),
            'duration' => (int) $start->diffInSeconds($stop, absolute: true),
            'type' => 'activity',
        ];
        if ($billable) {
            // Wie bei Track: Tarife ohne Abrechenbarkeit kennen das Feld nicht.
            $payload['billable'] = true;
        }
        if ($projectId !== null) {
            $payload['project_id'] = $projectId;
        }
        if ($tags !== []) {
            $payload['tag_ids'] = $this->tagIdsFor($workspaceId, $tags);
        }

        $response = $this->api()->postJson(
            $this->baseUrl . '/organizations/' . $organizationId . '/workspaces/' . $workspaceId . '/time-entries',
            $payload,
            ['timeout' => 20],
        );
        if (! $response->successful()) {
            throw new TogglApiException('Toggl-Anlage fehlgeschlagen (HTTP ' . $response->status() . ')', $response->status());
        }

        $record = (array) $response->json();
        // Track-Form für den Aufrufer: das neue Toggl kennt kein Ende, nur Start und Dauer.
        $state = $this->stateOf($record);
        $record['stop'] = $state !== null ? $state['ended_at']?->toIso8601String() : null;

        return $record;
    }

    /**
     * Fremdstand in der Form des lokalen Stands — `null`, wenn der Eintrag
     * nur geplant ist oder der Timer läuft.
     *
     * @return array{description: ?string, date: ?CarbonImmutable, started_at: ?CarbonImmutable, ended_at: ?CarbonImmutable, minutes: int, billable: bool}|null
     */
    private function stateOf(mixed $record): ?array {
        if (! is_array($record) || ! is_string($record['start'] ?? null) || ! is_numeric($record['duration'] ?? null)) {
            return null;
        }

        $start = CarbonImmutable::parse($record['start']);
        $stop = $start->addSeconds((int) $record['duration']);

        return [
            'description' => isset($record['description']) ? (string) $record['description'] : null,
            'date' => $start,
            'started_at' => $start,
            'ended_at' => $stop,
            'minutes' => (int) round(((int) $record['duration']) / 60),
            'billable' => (bool) ($record['billable'] ?? false),
        ];
    }

    /**
     * Tag-IDs zu Namen; fehlende Tags werden angelegt, wie Track es beim
     * Schreiben über Namen selbst tut.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    private function tagIdsFor(int $workspaceId, array $names): array {
        if (! isset($this->tagIds[$workspaceId])) {
            $this->tagIds[$workspaceId] = [];
            foreach ($this->pages($this->baseUrl . '/workspaces/' . $workspaceId . '/tags', []) as $row) {
                if (is_numeric($row['id'] ?? null) && trim((string) ($row['name'] ?? '')) !== '') {
                    $this->tagIds[$workspaceId][mb_strtolower(trim((string) $row['name']))] = (int) $row['id'];
                }
            }
        }

        $ids = [];
        foreach ($names as $name) {
            $key = mb_strtolower(trim($name));
            if ($key === '') {
                continue;
            }
            if (! isset($this->tagIds[$workspaceId][$key])) {
                $response = $this->api()->postJson($this->baseUrl . '/workspaces/' . $workspaceId . '/tags', ['name' => trim($name)], ['timeout' => 20]);
                if (! $response->successful() || ! is_numeric($response->json('id'))) {
                    continue;
                }
                $this->tagIds[$workspaceId][$key] = (int) $response->json('id');
            }
            $ids[] = $this->tagIds[$workspaceId][$key];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Alle Zeilen einer seitenweisen Liste (`data` + `page`/`per_page`).
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function pages(string $url, array $query): array {
        $out = [];
        $page = 1;
        do {
            $response = $this->api()->getResponse($url, $query + ['page' => $page, 'per_page' => self::LIST_PAGE_SIZE], ['timeout' => 20]);
            if (! $response->successful()) {
                break;
            }
            $rows = $this->rows($response);
            array_push($out, ...$rows);
            $total = $response->json('total');
            $page++;
            $more = is_numeric($total) ? count($out) < (int) $total && $rows !== [] : count($rows) >= self::LIST_PAGE_SIZE;
        } while ($more);

        return $out;
    }

    /**
     * Zeilen einer Antwort — als Seite (`data`) oder als nackte Liste.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(Response $response): array {
        $json = $response->json();
        $rows = is_array($json) && array_key_exists('data', $json) ? $json['data'] : $json;

        return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
    }

    /**
     * Workspaces des neuen Toggl mit ihrer Organisation. Mit beiden IDs in den
     * Einstellungen ohne Abruf; sonst über den Kontodienst, gefiltert auf das,
     * was eingetragen ist.
     *
     * @return array<int, array{id: int, name: string, organization_id: int}>
     */
    private function workspaceIndex(): array {
        if ($this->workspaceIndex !== null) {
            return $this->workspaceIndex;
        }
        if (! $this->isConfigured()) {
            return $this->workspaceIndex = [];
        }

        if ($this->organizationId !== null && $this->workspaceId !== null) {
            return $this->workspaceIndex = [$this->workspaceId => [
                'id' => $this->workspaceId,
                'name' => 'Workspace ' . $this->workspaceId,
                'organization_id' => $this->organizationId,
            ]];
        }

        $response = $this->accounts()->getResponse(rtrim($this->accountsUrl, '/') . '/org/api/organizations/me', [], ['timeout' => 20]);
        if (! $response->successful()) {
            $this->fetchComplete = false;

            return $this->workspaceIndex = [];
        }

        $index = [];
        foreach ((array) ($response->json() ?? []) as $organization) {
            if (! is_array($organization) || ! is_numeric($organization['id'] ?? null)) {
                continue;
            }
            if ($this->organizationId !== null && (int) $organization['id'] !== $this->organizationId) {
                continue;
            }
            foreach ((array) ($organization['workspaces'] ?? []) as $workspace) {
                if (! is_array($workspace) || ! is_numeric($workspace['id'] ?? null)
                    || ! in_array('focus', (array) ($workspace['toggl_products'] ?? []), true)) {
                    continue;
                }
                $id = (int) $workspace['id'];
                if ($this->workspaceId !== null && $id !== $this->workspaceId) {
                    continue;
                }
                $index[$id] = [
                    'id' => $id,
                    'name' => trim((string) ($workspace['name'] ?? '')) ?: 'Workspace ' . $id,
                    'organization_id' => (int) $organization['id'],
                ];
            }
        }

        return $this->workspaceIndex = $index;
    }

    private function organizationFor(int $workspaceId): ?int {
        if ($this->organizationId !== null) {
            return $this->organizationId;
        }

        return $this->workspaceIndex()[$workspaceId]['organization_id'] ?? null;
    }

    /** @param array<string, mixed> $context */
    private function workspaceOf(array $context): ?int {
        $workspaceId = is_numeric($context['workspace_id'] ?? null) ? (int) $context['workspace_id'] : $this->workspaceId;

        return $workspaceId !== null && $workspaceId > 0 ? $workspaceId : null;
    }

    /**
     * Der Workspace steht im Referenz-Payload; der eingestellte ist der Rückfall.
     *
     * @param  array<string, mixed>  $context
     */
    private function entryUrl(string $externalId, array $context): ?string {
        $workspaceId = $this->workspaceOf($context);
        if (! $this->isConfigured() || $workspaceId === null || ! is_numeric($externalId)) {
            return null;
        }
        $organizationId = $this->organizationFor($workspaceId);
        if ($organizationId === null) {
            return null;
        }

        return $this->baseUrl . '/organizations/' . $organizationId . '/workspaces/' . $workspaceId . '/time-entries/' . (int) $externalId;
    }

    private static function nonEmpty(mixed $value): ?string {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('toggl', $this->baseUrl, $this->requestInterval);
            $this->api->setAuthentication(new BearerAuthentication((string) $this->apiKey));
        }

        return $this->api;
    }

    private function accounts(): PluginApiClient {
        if ($this->accounts === null) {
            $this->accounts = app(PluginHttpFactory::class)->client('toggl', $this->accountsUrl);
            $this->accounts->setAuthentication(new BearerAuthentication((string) $this->apiKey));
        }

        return $this->accounts;
    }
}
