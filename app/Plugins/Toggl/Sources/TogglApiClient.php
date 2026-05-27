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
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Client für die Toggl Track API v9 (https://api.track.toggl.com/api/v9).
 *
 * Authentifizierung per HTTP-Basic: API-Token als Benutzername, das Literal
 * "api_token" als Passwort. {@see fetchEntries()} lädt zunächst die Stammdaten
 * (`/me?with_related_data=true`), um Projekt-/Client-Namen auflösen zu können,
 * und anschließend die Zeiteinträge im Fenster [$from, $to].
 */
class TogglApiClient {
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
        );
    }

    private function request(): \Illuminate\Http\Client\PendingRequest {
        return Http::withBasicAuth((string) $this->apiToken, 'api_token')->acceptJson();
    }
}
