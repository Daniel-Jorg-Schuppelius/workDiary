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
use App\Models\{ExternalReference, IntegrationInboxItem, Organization, Project, Task, TimeEntry};
use App\Plugins\OpenProject\{OpenProjectConfig, OpenProjectPlugin};
use App\Plugins\OpenProject\Sources\{OpenProjectApiClient, OpenProjectEntry};
use App\Plugins\Support\{PersistsTimeImportInbox, ReconcilesRemoteDeletions, RemoteSyncWindow, RemoteTimeFingerprint, TimeWritebackObserver};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Kernlogik des OpenProject-Imports:
 *  - {@see syncStructure()} gleicht Projekte/Work-Packages/Benutzer ab
 *    ({@see OpenProjectStructureSync}).
 *  - {@see importTimes()} liest die Zeiteinträge im Fenster [$from, $to] und legt
 *    für gemappte Projekte einen {@see TimeEntry} an (idempotent über die
 *    `entry`-Reference); nicht gemappte Projekte → universelle Zuordnungs-Inbox
 *    ({@see \App\Models\IntegrationInboxItem}, gruppiert nach Projekt).
 *  - Die Inbox bucht Gruppen gegen ein Projekt ({@see bookInboxGroup()}), merkt die
 *    Projekt-Reference (→ Folgeimporte matchen automatisch) und bucht die Einträge.
 *
 * {@see importFromApi()} ist der vollständige Lauf (Struktur + Zeiten) für den
 * Scheduler/Command bzw. die Admin-Aktion.
 */
class OpenProjectImportService {
    use PersistsTimeImportInbox;
    use ReconcilesRemoteDeletions;

    public const EXT_TYPE_ENTRY = 'entry';

    public function __construct(private readonly OpenProjectStructureSync $structure) {}

    protected function pluginId(): string {
        return OpenProjectPlugin::ID;
    }

    /**
     * Vollständiger API-Lauf: Struktur-Sync, anschließend Zeit-Import.
     *
     * @param  array<string, mixed>  $config  Ergebnis von {@see \App\Plugins\OpenProject\OpenProjectConfig::resolve()}
     * @return array{created: int, skipped: int, unmatched: int}
     */
    public function importFromApi(Organization $organization, array $config, CarbonImmutable $from, CarbonImmutable $to): array {
        $client = $this->client($config);
        if (! $client->isConfigured()) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0, 'updated' => 0, 'conflicts' => 0, 'removed' => 0];
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
     * @return array{created: int, skipped: int, unmatched: int, updated: int, conflicts: int, removed: int}
     */
    public function importTimes(Organization $organization, array $config, OpenProjectApiClient $client, CarbonImmutable $from, CarbonImmutable $to): array {
        // Ungefilterter Lauf über alle Benutzer — fehlende Einträge sind in
        // OpenProject gelöscht.
        return $this->ingest($organization, $client->fetchTimeEntries($from, $to), $config, new RemoteSyncWindow($from, $to));
    }

    /**
     * @param  array<int, OpenProjectEntry>  $entries
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int, updated: int, conflicts: int, removed: int}
     */
    private function ingest(Organization $organization, array $entries, array $config, ?RemoteSyncWindow $window = null): array {
        // Der Import darf keine Rückschreibung auslösen — die Einträge kommen ja
        // gerade von dort.
        return TimeWritebackObserver::suppressed(fn (): array => $this->ingestEntries($organization, $entries, $config, $window));
    }

