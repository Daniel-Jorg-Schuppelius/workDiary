<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl;

use App\Models\{Customer, ExternalReference, ForeignCustomer, Organization, Project};
use App\Plugins\Support\{ImportedTimeEntry, MatchingTimeImportService};
use App\Plugins\Toggl\Sources\{TogglApiClient, TogglCsvParser, TogglEntry};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Toggl-Zeitimport auf der gemeinsamen {@see MatchingTimeImportService}-
 * Pipeline (API oder Detailed-Report-CSV). Toggl-Spezifika der Ableitung:
 *  - {@see TogglEntry} wird an der Quelle auf {@see ImportedTimeEntry} gemappt;
 *    {@see matchProject()} akzeptiert das Toggl-DTO weiterhin direkt.
 *  - Die Kunden-Auflösung kennt die stabile Toggl-Client-ID
 *    ({@see matchCustomer()} / {@see matchCustomerForEntry()}).
 *  - {@see mappings()} speist die Mapping-Verwaltung,
 *    {@see backfillIdReferences()} den einmaligen Workspace-Sync.
 */
class TogglImportService extends MatchingTimeImportService {
    public function __construct(private readonly TogglCsvParser $csvParser = new TogglCsvParser) {}

    protected function pluginId(): string {
        return TogglPlugin::ID;
    }

    protected function resolveConfig(int $organizationId): array {
        return TogglConfig::resolve($organizationId);
    }

    protected function fallbackDescription(): string {
        return (string) __('Toggl-Zeiteintrag');
    }

