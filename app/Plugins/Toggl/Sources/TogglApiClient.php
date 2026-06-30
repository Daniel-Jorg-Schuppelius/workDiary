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

use App\Models\TogglPendingEntry;
use App\Plugins\Support\PluginHttp;
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
class TogglApiClient {
    /** Maximale Historie (Jahre) beim „alles"-Import, falls kein Floor bestimmbar ist. */
    public const MAX_HISTORY_YEARS = 12;

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

        return $this->request()
            ->timeout(5)
            ->get($this->baseUrl . '/me')
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

        $response = $this->request()
            ->timeout(20)
            ->get($this->baseUrl . '/me/time_entries', [
                'start_date' => $from->toIso8601String(),
                'end_date' => $to->toIso8601String(),
            ]);

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
        $response = $this->request()
            ->timeout(20)
            ->get($this->baseUrl . '/me', ['with_related_data' => 'true']);

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
            source: TogglPendingEntry::SOURCE_API,
            entryKey: 'toggl:' . ($id ?? ($start . '|' . $stop)),
            clientName: $clientId !== null ? ($clients[$clientId] ?? null) : null,
            projectName: $project['name'] ?? null,
            description: isset($record['description']) ? trim((string) $record['description']) : null,
            startedAt: CarbonImmutable::parse($start),
            endedAt: CarbonImmutable::parse($stop),
            billable: (bool) ($record['billable'] ?? false),
            userEmail: $email,
            clientId: $clientId !== null ? (int) $clientId : null,
            projectId: $projectId,
        );
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

        $response = $this->request()->timeout(20)->get($this->baseUrl . '/workspaces');
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
        $response = $this->request()
            ->timeout(20)
            ->get($this->baseUrl . '/workspaces/' . $workspaceId . '/clients', ['status' => 'both']);

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
            $response = $this->request()
                ->timeout(20)
                ->get($this->baseUrl . '/workspaces/' . $workspaceId . '/projects', [
                    'active' => 'both',
                    'per_page' => 200,
                    'page' => $page,
                ]);

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
     * Workspace-Benutzer, Format wie der Ordner-Reader.
     *
     * @return array<int, array{email: string, name: string, timezone: ?string}>
     */
    public function workspaceUsers(int $workspaceId): array {
        $response = $this->request()
            ->timeout(20)
            ->get($this->baseUrl . '/workspaces/' . $workspaceId . '/users');

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

        $entries = [];
        $windowEnd = $ceil;
        while ($windowEnd->greaterThanOrEqualTo($floor)) {
            $windowStart = $windowEnd->subYear()->addDay();
            if ($windowStart->lessThan($floor)) {
                $windowStart = $floor;
            }

            foreach ($this->fetchReportWindow($workspaceId, $windowStart, $windowEnd) as $row) {
                foreach ($this->mapReportRow((array) $row, $projects) as $entry) {
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

            $response = $this->request()->timeout(60)->post($url, $payload);
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
     * @return array<int, TogglEntry>
     */
    private function mapReportRow(array $row, array $projects): array {
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
        $userEmail = $this->reportUserEmail($row);

        $entries = [];
        foreach ($items as $item) {
            $entry = $this->reportItemToEntry((array) $item, $clientName, $projectName, $description, $billable, $userEmail, $clientId, $projectId);
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
     */
    private function reportItemToEntry(array $item, ?string $clientName, ?string $projectName, ?string $description, bool $billable, ?string $userEmail, ?int $clientId = null, ?int $projectId = null): ?TogglEntry {
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
            source: TogglPendingEntry::SOURCE_API,
            entryKey: 'toggl:' . ($id ?? ($start . '|' . $stop)),
            clientName: $clientName,
            projectName: $projectName,
            description: $description,
            startedAt: CarbonImmutable::parse($start),
            endedAt: CarbonImmutable::parse($stop),
            billable: $billable,
            userEmail: $userEmail,
            clientId: $clientId,
            projectId: $projectId,
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

    private function request(): \Illuminate\Http\Client\PendingRequest {
        return PluginHttp::for('toggl')->withBasicAuth((string) $this->apiToken, 'api_token')->acceptJson();
    }
}