    /**
     * @param  array<int, OpenProjectEntry>  $entries
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int, updated: int, conflicts: int}
     */
    private function ingestEntries(Organization $organization, array $entries, array $config, ?RemoteSyncWindow $window): array {
        $created = 0;
        $skipped = 0;
        $unmatched = 0;
        $updated = 0;
        $conflicts = 0;

        $fallbackUserId = $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
        if ($fallbackUserId === null) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0, 'updated' => 0, 'conflicts' => 0, 'removed' => 0];
        }

        foreach ($entries as $entry) {
            if ($this->alreadyImported($organization, $entry->entryKey)) {
                // Bekannter Eintrag: gegen den hinterlegten Fingerabdruck prüfen,
                // sonst bleiben Korrekturen in OpenProject hier unsichtbar.
                match ($this->syncKnownEntry($organization, $entry)) {
                    'updated' => $updated++,
                    'conflict' => $conflicts++,
                    default => $skipped++,
                };

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

        return [
            'created' => $created,
            'skipped' => $skipped,
            'unmatched' => $unmatched,
            'updated' => $updated,
            'conflicts' => $conflicts,
            'removed' => $this->reconcileRemoteDeletions(
                $organization,
                array_values(array_map(static fn (OpenProjectEntry $entry): string => $entry->entryKey, $entries)),
                $window,
                self::EXT_TYPE_ENTRY,
            ),
        ];
    }

    /**
     * Gleicht einen bereits importierten Eintrag mit dem OpenProject-Stand ab.
     * Abgerechnete Zeiten werden nicht überschrieben — die Abweichung landet
     * als Konflikt in der Inbox.
     *
     * @return 'unchanged'|'updated'|'conflict'
     */
    private function syncKnownEntry(Organization $organization, OpenProjectEntry $entry): string {
        $reference = ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), self::EXT_TYPE_ENTRY)
            ->forExternalId($entry->entryKey)
            ->first();

        $known = is_array($reference?->payload) ? (string) ($reference->payload['fingerprint'] ?? '') : '';
        $current = RemoteTimeFingerprint::fromDuration($entry->spentOn, $entry->minutes);
        if ($reference === null || $known === '' || $known === $current) {
            return 'unchanged'; // Altbestand ohne Fingerabdruck bleibt unberührt
        }

        $timeEntry = $reference->referenceable;
        if (! $timeEntry instanceof TimeEntry) {
            return 'unchanged';
        }

        if ($timeEntry->exported) {
            $this->recordRemoteChangeConflict($organization, $reference, $timeEntry, $entry);

            return 'conflict';
        }

        $timeEntry->forceFill([
            'date' => $entry->spentOn->toDateString(),
            'minutes' => $entry->minutes,
        ])->save();

        $reference->payload = array_merge((array) $reference->payload, ['fingerprint' => $current]);
        $reference->synced_at = now();
        $reference->save();

        return 'updated';
    }

    /** Fremde Änderung an einer bereits abgerechneten Zeit sichtbar machen. */
    private function recordRemoteChangeConflict(Organization $organization, ExternalReference $reference, TimeEntry $timeEntry, OpenProjectEntry $entry): void {
        IntegrationInboxItem::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => $this->pluginId(),
                'dedupe_key' => $this->pluginId() . '-remote-changed:' . $reference->external_id,
            ],
            [
                'source' => $this->pluginId(),
                'target_type' => TimeEntry::class,
                'external_type' => self::EXT_TYPE_ENTRY,
                'external_id' => (string) $reference->external_id,
                'case_type' => IntegrationInboxItem::CASE_CONFLICT,
                'referenceable_type' => $timeEntry->getMorphClass(),
                'referenceable_id' => $timeEntry->getKey(),
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'remote_snapshot' => [
                    'reason' => 'remote_changed_after_export',
                    // Die Zeit hängt an einem Beleg: der Fremdstand darf hier
                    // nicht per Klick übernommen werden (GoBD). Bleibt nur
                    // Kenntnisnahme bzw. eine Korrektur außerhalb.
                    'resolution' => IntegrationInboxItem::RESOLUTION_ACKNOWLEDGE_ONLY,
                    'remote' => ['spent_on' => $entry->spentOn->toDateString(), 'minutes' => $entry->minutes],
                    'local' => ['date' => $timeEntry->date?->toDateString(), 'minutes' => $timeEntry->minutes],
                ],
            ],
        );
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
                // Stand in OpenProject zum Importzeitpunkt — Grundlage für
                // Nachzug und Konflikterkennung.
                'fingerprint' => RemoteTimeFingerprint::fromDuration($entry->spentOn, $entry->minutes),
            ],
            'synced_at' => now(),
        ]);

        return $timeEntry;
    }

    /**
     * Legt einen unmatchbaren Eintrag als offenen Eintrag in der universellen
     * Zuordnungs-Inbox ab (gruppiert nach OpenProject-Projekt). Idempotent über
     * den entry_key (dedupe_key).
     */
    private function recordPending(Organization $organization, OpenProjectEntry $entry): void {
        $projectExternalId = $entry->projectExternalId !== null ? trim($entry->projectExternalId) : '';
        $project = trim((string) $entry->projectName);

        $this->recordPendingItem($organization, $entry->entryKey, [
            'source' => 'api',
            'group_key' => $projectExternalId !== '' ? 'project:' . $projectExternalId : 'op:none',
            'remote_snapshot' => [
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
            ],
            'display_title' => $project !== '' ? $project : (string) __('(ohne Projekt)'),
            'display_subtitle' => $entry->workPackageSubject,
            'occurred_at' => $entry->spentOn,
        ]);
    }

    /**
     * Offene OpenProject-Inbox-Einträge der Organisation, gruppiert nach Projekt
     * (group_key), für die Gruppen-Auflösung in der universellen Inbox.
     *
     * @return Collection<int, array{group_key: string, project_external_id: ?string, project_name: ?string, count: int, minutes: int, first_seen: ?\Illuminate\Support\Carbon, last_seen: ?\Illuminate\Support\Carbon}>
     */
    public function openInboxGroups(Organization $organization): Collection {
        return $this->openInboxItems($organization)
            ->groupBy('group_key')
            ->map(function ($group, $groupKey): array {
                /** @var Collection<int, IntegrationInboxItem> $group */
                $first = $group->first();
                $snap = $first !== null ? $first->remote_snapshot : [];
                /** @var \Illuminate\Support\Carbon|null $firstSeen */
                $firstSeen = $group->min('occurred_at');
                /** @var \Illuminate\Support\Carbon|null $lastSeen */
                $lastSeen = $group->max('occurred_at');

                return [
                    'group_key' => (string) $groupKey,
                    'project_external_id' => isset($snap['project_external_id']) ? (string) $snap['project_external_id'] : null,
                    'project_name' => isset($snap['project_name']) ? (string) $snap['project_name'] : null,
                    'count' => $group->count(),
                    'minutes' => (int) $group->sum(fn(IntegrationInboxItem $i): int => (int) (($i->remote_snapshot['minutes'] ?? 0))),
                    'first_seen' => $firstSeen,
                    'last_seen' => $lastSeen,
                ];
            })
            ->values();
    }

    /**
     * Bucht alle offenen Inbox-Einträge einer Gruppe gegen ein Projekt: merkt die
     * Projekt-Reference und materialisiert die Einträge als TimeEntries
     * (idempotent). Work-Package-/Benutzer-Mappings werden je Eintrag aufgelöst.
     *
     * @return array{created: int, skipped: int}
     */
    public function bookInboxGroup(Organization $organization, string $groupKey, Project $project): array {
        $config = OpenProjectConfig::resolve($organization->id);
        $fallbackUserId = $this->resolveBookingUserId($organization, $config['default_user_id'] ?? null);
        if ($fallbackUserId === null) {
            return ['created' => 0, 'skipped' => 0];
        }

        $items = $this->openInboxItems($organization)->where('group_key', $groupKey)->values();
        if ($items->isEmpty()) {
            return ['created' => 0, 'skipped' => 0];
        }

        $projectExternalId = trim((string) (($items->first()?->remote_snapshot['project_external_id']) ?? ''));
        if ($projectExternalId !== '') {
            $this->structure->linkProject($organization, $projectExternalId, $project, $project->name);
        }

        $created = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $snap = (array) $item->remote_snapshot;
            $entry = $this->entryFromSnapshot($snap);

            if ($this->alreadyImported($organization, $entry->entryKey)) {
                $this->resolveItem($item, IntegrationInboxItem::STATUS_RESOLVED_LINKED, null);
                $skipped++;

                continue;
            }

            $task = $this->structure->resolveTask($organization, $snap['work_package_external_id'] ?? null);
            $userId = $this->structure->resolveUserId($organization, $snap['user_external_id'] ?? null) ?? $fallbackUserId;

            $timeEntry = $this->createTimeEntry($organization, $project, $task, $entry, $userId, (bool) $config['default_billable']);
            $this->resolveItem($item, IntegrationInboxItem::STATUS_RESOLVED_CREATED, $timeEntry);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Verwirft alle offenen Inbox-Einträge einer Gruppe. */
    public function dismissInboxGroup(Organization $organization, string $groupKey): int {
        $items = $this->openInboxItems($organization)->where('group_key', $groupKey);
        foreach ($items as $item) {
            $this->resolveItem($item, IntegrationInboxItem::STATUS_DISMISSED, null);
        }

        return $items->count();
    }

    /**
     * @param  array<string, mixed>  $snap
     */
    private function entryFromSnapshot(array $snap): OpenProjectEntry {
        return new OpenProjectEntry(
            entryKey: (string) ($snap['entry_key'] ?? ''),
            projectExternalId: $snap['project_external_id'] ?? null,
            projectName: $snap['project_name'] ?? null,
            workPackageExternalId: $snap['work_package_external_id'] ?? null,
            workPackageSubject: $snap['work_package_subject'] ?? null,
            description: $snap['description'] ?? null,
            spentOn: CarbonImmutable::parse((string) $snap['spent_on']),
            minutes: (int) ($snap['minutes'] ?? 0),
            userExternalId: $snap['user_external_id'] ?? null,
            userName: $snap['user_name'] ?? null,
        );
    }

    /** @param array<string, mixed> $config */
    private function client(array $config): OpenProjectApiClient {
        return new OpenProjectApiClient($config['api_token'] ?? null, $config['base_url'] ?? null);
    }
}