    /**
     * Holt die Zeiteinträge der Toggl-API im Fenster [$from, $to] und verarbeitet sie.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see TogglConfig::resolve()}
     * @return array{created: int, skipped: int, unmatched: int}
     */
    public function importFromApi(Organization $organization, array $config, CarbonImmutable $from, CarbonImmutable $to): array {
        $client = new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id']);
        if (! $client->isConfigured()) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0];
        }

        return $this->ingest($organization, $this->mapEntries($client->fetchEntries($from, $to)), $config);
    }

    /**
     * Verarbeitet einen Toggl-Detailed-Report-CSV-Inhalt.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see TogglConfig::resolve()}
     * @return array{created: int, skipped: int, unmatched: int}
     */
    public function importFromCsv(Organization $organization, string $csvContent, array $config): array {
        return $this->ingest($organization, $this->mapEntries($this->csvParser->parse($csvContent)), $config);
    }

    /**
     * @param  array<int, TogglEntry>  $entries
     * @return array<int, ImportedTimeEntry>
     */
    private function mapEntries(array $entries): array {
        return array_map(fn(TogglEntry $entry): ImportedTimeEntry => $this->toImported($entry), $entries);
    }

    /** Mappt das Toggl-DTO (keine Tätigkeit/Tags) auf das gemeinsame Import-DTO. */
    private function toImported(TogglEntry $entry): ImportedTimeEntry {
        return new ImportedTimeEntry(
            entryKey: $entry->entryKey,
            clientName: $entry->clientName,
            projectName: $entry->projectName,
            activity: null,
            description: $entry->description,
            startedAt: $entry->startedAt,
            endedAt: $entry->endedAt,
            billable: $entry->billable,
            userEmail: $entry->userEmail,
            tags: [],
            source: $entry->source,
            clientId: $entry->clientId,
            projectId: $entry->projectId,
        );
    }

    /**
     * Toggl-Delta: die stabile Client-ID (nur API) schlägt den Namen — robust
     * gegen Umbenennungen in Toggl. Danach greift die Basis (Namens-Reference
     * inkl. Merge-Alias, dann Name-/Firmen-Fallback). Zeigt die ID-Referenz auf
     * einen Fremdkunden (Endkunden), ist dessen Firma der Kunde.
     */
    public function matchCustomer(Organization $organization, ?string $clientName, ?int $clientId = null): ?Customer {
        $byId = $this->resolveClientById($organization, $clientId);
        if ($byId instanceof Customer) {
            return $byId;
        }
        if ($byId instanceof ForeignCustomer) {
            return $byId->customer;
        }

        return parent::matchCustomer($organization, $clientName);
    }

    protected function matchClientForEntry(Organization $organization, ImportedTimeEntry $entry): Customer|ForeignCustomer|null {
        return $this->resolveClientById($organization, $entry->clientId)
            ?? parent::matchClientForEntry($organization, $entry);
    }

    /** Gemerkte Client-ID-Referenz (nur API-Quelle) → Kunde oder Fremdkunde. */
    private function resolveClientById(Organization $organization, ?int $clientId): Customer|ForeignCustomer|null {
        if ($clientId === null) {
            return null;
        }

        $byId = $this->resolveByReference($organization, self::EXT_TYPE_CLIENT_ID, (string) $clientId);

        return ($byId instanceof Customer || $byId instanceof ForeignCustomer) ? $byId : null;
    }

    /** Akzeptiert das Toggl-DTO direkt (Aufrufer/Tests) und delegiert an die Basis. */
    public function matchProject(Organization $organization, ImportedTimeEntry|TogglEntry $entry): ?Project {
        return parent::matchProject($organization, $entry instanceof TogglEntry ? $this->toImported($entry) : $entry);
    }

    /**
     * Toggl-Delta: stabile ID-Referenzen aus dem ersten Snapshot schon vor der
     * Buchung merken — bleibt auch dann erhalten, wenn alle Einträge bereits
     * importiert waren (die Basis schreibt sie nur je angelegtem TimeEntry).
     *
     * @return array{created: int, skipped: int}
     */
    public function bookInboxGroup(Organization $organization, string $groupKey, ?Customer $customer, Project $project, ?int $userId = null, ?ForeignCustomer $foreignCustomer = null): array {
        $firstSnap = (array) ($this->openInboxItems($organization)->where('group_key', $groupKey)->first()->remote_snapshot ?? []);
        $clientId = is_numeric($firstSnap['client_id'] ?? null) ? (int) $firstSnap['client_id'] : null;
        $projectId = is_numeric($firstSnap['project_id'] ?? null) ? (int) $firstSnap['project_id'] : null;

        // Wie die Namens-Referenz der Basis: der Fremdkunde (Endkunde) ist der
        // präzisere Schlüssel für die stabile Client-ID.
        if ($customer !== null && $clientId !== null) {
            $this->rememberReference($organization, self::EXT_TYPE_CLIENT_ID, (string) $clientId, $foreignCustomer ?? $customer);
        }
        if ($projectId !== null) {
            $this->rememberReference($organization, self::EXT_TYPE_PROJECT_ID, (string) $projectId, $project);
        }

        return parent::bookInboxGroup($organization, $groupKey, $customer, $project, $userId, $foreignCustomer);
    }

    /**
     * Alle gemerkten Client-/Projekt-Zuordnungen der Organisation (für die
     * Mapping-Verwaltung), inkl. aufgelöstem Ziel.
     *
     * @return Collection<int, ExternalReference>
     */
    public function mappings(Organization $organization): Collection {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->whereIn('external_type', [self::EXT_TYPE_CLIENT, self::EXT_TYPE_PROJECT])
            ->with('referenceable')
            ->orderBy('external_type')
            ->orderBy('external_id')
            ->get();
    }

    /**
     * Trägt für bestehende, namensbasiert verknüpfte Projekte/Kunden die stabilen
     * Toggl-ID-Referenzen nach (einmaliger Sync gegen den Workspace). Bestehende
     * Namens-Referenzen bleiben unberührt; künftige Importe matchen dann ID-first.
     *
     * @return array{projects: int, clients: int}
     */
    public function backfillIdReferences(Organization $organization): array {
        $config = TogglConfig::resolve($organization->id);
        $client = new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id']);
        if (! $client->isConfigured()) {
            return ['projects' => 0, 'clients' => 0];
        }

        $workspaceIds = $config['workspace_id'] !== null
            ? [$config['workspace_id']]
            : array_map(static fn(array $w): int => $w['id'], $client->workspaces());

        $projects = 0;
        $clients = 0;

        foreach ($workspaceIds as $workspaceId) {
            foreach ($client->workspaceClients($workspaceId) as $remoteClient) {
                $customer = $this->resolveByReference($organization, self::EXT_TYPE_CLIENT, $remoteClient['name']);
                if ($customer instanceof Customer) {
                    $this->rememberReference($organization, self::EXT_TYPE_CLIENT_ID, (string) $remoteClient['id'], $customer);
                    $clients++;
                }
            }

            foreach ($client->workspaceProjects($workspaceId) as $remoteProject) {
                $key = $this->projectKey($remoteProject['client_name'] ?? null, $remoteProject['name']);
                $project = $this->resolveByReference($organization, self::EXT_TYPE_PROJECT, $key);
                if ($project instanceof Project) {
                    $this->rememberReference($organization, self::EXT_TYPE_PROJECT_ID, (string) $remoteProject['id'], $project);
                    $projects++;
                }
            }
        }

        return ['projects' => $projects, 'clients' => $clients];
    }
}
