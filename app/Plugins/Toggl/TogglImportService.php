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

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, ExternalReference, Organization, Project, TimeEntry, TogglPendingEntry, User};
use App\Plugins\Toggl\Sources\{TogglApiClient, TogglCsvParser, TogglEntry};
use Carbon\CarbonImmutable;

/**
 * Kernlogik des Toggl-Imports:
 *  - Zeiteinträge aus der Toggl-API ({@see importFromApi()}) oder einem
 *    Detailed-Report-CSV ({@see importFromCsv()}) einlesen.
 *  - Toggl-Client → Kunde und Toggl-Projekt → Projekt ausschließlich über
 *    bestehende {@see ExternalReference} bzw. Namensgleichheit matchen
 *    (kein Auto-Anlegen). Treffer → TimeEntry (idempotent über die
 *    `entry`-Reference); kein Treffer → {@see TogglPendingEntry} (Inbox).
 *  - Die Inbox weist Gruppen einem Kunden + Projekt zu ({@see assignPending()}),
 *    persistiert dabei die `client`/`project`-Reference (→ Folgeimporte matchen
 *    automatisch) und materialisiert die Einträge.
 */
class TogglImportService {
    public const EXT_TYPE_CLIENT = 'client';

    public const EXT_TYPE_PROJECT = 'project';

    public const EXT_TYPE_ENTRY = 'entry';

    public function __construct(private readonly TogglCsvParser $csvParser = new TogglCsvParser) {
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

        return $this->ingest($organization, $client->fetchEntries($from, $to), $config);
    }

    /**
     * Verarbeitet einen Toggl-Detailed-Report-CSV-Inhalt.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see TogglConfig::resolve()}
     * @return array{created: int, skipped: int, unmatched: int}
     */
    public function importFromCsv(Organization $organization, string $csvContent, array $config): array {
        return $this->ingest($organization, $this->csvParser->parse($csvContent), $config);
    }

