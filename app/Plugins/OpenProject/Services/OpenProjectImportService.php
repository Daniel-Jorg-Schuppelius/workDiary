<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Services;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{ExternalReference, OpenProjectPendingEntry, Organization, Project, Task, TimeEntry, User};
use App\Plugins\OpenProject\OpenProjectPlugin;
use App\Plugins\OpenProject\Sources\{OpenProjectApiClient, OpenProjectEntry};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Kernlogik des OpenProject-Imports:
 *  - {@see syncStructure()} gleicht Projekte/Work-Packages/Benutzer ab
 *    ({@see OpenProjectStructureSync}).
 *  - {@see importTimes()} liest die Zeiteinträge im Fenster [$from, $to] und legt
 *    für gemappte Projekte einen {@see TimeEntry} an (idempotent über die
 *    `entry`-Reference); nicht gemappte Projekte → {@see OpenProjectPendingEntry}.
 *  - Die Inbox weist Gruppen einem Projekt zu ({@see assignPending()}), merkt die
 *    Projekt-Reference (→ Folgeimporte matchen automatisch) und bucht die Einträge.
 *
 * {@see importFromApi()} ist der vollständige Lauf (Struktur + Zeiten) für den
 * Scheduler/Command bzw. die Admin-Aktion.
 */
class OpenProjectImportService {
    public const EXT_TYPE_ENTRY = 'entry';

    public function __construct(private readonly OpenProjectStructureSync $structure) {}

