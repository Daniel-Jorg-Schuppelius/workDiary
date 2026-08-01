<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglApiClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Sources;

use APIToolkit\API\Authentication\BasicAuthentication;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory, RemoteTimeWriter, StartStopFingerprint};
use App\Plugins\Toggl\Exceptions\TogglApiException;
use Carbon\CarbonImmutable;

/**
 * Client für die Toggl Track API v9 (https://api.track.toggl.com/api/v9).
 *
 * Authentifizierung per HTTP-Basic: API-Token als Benutzername, das Literal
 * "api_token" als Passwort. {@see fetchEntries()} lädt zunächst die Stammdaten
 * (`/me?with_related_data=true`), um Projekt-/Client-Namen auflösen zu können,
 * und anschließend die Zeiteinträge im Fenster [$from, $to].
 *
 * Für den vollständigen Workspace-Import liefern {@see workspaces()} und die
 * `workspace*`-Methoden die Stammdaten je Workspace; {@see workspaceEntries()}
 * holt alle Zeiteinträge (aller Benutzer) über die Reports-API v3.
 */
class TogglApiClient implements RemoteTimeWriter {
    use StartStopFingerprint;

    /** Maximale Historie (Jahre) beim „alles"-Import, falls kein Floor bestimmbar ist. */
    public const MAX_HISTORY_YEARS = 12;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $apiToken,
        private readonly string $baseUrl = 'https://api.track.toggl.com/api/v9',
        /** Optionaler Workspace-Filter; null = alle Workspaces des Tokens. */
        private readonly ?int $workspaceId = null,
    ) {}

    public function isConfigured(): bool {
        return $this->apiToken !== null && $this->apiToken !== '';
    }

    /** Health-Ping: /me liefert bei gültigem Token 200. */
    public function ping(): bool {
        if (! $this->isConfigured()) {
            return false;
        }

        return $this->api()
            ->getResponse($this->baseUrl . '/me', [], ['timeout' => 5])
            ->successful();
    }

    /**
     * Zeiteinträge im Fenster [$from, $to], angereichert um Client-/Projekt-Namen.
     *
     * @return array<int, TogglEntry>
     */
    public function fetchEntries(CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->isConfigured()) {
            return [];
        }

        [$projects, $clients, $email] = $this->fetchRelatedData();

        $response = $this->api()
            ->getResponse($this->baseUrl . '/me/time_entries', [
                'start_date' => $from->toIso8601String(),
                'end_date' => $to->toIso8601String(),
            ], ['timeout' => 20]);

        if (! $response->successful()) {
            return [];
        }

        $entries = [];
        foreach ((array) ($response->json() ?? []) as $record) {
            $entry = $this->mapRecord((array) $record, $projects, $clients, $email);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Lädt Projekte + Clients über `/me?with_related_data=true`.
     *
     * @return array{0: array<int, array{name: string, client_id: ?int}>, 1: array<int, string>, 2: ?string}
     */
    private function fetchRelatedData(): array {
        $response = $this->api()
            ->getResponse($this->baseUrl . '/me', ['with_related_data' => 'true'], ['timeout' => 20]);

        if (! $response->successful()) {
            return [[], [], null];
        }

        $clients = [];
        foreach ((array) ($response->json('clients') ?? []) as $client) {
            $id = $client['id'] ?? null;
            if ($id !== null) {
                $clients[(int) $id] = (string) ($client['name'] ?? '');
            }
        }

        $projects = [];
        foreach ((array) ($response->json('projects') ?? []) as $project) {
            $id = $project['id'] ?? null;
            if ($id === null) {
                continue;
            }
            if ($this->workspaceId !== null && (int) ($project['workspace_id'] ?? 0) !== $this->workspaceId) {
                continue;
            }
            $projects[(int) $id] = [
                'name' => (string) ($project['name'] ?? ''),
                'client_id' => isset($project['client_id']) ? (int) $project['client_id'] : null,
            ];
        }

        $email = $response->json('email');

        return [$projects, $clients, is_string($email) ? $email : null];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, array{name: string, client_id: ?int}>  $projects
     * @param  array<int, string>  $clients
     */
    private function mapRecord(array $record, array $projects, array $clients, ?string $email): ?TogglEntry {
        $start = $record['start'] ?? null;
        $stop = $record['stop'] ?? null;
        // Laufende Einträge (kein stop) und solche außerhalb des Workspaces überspringen.
        if (! is_string($start) || ! is_string($stop) || $start === '' || $stop === '') {
            return null;
        }
        if ($this->workspaceId !== null && (int) ($record['workspace_id'] ?? 0) !== $this->workspaceId) {
            return null;
        }

        $projectId = isset($record['project_id']) ? (int) $record['project_id'] : null;
        $project = $projectId !== null ? ($projects[$projectId] ?? null) : null;
        $clientId = $project['client_id'] ?? null;

        $id = $record['id'] ?? null;

        return new TogglEntry(
            source: TogglEntry::SOURCE_API,
            entryKey: 'toggl:' . ($id ?? ($start . '|' . $stop)),
            clientName: $clientId !== null ? ($clients[$clientId] ?? null) : null,
            projectName: $project['name'] ?? null,
            description: isset($record['description']) ? trim((string) $record['description']) : null,
            startedAt: CarbonImmutable::parse($start),
            endedAt: CarbonImmutable::parse($stop),
            billable: (bool) ($record['billable'] ?? false),
            userEmail: $email,
            tags: self::tagNames($record['tags'] ?? null),
            clientId: $clientId !== null ? (int) $clientId : null,
            projectId: $projectId,
            workspaceId: isset($record['workspace_id']) ? (int) $record['workspace_id'] : null,
        );
    }

    /**
     * Normalisiert das tags-Feld eines v9-Datensatzes (Tag-Namen).
     *
     * @return list<string>
     */
    private static function tagNames(mixed $tags): array {
        if (! is_array($tags)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($tag): string => trim((string) $tag),
            $tags,
        ), static fn (string $tag): bool => $tag !== ''));
    }

    /**
     * Alle Workspaces, auf die das Token Zugriff hat.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function workspaces(): array {
        if (! $this->isConfigured()) {
            return [];
        }

        $response = $this->api()->getResponse($this->baseUrl . '/workspaces', [], ['timeout' => 20]);
        if (! $response->successful()) {
            return [];
        }

        $out = [];
        foreach ((array) ($response->json() ?? []) as $row) {
            $id = $row['id'] ?? null;
            if ($id === null) {
                continue;
            }
            if ($this->workspaceId !== null && (int) $id !== $this->workspaceId) {
                continue;
            }
            $out[] = ['id' => (int) $id, 'name' => trim((string) ($row['name'] ?? ('Workspace ' . $id)))];
        }

        return $out;
    }

    /**
     * Toggl-Clients eines Workspaces (inkl. archivierter), Format wie der Ordner-Reader.
     *
     * @return array<int, array{id: int, name: string, archived: bool}>
     */
    public function workspaceClients(int $workspaceId): array {
        $response = $this->api()
            ->getResponse($this->baseUrl . '/workspaces/' . $workspaceId . '/clients', ['status' => 'both'], ['timeout' => 20]);

        if (! $response->successful()) {
            return [];
        }

        $out = [];
        foreach ((array) ($response->json() ?? []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $archived = ($row['archived'] ?? false) || (($row['status'] ?? null) === 'archived');
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => $name,
                'archived' => (bool) $archived,
            ];
        }

        return $out;
    }

    /**
     * Toggl-Projekte eines Workspaces (inkl. aufgelöstem Client-Namen), Format wie der Ordner-Reader.
     *
     * @return array<int, array{id: int, name: string, client_id: ?int, client_name: ?string, color: ?string, billable: bool, active: bool, start_date: ?string}>
     */
    public function workspaceProjects(int $workspaceId): array {
        $clientNames = [];
        foreach ($this->workspaceClients($workspaceId) as $client) {
            $clientNames[(int) $client['id']] = $client['name'];
        }

        $out = [];
        $page = 1;
        do {
            $response = $this->api()
                ->getResponse($this->baseUrl . '/workspaces/' . $workspaceId . '/projects', [
                    'active' => 'both',
                    'per_page' => 200,
                    'page' => $page,
                ], ['timeout' => 20]);

            if (! $response->successful()) {
                break;
            }

            $rows = (array) ($response->json() ?? []);
            foreach ($rows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $clientId = isset($row['client_id']) ? (int) $row['client_id'] : null;
                $out[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => $name,
                    'client_id' => $clientId,
                    'client_name' => $clientId !== null ? ($clientNames[$clientId] ?? null) : null,
                    'color' => isset($row['color']) && trim((string) $row['color']) !== '' ? trim((string) $row['color']) : null,
                    'billable' => (bool) ($row['billable'] ?? false),
                    'active' => (bool) ($row['active'] ?? true),
                    'start_date' => isset($row['start_date']) ? substr((string) $row['start_date'], 0, 10) : null,
                ];
            }

            $page++;
        } while (count($rows) >= 200);

        return $out;
    }

    /**
     * Workspace-Tags (ID → Name) — die Reports-API liefert nur tag_ids,
     * die Namen kommen aus dieser Liste.
     *
     * @return array<int, string>
     */
    public function workspaceTags(int $workspaceId): array {
        $response = $this->api()
            ->getResponse($this->baseUrl . '/workspaces/' . $workspaceId . '/tags', [], ['timeout' => 20]);

        if (! $response->successful()) {
            return [];
        }

        $out = [];
        foreach ((array) ($response->json() ?? []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $id = $row['id'] ?? null;
            if ($name === '' || $id === null) {
                continue;
            }
            $out[(int) $id] = $name;
        }

        return $out;
    }

    /**
     * Workspace-Benutzer, Format wie der Ordner-Reader.
     *
     * @return array<int, array{id: ?int, email: string, name: string, timezone: ?string}>
     */
    public function workspaceUsers(int $workspaceId): array {
        $response = $this->api()
            ->getResponse($this->baseUrl . '/workspaces/' . $workspaceId . '/users', [], ['timeout' => 20]);

        if (! $response->successful()) {
            return [];
        }

        $out = [];
        foreach ((array) ($response->json() ?? []) as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $out[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'email' => $email,
                'name' => trim((string) ($row['fullname'] ?? $row['name'] ?? $email)),
                'timezone' => isset($row['timezone']) && trim((string) $row['timezone']) !== '' ? trim((string) $row['timezone']) : null,
            ];
        }

        return $out;
    }

    /**
     * Alle Zeiteinträge eines Workspaces (aller Benutzer) über die Reports-API v3.
     *
     * Optionales Fenster [$from, $to]; ist beides null, wird die vollständige
     * Historie geholt (jahresweise Chunks rückwärts bis zum frühesten
     * Projekt-Start bzw. {@see MAX_HISTORY_YEARS}). Die Reports-API verlangt
     * einen Datumsbereich von höchstens einem Jahr je Anfrage.
     *
     * @return array<int, TogglEntry>
     */
    public function workspaceEntries(int $workspaceId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array {
        if (! $this->isConfigured()) {
            return [];
        }

        // Reports-Zeilen tragen nur user_id/username, keine E-Mail — die
        // Workspace-Benutzerliste liefert die Auflösung für die Zuordnung.
        $emailsByUserId = [];
        foreach ($this->workspaceUsers($workspaceId) as $user) {
            if ($user['id'] !== null) {
                $emailsByUserId[(int) $user['id']] = $user['email'];
            }
        }

        // Stammdaten für die Namensauflösung (Projekt/Client).
        $projects = [];
        $earliestStart = null;
        foreach ($this->workspaceProjects($workspaceId) as $project) {
            $projects[(int) $project['id']] = [
                'name' => $project['name'],
                'client_name' => $project['client_name'],
                'client_id' => $project['client_id'] ?? null,
            ];
            if ($project['start_date'] !== null) {
                $start = CarbonImmutable::parse($project['start_date']);
                if ($earliestStart === null || $start->lessThan($earliestStart)) {
                    $earliestStart = $start;
                }
            }
        }

        $ceil = $to ?? CarbonImmutable::now();
        $floor = $from ?? $earliestStart ?? $ceil->subYears(self::MAX_HISTORY_YEARS);
        if ($floor->greaterThan($ceil)) {
            return [];
        }

        // Reports-Zeilen tragen nur tag_ids — einmal je Workspace auflösen.
        $tagNamesById = $this->workspaceTags($workspaceId);

        $entries = [];
        $windowEnd = $ceil;
        while ($windowEnd->greaterThanOrEqualTo($floor)) {
            $windowStart = $windowEnd->subYear()->addDay();
            if ($windowStart->lessThan($floor)) {
                $windowStart = $floor;
            }

            foreach ($this->fetchReportWindow($workspaceId, $windowStart, $windowEnd) as $row) {
                foreach ($this->mapReportRow((array) $row, $projects, $workspaceId, $emailsByUserId, $tagNamesById) as $entry) {
                    $entries[] = $entry;
                }
            }

            $windowEnd = $windowStart->subDay();
        }

        return $entries;
    }

    /**
     * Holt eine Datumsspanne (≤ 1 Jahr) aus der Reports-API v3, paginiert über
     * den `first_row_number`/`X-Next-Row-Number`-Mechanismus.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchReportWindow(int $workspaceId, CarbonImmutable $start, CarbonImmutable $end): array {
        $url = $this->reportsBaseUrl() . '/workspace/' . $workspaceId . '/search/time_entries';
        $rows = [];
        $firstRow = null;

        do {
            $payload = [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'page_size' => 1000,
            ];
            if ($firstRow !== null) {
                $payload['first_row_number'] = $firstRow;
            }

            $response = $this->api()->postJson($url, $payload, ['timeout' => 60]);
            if (! $response->successful()) {
                break;
            }

            foreach ((array) ($response->json() ?? []) as $row) {
                $rows[] = $row;
            }

            $next = $response->header('X-Next-Row-Number');
            $firstRow = is_numeric($next) && (int) $next > 0 ? (int) $next : null;
        } while ($firstRow !== null);

        return $rows;
    }

    /**
     * Mappt eine Reports-v3-Zeile (mit eingebetteten `time_entries`) auf
     * {@see TogglEntry}-DTOs. Eine Report-Zeile kann mehrere Einzeleinträge
     * bündeln (z. B. gestoppte/fortgesetzte Timer); je Position entsteht ein DTO.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, array{name: string, client_name: ?string, client_id: ?int}>  $projects
     * @param  array<int, string>  $emailsByUserId  Toggl-User-ID → E-Mail
     * @param  array<int, string>  $tagNamesById  Toggl-Tag-ID → Name
     * @return array<int, TogglEntry>
     */
    private function mapReportRow(array $row, array $projects, ?int $workspaceId = null, array $emailsByUserId = [], array $tagNamesById = []): array {
        $items = $row['time_entries'] ?? null;
        if (! is_array($items) || $items === []) {
            return [];
        }

        $projectId = isset($row['project_id']) ? (int) $row['project_id'] : null;
        $project = $projectId !== null ? ($projects[$projectId] ?? null) : null;
        $clientName = $project['client_name'] ?? null;
        $projectName = $project['name'] ?? null;
        $clientId = $project['client_id'] ?? null;

        $description = isset($row['description']) ? trim((string) $row['description']) : null;
        $billable = (bool) ($row['billable'] ?? false);
        $userEmail = $this->reportUserEmail($row)
            ?? (isset($row['user_id']) ? ($emailsByUserId[(int) $row['user_id']] ?? null) : null);

        // Unbekannte tag_ids (z. B. inzwischen gelöschte Tags) still überspringen.
        $tags = [];
        foreach (is_array($row['tag_ids'] ?? null) ? $row['tag_ids'] : [] as $tagId) {
            if (is_numeric($tagId) && isset($tagNamesById[(int) $tagId])) {
                $tags[] = $tagNamesById[(int) $tagId];
            }
        }

        $entries = [];
        foreach ($items as $item) {
            $entry = $this->reportItemToEntry((array) $item, $clientName, $projectName, $description, $billable, $userEmail, $clientId, $projectId, $workspaceId, $tags);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Baut aus einer einzelnen Reports-Position ein {@see TogglEntry}.
     *
     * @param  array<string, mixed>  $item
     * @param  list<string>  $tags
     */
    private function reportItemToEntry(array $item, ?string $clientName, ?string $projectName, ?string $description, bool $billable, ?string $userEmail, ?int $clientId = null, ?int $projectId = null, ?int $workspaceId = null, array $tags = []): ?TogglEntry {
        $start = $item['start'] ?? null;
        $stop = $item['stop'] ?? null;
        if (! is_string($start) || $start === '') {
            return null;
        }
        if (! is_string($stop) || $stop === '') {
            // Laufende Einträge überspringen.
            return null;
        }

        $id = $item['id'] ?? null;

        return new TogglEntry(
            source: TogglEntry::SOURCE_API,
            entryKey: 'toggl:' . ($id ?? ($start . '|' . $stop)),
            clientName: $clientName,
            projectName: $projectName,
            description: $description,
            startedAt: CarbonImmutable::parse($start),
            endedAt: CarbonImmutable::parse($stop),
            billable: $billable,
            userEmail: $userEmail,
            tags: $tags,
            clientId: $clientId,
            projectId: $projectId,
            workspaceId: $workspaceId,
        );
    }

    /** @param array<string, mixed> $row */
    private function reportUserEmail(array $row): ?string {
        foreach (['user_email', 'email'] as $key) {
            if (isset($row[$key]) && is_string($row[$key]) && trim($row[$key]) !== '') {
                return trim($row[$key]);
            }
        }

        return null;
    }

    /** Reports-Basis-URL aus der v9-Basis ableiten (…/api/v9 → …/reports/api/v3). */
    private function reportsBaseUrl(): string {
        $base = rtrim($this->baseUrl, '/');
        $replaced = preg_replace('#/api/v9$#', '/reports/api/v3', $base);

        return $replaced ?? ($base . '/reports/api/v3');
    }

    /**
     * Einzelnen Zeiteintrag frisch aus Toggl holen — Grundlage der
     * Konflikterkennung vor dem Zurückschreiben.
     *
     * @param  array<string, mixed>  $context
     * @return array{description: ?string, date: ?CarbonImmutable, started_at: ?CarbonImmutable, ended_at: ?CarbonImmutable, minutes: int, billable: bool}|null
     */
    public function fetchRemoteState(string $externalId, array $context): ?array {
        $url = $this->entryUrl($externalId, $context);
        if ($url === null) {
            return null;
        }

        $response = $this->api()->getResponse($url, [], ['timeout' => 20]);
        if (! $response->successful()) {
            return null;
        }

        $record = $response->json();
        if (! is_array($record) || ! isset($record['start'], $record['stop'])) {
            return null;
        }

        $start = CarbonImmutable::parse((string) $record['start']);
        $stop = CarbonImmutable::parse((string) $record['stop']);

        return [
            'description' => isset($record['description']) ? (string) $record['description'] : null,
            'date' => $start,
            'started_at' => $start,
            'ended_at' => $stop,
            'minutes' => (int) round($start->diffInSeconds($stop) / 60),
            'billable' => (bool) ($record['billable'] ?? false),
        ];
    }

    /**
     * Zeiteintrag in Toggl aktualisieren (API v9). Tags und gemapptes
     * project_id werden mitgespiegelt, wenn der Dispatcher sie liefert (G3,
     * MVP-463) — Projekt-Umzüge kommen so in Toggl an.
     *
     * @param  array{description: ?string, date: ?CarbonImmutable, started_at: ?CarbonImmutable, ended_at: ?CarbonImmutable, minutes: int, billable: bool, tags?: list<string>, project_id?: int}  $entry
     * @param  array<string, mixed>  $context
     */
    public function pushEntryUpdate(string $externalId, array $entry, array $context): bool {
        $url = $this->entryUrl($externalId, $context);
        if ($url === null) {
            return false;
        }

        $changes = array_filter([
            'description' => (string) $entry['description'],
            'start' => $entry['started_at']?->utc()->toIso8601String(),
            'stop' => $entry['ended_at']?->utc()->toIso8601String(),
        ], static fn ($v): bool => $v !== null);
        $changes['billable'] = $entry['billable'];
        if (isset($entry['tags'])) {
            $changes['tags'] = $entry['tags'];
        }
        if (isset($entry['project_id'])) {
            $changes['project_id'] = (int) $entry['project_id'];
        }

        return $this->api()->putJson($url, $changes, ['timeout' => 20])->successful();
    }

    /**
     * Zeiteintrag in Toggl löschen (API v9). Ein bereits gelöschter Eintrag
     * (404) ist für uns erledigt.
     *
     * @param  array<string, mixed>  $context
     */
    public function pushEntryDeletion(string $externalId, array $context): bool {
        $url = $this->entryUrl($externalId, $context);
        if ($url === null) {
            return false;
        }

        $response = $this->api()->deleteResponse($url, ['timeout' => 20]);

        return $response->successful() || $response->status() === 404;
    }

    /**
     * Der Workspace steht im Referenz-Payload; der konfigurierte ist der
     * Rückfall für Referenzen aus früheren Importen.
     *
     * @param  array<string, mixed>  $context
     */
    private function entryUrl(string $externalId, array $context): ?string {
        $workspaceId = (int) ($context['workspace_id'] ?? $this->workspaceId ?? 0);
        if (! $this->isConfigured() || $workspaceId <= 0 || ! is_numeric($externalId)) {
            return null;
        }

        return $this->baseUrl . '/workspaces/' . $workspaceId . '/time_entries/' . (int) $externalId;
    }

    /**
     * Legt einen Zeiteintrag in Toggl an (v9) und liefert den Response-Datensatz —
     * Grundlage für die Referenzen + den Import-Fingerabdruck des Push-Exports.
     * Angelegt wird immer für den Token-Inhaber (v9-Limitierung).
     *
     * @param  list<string>  $tags
     * @return array<string, mixed>
     *
     * @throws TogglApiException bei Nicht-2xx (Status für 429-Abbruch)
     */
    public function createTimeEntry(
        int $workspaceId,
        CarbonImmutable $start,
        CarbonImmutable $stop,
        string $description,
        ?int $projectId,
        bool $billable,
        array $tags = [],
    ): array {
        $payload = [
            'created_with' => 'workDiary',
            'description' => $description,
            'start' => $start->utc()->toIso8601String(),
            'stop' => $stop->utc()->toIso8601String(),
            'workspace_id' => $workspaceId,
        ];
        if ($projectId !== null) {
            $payload['project_id'] = $projectId;
        }
        if ($billable) {
            // Free-Plan-Workspaces kennen das Flag nicht — false nie senden.
            $payload['billable'] = true;
        }
        if ($tags !== []) {
            $payload['tags'] = $tags;
        }

        $response = $this->api()->postJson(
            $this->baseUrl . '/workspaces/' . $workspaceId . '/time_entries',
            $payload,
            ['timeout' => 20],
        );
        if (! $response->successful()) {
            throw new TogglApiException(
                'Toggl-Anlage fehlgeschlagen (HTTP ' . $response->status() . ')',
                $response->status(),
            );
        }

        return (array) $response->json();
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('toggl', $this->baseUrl);
            $this->api->setAuthentication(new BasicAuthentication((string) $this->apiToken, 'api_token'));
        }

        return $this->api;
    }
}
