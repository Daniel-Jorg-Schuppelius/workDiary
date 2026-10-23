<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Services;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\Organization;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Plugins\Support\{ImportedTimeEntry, MatchingTimeImportService, RemoteSyncWindow, RemoteTimeFingerprint};
use App\Plugins\Toggl\Api\{TogglApi, TogglClientFactory};
use App\Plugins\Toggl\Enums\TogglSource;
use App\Plugins\Toggl\Sources\{TogglCsvParser, TogglEntry};
use App\Plugins\Toggl\{TogglConfig, TogglPlugin};
use App\Support\Tz;
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
 *  - Quellenwahl (Feature 178): Schlüssel und ID-Referenzen tragen die Quelle;
 *    nach einem Wechsel übernimmt {@see adoptOtherSourceEntries()} den Bestand.
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

    protected function clientIdReferenceType(Organization $organization, ImportedTimeEntry $entry): string {
        return $this->sourceOf($organization, $entry->entryKey)->clientIdType();
    }

    protected function projectIdReferenceType(Organization $organization, ImportedTimeEntry $entry): string {
        return $this->sourceOf($organization, $entry->entryKey)->projectIdType();
    }

    protected function remoteKeyPrefix(Organization $organization): ?string {
        return TogglConfig::resolve($organization->id)['source']->entryKeyPrefix();
    }

    /**
     * Die Quelle steckt im Schlüssel: ein Inbox-Fall aus der Zeit vor dem Wechsel
     * trägt Track-IDs und wird auch danach unter Track-Typen gemerkt. Ohne
     * Präfix (CSV) gilt die eingestellte Quelle.
     */
    private function sourceOf(Organization $organization, ?string $entryKey): TogglSource {
        $active = TogglConfig::resolve($organization->id)['source'];

        return $entryKey !== null && $active->other()->ownsEntryKey($entryKey) ? $active->other() : $active;
    }

    /**
     * Holt die Zeiteinträge der Toggl-API im Fenster [$from, $to] und verarbeitet sie.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see TogglConfig::resolve()}
     * @return array{created: int, skipped: int, unmatched: int, unresolved_users: int, updated: int, conflicts: int, removed: int, adopted: int, incomplete: bool}
     */
    public function importFromApi(Organization $organization, array $config, CarbonImmutable $from, CarbonImmutable $to): array {
        $client = app(TogglClientFactory::class)->for((int) $organization->id);
        if (! $client->isConfigured()) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0, 'unresolved_users' => 0, 'updated' => 0, 'conflicts' => 0, 'removed' => 0, 'adopted' => 0, 'incomplete' => false];
        }

        // Workspace-Namen für die Inbox-Anzeige (Gruppen sind je Workspace getrennt).
        $workspaceNames = [];
        foreach ($client->workspaces() as $workspace) {
            $workspaceNames[(int) $workspace['id']] = (string) $workspace['name'];
        }

        // Eigene Zeiten des Token-Inhabers (/me) PLUS die Einträge ALLER
        // Benutzer über die Reports-API je Workspace — /me allein kennt nur
        // die eigenen Zeiten, Mitarbeiter-Einträge kämen sonst nie an.
        // Dedupe über den stabilen Entry-Key; die Report-Variante gewinnt
        // (trägt die Benutzer-E-Mail für die Mitarbeiter-Zuordnung).
        $entries = [];
        foreach ($client->fetchEntries($from, $to) as $entry) {
            $entries[$entry->entryKey] = $entry;
        }
        foreach (array_keys($workspaceNames) as $workspaceId) {
            foreach ($client->workspaceEntries($workspaceId, $from, $to) as $entry) {
                $entries[$entry->entryKey] = $entry;
            }
        }

        $source = $client->source();
        // Erster Lauf nach dem Wechsel: Projekt- und Kunden-IDs der neuen Quelle
        // über die gemerkten Namen nachtragen — Rückschreiben und Übertragung brauchen sie.
        if ($this->hasIdReferences($organization, $source->other()) && ! $this->hasIdReferences($organization, $source)) {
            $this->backfillIdReferences($organization, $client);
        }
        $adopted = $this->adoptOtherSourceEntries($organization, $source, $entries);

        return $this->ingest(
            $organization,
            $this->mapEntries(array_values($entries), $workspaceNames),
            $config,
            // Löschungen dürfen nur aus einem nachweislich vollständigen Lauf
            // abgeleitet werden: bricht /me, die Workspace-Liste oder die
            // Reports-Pagination still ab (429/Timeout/Rechte), gälte alles
            // Ungelieferte fälschlich als drüben gelöscht — Massenlöschung
            // lokaler Zeiten (passiert am 01./02.08.2026: 50+44 Einträge).
            RemoteSyncWindow::whenComplete($client->isFetchComplete(), $from, $to),
            // Unvollständig heißt auch (MVP-509): die Workspace-Benutzerliste kann
            // gefehlt haben — Einträge ohne E-Mail sind dann klärungsbedürftig,
            // keine scheinbar korrekten Hauptbenutzer-Buchungen.
        ) + ['adopted' => $adopted, 'incomplete' => ! $client->isFetchComplete()];
    }

    /**
     * Quellenwechsel (Feature 178): Was schon unter dem Schlüssel der anderen
     * Quelle importiert ist, bekommt den Schlüssel der gelesenen — sonst legte
     * der Lauf es doppelt an. Die Live-Synchronisation von Toggl überträgt Start
     * und Ende unverändert; erkannt wird über Benutzer, Start und Ende (±1 Minute).
     * Offene Zuordnungsfälle der anderen Quelle zum selben Eintrag werden
     * verworfen, der Lauf legt sie unter dem neuen Schlüssel wieder an.
     *
     * @param  array<string, TogglEntry>  $entries  Schlüssel → Eintrag
     */
    private function adoptOtherSourceEntries(Organization $organization, TogglSource $source, array $entries): int {
        $known = [];
        foreach (array_chunk(array_keys($entries), 500) as $keys) {
            array_push($known, ...ExternalReference::query()
                ->forPlugin($organization, TogglPlugin::ID, self::EXT_TYPE_ENTRY)
                ->whereIn('external_id', $keys)
                ->pluck('external_id')
                ->map(static fn ($key): string => (string) $key)
                ->all());
        }
        $pending = array_diff_key($entries, array_flip($known));
        if ($pending === []) {
            return 0;
        }

        $starts = array_map(static fn (TogglEntry $entry): int => $entry->startedAt->getTimestamp(), $pending);
        $from = CarbonImmutable::createFromTimestampUTC(min($starts) - 120);
        $to = CarbonImmutable::createFromTimestampUTC(max($starts) + 120);
        $foreignPrefix = $source->other()->entryKeyPrefix();

        $references = ExternalReference::query()
            ->forPlugin($organization, TogglPlugin::ID, self::EXT_TYPE_ENTRY)
            ->where('referenceable_type', (new TimeEntry)->getMorphClass())
            ->whereLikeEscaped('external_id', $foreignPrefix, 'prefix')
            ->whereIn('referenceable_id', TimeEntry::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereBetween('started_at', [$from, $to])
                ->select('id'))
            ->get()
            ->all();
        $timeEntries = TimeEntry::query()->withoutGlobalScopes()
            ->whereIn('id', array_map(static fn (ExternalReference $reference): int => (int) $reference->referenceable_id, $references))
            ->get()
            ->keyBy('id');
        $importedCandidates = array_map(static function (ExternalReference $reference) use ($timeEntries): array {
            $timeEntry = $timeEntries->get((int) $reference->referenceable_id);

            return [
                'start' => $timeEntry?->started_at?->toImmutable(),
                'end' => $timeEntry?->ended_at?->toImmutable(),
                'user' => $timeEntry !== null ? (int) $timeEntry->user_id : null,
            ];
        }, $references);

        $items = IntegrationInboxItem::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('status', IntegrationInboxStatus::Open)
            ->where('case_type', IntegrationInboxItem::CASE_UNMATCHED)
            ->whereLikeEscaped('external_id', $foreignPrefix, 'prefix')
            ->whereBetween('occurred_at', [$from, $to])
            ->get()
            ->all();
        $pendingCandidates = array_map(static function (IntegrationInboxItem $item): array {
            $snapshot = (array) ($item->remote_snapshot ?? []);
            $email = mb_strtolower(trim((string) ($snapshot['user_email'] ?? '')));

            return [
                'start' => is_string($snapshot['started_at'] ?? null) ? CarbonImmutable::parse($snapshot['started_at']) : null,
                'end' => is_string($snapshot['ended_at'] ?? null) ? CarbonImmutable::parse($snapshot['ended_at']) : null,
                'user' => $email !== '' ? $email : null,
            ];
        }, $items);

        $adopted = 0;
        foreach ($pending as $entry) {
            $index = self::matchingCandidate($importedCandidates, $entry, $this->resolveImportUser($organization, $entry->userEmail));
            if ($index !== null) {
                $timeEntry = $timeEntries->get((int) $references[$index]->referenceable_id);
                if ($timeEntry instanceof TimeEntry) {
                    $this->adoptReference($organization, $source, $references[$index], $timeEntry, $entry);
                    $adopted++;
                }
                unset($importedCandidates[$index]);

                continue;
            }

            $email = mb_strtolower(trim((string) $entry->userEmail));
            $index = self::matchingCandidate($pendingCandidates, $entry, $email !== '' ? $email : null);
            if ($index !== null) {
                $this->resolveItem($items[$index], IntegrationInboxStatus::Dismissed, null);
                unset($pendingCandidates[$index]);
            }
        }

        return $adopted;
    }

    /**
     * Kandidat mit gleichem Start und Ende (je ±1 Minute) und gleichem Benutzer;
     * ohne bekannten Benutzer zählt nur ein eindeutiger Treffer.
     *
     * @param  array<int, array{start: ?CarbonImmutable, end: ?CarbonImmutable, user: int|string|null}>  $candidates
     */
    private static function matchingCandidate(array $candidates, TogglEntry $entry, int|string|null $user): ?int {
        $hits = [];
        foreach ($candidates as $index => $candidate) {
            if ($candidate['start'] === null || $candidate['end'] === null) {
                continue;
            }
            if ($user !== null && $candidate['user'] !== null && $candidate['user'] !== $user) {
                continue;
            }
            if (abs($candidate['start']->getTimestamp() - $entry->startedAt->getTimestamp()) > 60
                || abs($candidate['end']->getTimestamp() - $entry->endedAt->getTimestamp()) > 60) {
                continue;
            }
            $hits[] = $index;
        }

        return $hits !== [] && ($user !== null || count($hits) === 1) ? $hits[0] : null;
    }

    /**
     * Schlüssel, Workspace, Projekt-ID und Fingerabdruck auf den Stand der neuen
     * Quelle setzen; der alte Schlüssel bleibt im Payload nachvollziehbar.
     */
    private function adoptReference(Organization $organization, TogglSource $source, ExternalReference $reference, TimeEntry $timeEntry, TogglEntry $entry): void {
        $previousKey = (string) $reference->external_id;
        $this->migrateLegacyEntryKey($organization, $previousKey, $entry->entryKey);

        $reference->forceFill([
            'external_id' => $entry->entryKey,
            'payload' => array_merge((array) $reference->payload, [
                'project_id' => $entry->projectId,
                'workspace_id' => $entry->workspaceId,
                'fingerprint' => RemoteTimeFingerprint::of($this->toImported($entry)),
                'previous_key' => $previousKey,
            ]),
            'synced_at' => now(),
        ])->save();

        $project = $timeEntry->project_id !== null ? Project::query()->withoutGlobalScopes()->find($timeEntry->project_id) : null;
        if (! $project instanceof Project) {
            return;
        }
        if ($entry->projectId !== null) {
            $this->rememberReference($organization, $source->projectIdType(), (string) $entry->projectId, $project);
        }
        $clientTarget = $project->foreignCustomer ?? $project->customer;
        if ($entry->clientId !== null && $clientTarget !== null) {
            $this->rememberReference($organization, $source->clientIdType(), (string) $entry->clientId, $clientTarget);
        }
    }

    private function hasIdReferences(Organization $organization, TogglSource $source): bool {
        return ExternalReference::query()
            ->forPlugin($organization->id, TogglPlugin::ID)
            ->whereIn('external_type', [$source->projectIdType(), $source->clientIdType()])
            ->exists();
    }

    /**
     * Verarbeitet einen Toggl-Detailed-Report-CSV-Inhalt.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see TogglConfig::resolve()}
     * @return array{created: int, skipped: int, unmatched: int, unresolved_users: int, updated: int, conflicts: int, removed: int}
     */
    public function importFromCsv(Organization $organization, string $csvContent, array $config): array {
        return $this->ingest($organization, $this->mapEntries($this->csvParser->parse($csvContent, Tz::ofOrganization($organization))), $config);
    }

    /**
     * @param  array<int, TogglEntry>  $entries
     * @param  array<int, string>  $workspaceNames  Workspace-ID → Name (nur API)
     * @return array<int, ImportedTimeEntry>
     */
    private function mapEntries(array $entries, array $workspaceNames = []): array {
        return array_map(fn(TogglEntry $entry): ImportedTimeEntry => $this->toImported($entry, $workspaceNames), $entries);
    }

    /**
     * Mappt das Toggl-DTO (keine Tätigkeit) auf das gemeinsame Import-DTO.
     *
     * @param  array<int, string>  $workspaceNames
     */
    private function toImported(TogglEntry $entry, array $workspaceNames = []): ImportedTimeEntry {
        return new ImportedTimeEntry(
            entryKey: $entry->entryKey,
            clientName: $entry->clientName,
            projectName: $entry->projectName,
            activity: null,
            description: $entry->description,
            startedAt: $entry->startedAt,
            endedAt: $entry->endedAt,
            // Toggl liefert billable für jeden Eintrag (Free-Plan: immer false,
            // das Flag ist dort Premium) — nur ein echtes true ist ein Signal,
            // sonst kein Signal (null) statt hartem „nicht abrechenbar"
            // (Spiegel der Projekt-Regel in TogglExportImporter).
            billable: $entry->billable ? true : null,
            userEmail: $entry->userEmail,
            tags: $entry->tags,
            source: $entry->source,
            clientId: $entry->clientId,
            projectId: $entry->projectId,
            workspaceId: $entry->workspaceId,
            workspaceName: $entry->workspaceId !== null ? ($workspaceNames[$entry->workspaceId] ?? null) : null,
            legacyEntryKey: $entry->legacyEntryKey,
        );
    }

    /**
     * Toggl-Delta: die stabile Client-ID (nur API) schlägt den Namen — robust
     * gegen Umbenennungen in Toggl. Danach greift die Basis (Namens-Reference
     * inkl. Merge-Alias, dann Name-/Firmen-Fallback). Zeigt die ID-Referenz auf
     * einen Fremdkunden (Endkunden), ist dessen Firma der Kunde.
     */
    public function matchCustomer(Organization $organization, ?string $clientName, ?int $clientId = null): ?Customer {
        $byId = $this->resolveClientById($organization, $clientId, TogglConfig::resolve($organization->id)['source']);
        if ($byId instanceof Customer) {
            return $byId;
        }
        if ($byId instanceof ForeignCustomer) {
            return $byId->customer;
        }

        return parent::matchCustomer($organization, $clientName);
    }

    protected function matchClientForEntry(Organization $organization, ImportedTimeEntry $entry): Customer|ForeignCustomer|null {
        return $this->resolveClientById($organization, $entry->clientId, $this->sourceOf($organization, $entry->entryKey))
            ?? parent::matchClientForEntry($organization, $entry);
    }

    /** Gemerkte Client-ID-Referenz (nur API-Quelle) → Kunde oder Fremdkunde. */
    private function resolveClientById(Organization $organization, ?int $clientId, TogglSource $source): Customer|ForeignCustomer|null {
        if ($clientId === null) {
            return null;
        }

        $byId = $this->resolveByReference($organization, $source->clientIdType(), (string) $clientId);

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
    public function bookInboxGroup(Organization $organization, string $groupKey, ?Customer $customer, ?Project $project, ?int $userId = null, ?ForeignCustomer $foreignCustomer = null): array {
        // Benutzer-Gruppen (MVP-509) tragen Einträge VERSCHIEDENER Projekte —
        // die stabilen IDs des ersten Snapshots dürfen dann nicht pauschal auf
        // das Fallback-Projekt gemerkt werden.
        if (! $this->isUserGroupKey($groupKey)) {
            $firstSnap = (array) ($this->openInboxItems($organization)->where('group_key', $groupKey)->first()->remote_snapshot ?? []);
            $clientId = is_numeric($firstSnap['client_id'] ?? null) ? (int) $firstSnap['client_id'] : null;
            $projectId = is_numeric($firstSnap['project_id'] ?? null) ? (int) $firstSnap['project_id'] : null;
            $source = $this->sourceOf($organization, is_string($firstSnap['entry_key'] ?? null) ? $firstSnap['entry_key'] : null);

            // Wie die Namens-Referenz der Basis: der Fremdkunde (Endkunde) ist der
            // präzisere Schlüssel für die stabile Client-ID.
            if ($customer !== null && $clientId !== null) {
                $this->rememberReference($organization, $source->clientIdType(), (string) $clientId, $foreignCustomer ?? $customer);
            }
            if ($projectId !== null && $project !== null) {
                $this->rememberReference($organization, $source->projectIdType(), (string) $projectId, $project);
            }
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
            ->forPlugin($organization->id, TogglPlugin::ID)
            ->whereIn('external_type', [self::EXT_TYPE_CLIENT, self::EXT_TYPE_PROJECT, self::EXT_TYPE_USER_EMAIL])
            ->with('referenceable')
            ->orderBy('external_type')
            ->orderBy('external_id')
            ->get();
    }

    /**
     * Trägt für bestehende, namensbasiert verknüpfte Projekte/Kunden die stabilen
     * Toggl-ID-Referenzen der eingestellten Quelle nach (einmaliger Sync gegen den
     * Workspace). Bestehende Namens-Referenzen bleiben unberührt; künftige Importe
     * matchen dann ID-first.
     *
     * @return array{projects: int, clients: int}
     */
    public function backfillIdReferences(Organization $organization, ?TogglApi $client = null): array {
        $config = TogglConfig::resolve($organization->id);
        $client ??= app(TogglClientFactory::class)->for((int) $organization->id);
        if (! $client->isConfigured()) {
            return ['projects' => 0, 'clients' => 0];
        }

        $source = $client->source();
        $workspaceIds = $source === TogglSource::Track && $config['workspace_id'] !== null
            ? [$config['workspace_id']]
            : array_map(static fn(array $w): int => $w['id'], $client->workspaces());

        $projects = 0;
        $clients = 0;

        foreach ($workspaceIds as $workspaceId) {
            foreach ($client->workspaceClients($workspaceId) as $remoteClient) {
                $customer = $this->resolveByReference($organization, self::EXT_TYPE_CLIENT, $remoteClient['name']);
                if ($customer instanceof Customer) {
                    $this->rememberReference($organization, $source->clientIdType(), (string) $remoteClient['id'], $customer);
                    $clients++;
                }
            }

            foreach ($client->workspaceProjects($workspaceId) as $remoteProject) {
                $key = $this->projectKey($remoteProject['client_name'] ?? null, $remoteProject['name']);
                $project = $this->resolveByReference($organization, self::EXT_TYPE_PROJECT, $key);
                if ($project instanceof Project) {
                    $this->rememberReference($organization, $source->projectIdType(), (string) $remoteProject['id'], $project);
                    $projects++;
                }
            }
        }

        return ['projects' => $projects, 'clients' => $clients];
    }
}