    /**
     * Vollständiger API-Lauf: Struktur-Sync, anschließend Zeit-Import.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see \App\Plugins\OpenProject\OpenProjectConfig::resolve()}
     * @return array{created: int, skipped: int, unmatched: int}
     */
    public function importFromApi(Organization $organization, array $config, CarbonImmutable $from, CarbonImmutable $to): array {
        $client = $this->client($config);
        if (! $client->isConfigured()) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0];
        }

        $this->syncStructure($organization, $config, $client);

        return $this->importTimes($organization, $config, $client, $from, $to);
    }

    /**
     * Struktur-Sync (Projekte + Work Packages + Benutzer).
     *
     * @param  array<string, mixed>  $config
     * @return array{projects: array{linked: int, created: int, unmatched: int}, work_packages: array{linked: int, created: int, unmatched: int}, users: array{linked: int, unmatched: int}}
     */
    public function syncStructure(Organization $organization, array $config, OpenProjectApiClient $client): array {
        return [
            'projects' => $this->structure->syncProjects($organization, $config, $client),
            'work_packages' => $this->structure->syncWorkPackages($organization, $config, $client),
            'users' => $this->structure->syncUsers($organization, $client),
        ];
    }

    /**
     * Zeit-Import im Fenster [$from, $to] (setzt aktuelle Mappings voraus).
     *
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int}
     */
    public function importTimes(Organization $organization, array $config, OpenProjectApiClient $client, CarbonImmutable $from, CarbonImmutable $to): array {
        return $this->ingest($organization, $client->fetchTimeEntries($from, $to), $config);
    }

    /**
     * @param  array<int, OpenProjectEntry>  $entries
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int}
     */
    private function ingest(Organization $organization, array $entries, array $config): array {
        $created = 0;
        $skipped = 0;
        $unmatched = 0;

        $fallbackUserId = $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
        if ($fallbackUserId === null) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0];
        }

        foreach ($entries as $entry) {
            if ($this->alreadyImported($organization, $entry->entryKey)) {
                $skipped++;

                continue;
            }

            $project = $this->structure->resolveProject($organization, $entry->projectExternalId);
            if ($project === null) {
                $this->recordPending($organization, $entry);
                $unmatched++;

                continue;
            }

            $task = $this->structure->resolveTask($organization, $entry->workPackageExternalId);
            $userId = $this->structure->resolveUserId($organization, $entry->userExternalId) ?? $fallbackUserId;

            $this->createTimeEntry($organization, $project, $task, $entry, $userId, (bool) $config['default_billable']);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'unmatched' => $unmatched];
    }

    private function alreadyImported(Organization $organization, string $entryKey): bool {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', OpenProjectPlugin::ID)
            ->where('external_type', self::EXT_TYPE_ENTRY)
            ->where('external_id', $entryKey)
            ->exists();
    }

    private function createTimeEntry(Organization $organization, Project $project, ?Task $task, OpenProjectEntry $entry, int $userId, bool $defaultBillable): TimeEntry {
        $description = trim(implode(' — ', array_filter([
            $entry->workPackageSubject,
            $entry->description,
        ]))) ?: (string) __('OpenProject-Zeiteintrag');

        $timeEntry = TimeEntry::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'user_id' => $userId,
            'date' => $entry->spentOn->toDateString(),
            'minutes' => $entry->minutes,
            'kind' => TimeEntryKind::Work,
            'description' => $description,
            'billable' => $defaultBillable && $entry->billable,
        ]);

        // Idempotenz-Anker: verknüpft den OpenProject-Eintrag mit dem TimeEntry.
        ExternalReference::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => OpenProjectPlugin::ID,
            'external_type' => self::EXT_TYPE_ENTRY,
            'referenceable_type' => $timeEntry->getMorphClass(),
            'referenceable_id' => $timeEntry->getKey(),
            'external_id' => $entry->entryKey,
            'payload' => [
                'project' => $entry->projectExternalId,
                'work_package' => $entry->workPackageExternalId,
            ],
            'synced_at' => now(),
        ]);

        return $timeEntry;
    }

    /** Legt einen unmatchbaren Eintrag als offenes Pending ab (Dedupe über entry_key). */
    private function recordPending(Organization $organization, OpenProjectEntry $entry): void {
        $exists = OpenProjectPendingEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('entry_key', $entry->entryKey)
            ->exists();

        if ($exists) {
            return;
        }

        OpenProjectPendingEntry::query()->create([
            'organization_id' => $organization->id,
            'entry_key' => $entry->entryKey,
            'project_external_id' => $entry->projectExternalId,
            'project_name' => $entry->projectName,
            'work_package_external_id' => $entry->workPackageExternalId,
            'work_package_subject' => $entry->workPackageSubject,
            'description' => $entry->description,
            'spent_on' => $entry->spentOn->toDateString(),
            'minutes' => $entry->minutes,
            'user_external_id' => $entry->userExternalId,
            'user_name' => $entry->userName,
            'status' => OpenProjectPendingEntry::STATUS_OPEN,
        ]);
    }

    /**
     * Offene Pending-Einträge der Organisation, gruppiert nach OpenProject-Projekt.
     *
     * @return Collection<int, object{project_external_id: string|null, project_name: string|null, count: int, minutes: int, first_seen: \Illuminate\Support\Carbon, last_seen: \Illuminate\Support\Carbon}>
     */
    public function openPendingGroups(Organization $organization): Collection {
        $groups = OpenProjectPendingEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', OpenProjectPendingEntry::STATUS_OPEN)
            ->orderByDesc('spent_on')
            ->get()
            ->groupBy(fn(OpenProjectPendingEntry $e): string => (string) ($e->project_external_id ?? ''))
            ->map(function ($group): object {
                /** @var Collection<int, OpenProjectPendingEntry> $group */
                $first = $group->first();
                assert($first instanceof OpenProjectPendingEntry);

                return (object) [
                    'project_external_id' => $first->project_external_id,
                    'project_name' => $first->project_name,
                    'count' => (int) $group->count(),
                    'minutes' => (int) $group->sum(fn(OpenProjectPendingEntry $e): int => (int) $e->minutes),
                    'first_seen' => $group->min('spent_on'),
                    'last_seen' => $group->max('spent_on'),
                ];
            })
            ->values();

        /** @var Collection<int, object{project_external_id: string|null, project_name: string|null, count: int, minutes: int, first_seen: \Illuminate\Support\Carbon, last_seen: \Illuminate\Support\Carbon}> $groups */
        return $groups;
    }

    /**
     * Weist alle offenen Pending-Einträge eines OpenProject-Projekts einem
     * workDiary-Projekt zu: merkt die Projekt-Reference und bucht die Einträge
     * als TimeEntries (idempotent). Vorhandene Work-Package-Mappings werden für
     * die Aufgabenzuordnung berücksichtigt.
     *
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int}
     */
    public function assignPending(Organization $organization, ?string $projectExternalId, Project $project, array $config): array {
        $fallbackUserId = $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
        if ($fallbackUserId === null) {
            return ['created' => 0, 'skipped' => 0];
        }

        if ($projectExternalId !== null && $projectExternalId !== '') {
            $this->structure->linkProject($organization, $projectExternalId, $project, $project->name);
        }

        $created = 0;
        $skipped = 0;

        foreach ($this->openPendingFor($organization, $projectExternalId) as $row) {
            if ($this->alreadyImported($organization, $row->entry_key)) {
                $row->update(['status' => OpenProjectPendingEntry::STATUS_IMPORTED, 'resolved_at' => now()]);
                $skipped++;

                continue;
            }

            $entry = $this->entryFromPending($row);
            $task = $this->structure->resolveTask($organization, $row->work_package_external_id);
            $userId = $this->structure->resolveUserId($organization, $row->user_external_id) ?? $fallbackUserId;

            $timeEntry = $this->createTimeEntry($organization, $project, $task, $entry, $userId, (bool) $config['default_billable']);
            $row->update([
                'status' => OpenProjectPendingEntry::STATUS_IMPORTED,
                'time_entry_id' => $timeEntry->id,
                'resolved_at' => now(),
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Verwirft alle offenen Pending-Einträge eines OpenProject-Projekts. */
    public function dismissPending(Organization $organization, ?string $projectExternalId): int {
        return $this->openPendingFor($organization, $projectExternalId)
            ->each(fn(OpenProjectPendingEntry $row) => $row->update([
                'status' => OpenProjectPendingEntry::STATUS_DISMISSED,
                'resolved_at' => now(),
            ]))
            ->count();
    }

    /**
     * @return Collection<int, OpenProjectPendingEntry>
     */
    private function openPendingFor(Organization $organization, ?string $projectExternalId): Collection {
        $projectExternalId = $projectExternalId !== null ? trim($projectExternalId) : '';

        return OpenProjectPendingEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', OpenProjectPendingEntry::STATUS_OPEN)
            ->where(fn($q) => $projectExternalId === '' ? $q->whereNull('project_external_id') : $q->where('project_external_id', $projectExternalId))
            ->get();
    }

    private function entryFromPending(OpenProjectPendingEntry $row): OpenProjectEntry {
        return new OpenProjectEntry(
            entryKey: $row->entry_key,
            projectExternalId: $row->project_external_id,
            projectName: $row->project_name,
            workPackageExternalId: $row->work_package_external_id,
            workPackageSubject: $row->work_package_subject,
            description: $row->description,
            spentOn: CarbonImmutable::parse($row->spent_on),
            minutes: (int) $row->minutes,
            userExternalId: $row->user_external_id,
            userName: $row->user_name,
        );
    }

    /**
     * Bestimmt den Buchungs-Benutzer: konfigurierte default_user_id (in der Org)
     * → Org-Owner → erster Org-Benutzer. (Identisch zu Toggl/RemoteSupport.)
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

    /** @param array<string, mixed> $config */
    private function client(array $config): OpenProjectApiClient {
        return new OpenProjectApiClient($config['api_token'] ?? null, $config['base_url'] ?? null);
    }
}