    /**
     * @param  array<int, TogglEntry>  $entries
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int}
     */
    private function ingest(Organization $organization, array $entries, array $config): array {
        $created = 0;
        $skipped = 0;
        $unmatched = 0;

        $userId = $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
        if ($userId === null) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0];
        }

        foreach ($entries as $entry) {
            if ($this->alreadyImported($organization, $entry->entryKey)) {
                $skipped++;

                continue;
            }

            $project = $this->matchProject($organization, $entry);
            if ($project === null) {
                $this->recordPending($organization, $entry);
                $unmatched++;

                continue;
            }

            $this->createTimeEntry($organization, $project, $entry, $userId, (bool) $config['default_billable']);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'unmatched' => $unmatched];
    }

    /**
     * Matcht den Toggl-Client eines Eintrags auf einen Kunden — zuerst über die
     * gespeicherte `client`-Reference, sonst über Name/Firma (case-insensitiv).
     */
    public function matchCustomer(Organization $organization, ?string $clientName): ?Customer {
        $clientName = $clientName !== null ? trim($clientName) : '';
        if ($clientName === '') {
            return null;
        }

        $ref = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('external_type', self::EXT_TYPE_CLIENT)
            ->where('external_id', $clientName)
            ->first();

        if ($ref?->referenceable instanceof Customer) {
            return $ref->referenceable;
        }

        return Customer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where(function ($q) use ($clientName): void {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($clientName)])
                    ->orWhereRaw('LOWER(company) = ?', [mb_strtolower($clientName)]);
            })
            ->first();
    }

    /**
     * Matcht den Toggl-Eintrag auf ein Projekt — über die `project`-Reference,
     * sonst über den Projektnamen (innerhalb des gematchten Kunden, sofern bekannt).
     */
    public function matchProject(Organization $organization, TogglEntry $entry): ?Project {
        $projectName = $entry->projectName !== null ? trim($entry->projectName) : '';
        if ($projectName === '') {
            return null;
        }

        $ref = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('external_type', self::EXT_TYPE_PROJECT)
            ->where('external_id', $this->projectKey($entry->clientName, $projectName))
            ->first();

        if ($ref?->referenceable instanceof Project) {
            return $ref->referenceable;
        }

        $customer = $this->matchCustomer($organization, $entry->clientName);

        $query = Project::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($projectName)]);

        if ($customer !== null) {
            $query->where('customer_id', $customer->id);
        }

        return $query->first();
    }

    private function alreadyImported(Organization $organization, string $entryKey): bool {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('external_type', self::EXT_TYPE_ENTRY)
            ->where('external_id', $entryKey)
            ->exists();
    }

    private function createTimeEntry(Organization $organization, Project $project, TogglEntry $entry, int $userId, bool $defaultBillable): TimeEntry {
        $description = trim(implode(' — ', array_filter([
            $entry->projectName,
            $entry->description,
        ]))) ?: (string) __('Toggl-Zeiteintrag');

        $timeEntry = TimeEntry::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $userId,
            'date' => $entry->startedAt->toDateString(),
            'started_at' => $entry->startedAt,
            'ended_at' => $entry->endedAt,
            'kind' => TimeEntryKind::Work,
            'description' => $description,
            'billable' => $defaultBillable && $entry->billable,
        ]);

        // Idempotenz-Anker: verknüpft den Toggl-Eintrag mit dem TimeEntry.
        ExternalReference::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => self::EXT_TYPE_ENTRY,
            'referenceable_type' => $timeEntry->getMorphClass(),
            'referenceable_id' => $timeEntry->getKey(),
            'external_id' => $entry->entryKey,
            'payload' => [
                'source' => $entry->source,
                'client' => $entry->clientName,
                'project' => $entry->projectName,
            ],
            'synced_at' => now(),
        ]);

        return $timeEntry;
    }

    /**
     * Legt einen unmatchbaren Eintrag als offenes Pending ab (Dedupe über entry_key).
     */
    private function recordPending(Organization $organization, TogglEntry $entry): void {
        $exists = TogglPendingEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('entry_key', $entry->entryKey)
            ->exists();

        if ($exists) {
            return;
        }

        TogglPendingEntry::query()->create([
            'organization_id' => $organization->id,
            'source' => $entry->source,
            'entry_key' => $entry->entryKey,
            'client_name' => $entry->clientName,
            'project_name' => $entry->projectName,
            'description' => $entry->description,
            'started_at' => $entry->startedAt,
            'ended_at' => $entry->endedAt,
            'billable' => $entry->billable,
            'user_email' => $entry->userEmail,
            'status' => TogglPendingEntry::STATUS_OPEN,
        ]);
    }

    /**
     * Offene Pending-Einträge der Organisation, gruppiert nach Client + Projekt.
     *
     * @return \Illuminate\Support\Collection<int, object{client_name: ?string, project_name: ?string, count: int, minutes: int, first_seen: \Illuminate\Support\Carbon, last_seen: \Illuminate\Support\Carbon}>
     */
    public function openPendingGroups(Organization $organization): \Illuminate\Support\Collection {
        $groups = TogglPendingEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', TogglPendingEntry::STATUS_OPEN)
            ->orderByDesc('ended_at')
            ->get()
            ->groupBy(fn(TogglPendingEntry $e): string => ($e->client_name ?? '') . '|' . ($e->project_name ?? ''))
            ->map(function ($group): object {
                /** @var \Illuminate\Support\Collection<int, TogglPendingEntry> $group */
                $first = $group->first();
                assert($first instanceof TogglPendingEntry);

                return (object) [
                    'client_name' => $first->client_name,
                    'project_name' => $first->project_name,
                    'count' => (int) $group->count(),
                    'minutes' => (int) $group->sum(fn(TogglPendingEntry $e): int => $e->minutes()),
                    'first_seen' => $group->min('started_at'),
                    'last_seen' => $group->max('ended_at'),
                ];
            })
            ->values();

        /** @var \Illuminate\Support\Collection<int, object{client_name: string|null, project_name: string|null, count: int, minutes: int, first_seen: \Illuminate\Support\Carbon, last_seen: \Illuminate\Support\Carbon}> $groups */
        return $groups;
    }

    /**
     * Weist alle offenen Pending-Einträge einer (client, project)-Gruppe einem
     * Kunden + Projekt zu: persistiert die Referenzen und materialisiert die
     * Einträge als TimeEntries (idempotent). Markiert die Pendings als imported.
     *
     * @return array{created: int, skipped: int}
     */
    public function assignPending(Organization $organization, ?string $clientName, ?string $projectName, Customer $customer, Project $project, ?int $userId = null): array {
        $config = TogglConfig::resolve($organization->id);
        $userId ??= $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
        if ($userId === null) {
            return ['created' => 0, 'skipped' => 0];
        }

        // Referenzen merken, damit künftige Imports automatisch matchen.
        $clientName = $clientName !== null ? trim($clientName) : '';
        if ($clientName !== '') {
            $this->rememberReference($organization, self::EXT_TYPE_CLIENT, $clientName, $customer);
        }
        $this->rememberReference($organization, self::EXT_TYPE_PROJECT, $this->projectKey($clientName, $projectName), $project);

        $created = 0;
        $skipped = 0;

        foreach ($this->openPendingFor($organization, $clientName, $projectName) as $row) {
            $entry = $this->entryFromPending($row);

            if ($this->alreadyImported($organization, $entry->entryKey)) {
                $row->update([
                    'status' => TogglPendingEntry::STATUS_IMPORTED,
                    'resolved_at' => now(),
                ]);
                $skipped++;

                continue;
            }

            $timeEntry = $this->createTimeEntry($organization, $project, $entry, $userId, (bool) $config['default_billable']);
            $row->update([
                'status' => TogglPendingEntry::STATUS_IMPORTED,
                'time_entry_id' => $timeEntry->id,
                'resolved_at' => now(),
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Verwirft alle offenen Pending-Einträge einer (client, project)-Gruppe. */
    public function dismissPending(Organization $organization, ?string $clientName, ?string $projectName): int {
        return $this->openPendingFor($organization, $clientName !== null ? trim($clientName) : '', $projectName)
            ->each(fn(TogglPendingEntry $row) => $row->update([
                'status' => TogglPendingEntry::STATUS_DISMISSED,
                'resolved_at' => now(),
            ]))
            ->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, TogglPendingEntry>
     */
    private function openPendingFor(Organization $organization, string $clientName, ?string $projectName): \Illuminate\Support\Collection {
        $projectName = $projectName !== null ? trim($projectName) : '';

        return TogglPendingEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', TogglPendingEntry::STATUS_OPEN)
            ->where(fn($q) => $clientName === '' ? $q->whereNull('client_name') : $q->where('client_name', $clientName))
            ->where(fn($q) => $projectName === '' ? $q->whereNull('project_name') : $q->where('project_name', $projectName))
            ->get();
    }

    private function entryFromPending(TogglPendingEntry $row): TogglEntry {
        return new TogglEntry(
            source: $row->source,
            entryKey: $row->entry_key,
            clientName: $row->client_name,
            projectName: $row->project_name,
            description: $row->description,
            startedAt: CarbonImmutable::parse($row->started_at),
            endedAt: CarbonImmutable::parse($row->ended_at),
            billable: (bool) $row->billable,
            userEmail: $row->user_email,
        );
    }

    private function rememberReference(Organization $organization, string $type, string $externalId, \Illuminate\Database\Eloquent\Model $referenceable): void {
        ExternalReference::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => TogglPlugin::ID,
                'external_type' => $type,
                'external_id' => $externalId,
            ],
            [
                'referenceable_type' => $referenceable->getMorphClass(),
                'referenceable_id' => $referenceable->getKey(),
                'synced_at' => now(),
            ],
        );
    }

    /**
     * Bestimmt den Buchungs-Benutzer: konfigurierte default_user_id (in der Org)
     * → Org-Owner → erster Org-Benutzer. (Identisch zu RemoteSupport.)
     */
    private function resolveBookingUserId(Organization $organization, ?int $defaultUserId): ?int {
        if ($defaultUserId !== null) {
            $user = User::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereKey($defaultUserId)
                ->first();
            if ($user !== null) {
                return (int) $user->id;
            }
        }

        if ($organization->owner_id !== null) {
            return (int) $organization->owner_id;
        }

        $first = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->first();

        return $first !== null ? (int) $first->id : null;
    }

    /** Stabiler Schlüssel für die Projekt-Reference (Client + Projektname, case-insensitiv). */
    private function projectKey(?string $clientName, ?string $projectName): string {
        return mb_strtolower(trim((string) $clientName) . '|' . trim((string) $projectName));
    }
}
