<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{ExternalReference, Organization, Project, TimeEntry};
use App\Plugins\Support\{AbstractTimeEntryPushService, MatchingTimeImportService, RemoteTimeFingerprint};
use App\Plugins\Toggl\Exceptions\TogglApiException;
use App\Plugins\Toggl\Sources\TogglApiClient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Überträgt in workDiary erfasste Zeiten (z. B. AnyDesk-/RemoteSupport-
 * Sitzungen) nach Toggl ({@see AbstractTimeEntryPushService}-Skelett).
 *
 * Anders als Kimai/OpenProject ist das SPIEGELUNG, kein „Rückbuchen":
 * {@see markPushed()} ist ein No-op — die Einträge bleiben lokal abrechenbar
 * (`exported` bleibt false) und über die zusätzlich geschriebene
 * `entry`-Reference writeback-fähig. Der Duplikat-Schutz gegen das
 * Import-Echo des stündlichen Syncs: nach dem POST wird neben der
 * `pushed_entry`-Reference eine `entry`-Reference mit `toggl:<id>` und dem
 * Fingerabdruck aus dem POST-Response geschrieben — der nächste Import
 * erkennt den Eintrag als bekannt und unverändert. Kandidaten sind Work-
 * Einträge mit echten Start-/Endzeiten gemappter Projekte OHNE bestehende
 * Toggl-Referenz. Angelegt wird immer für den Token-Inhaber (v9-Limitierung).
 */
class TogglExportService extends AbstractTimeEntryPushService {
    private ?TogglApiClient $client = null;

    /** @var array<int, int> workDiary-Projekt-ID → Toggl-Projekt-ID */
    private array $projectMap = [];

    /** @var array<int, int> Toggl-Projekt-ID → Workspace-ID */
    private array $workspaceByTogglProject = [];

    private ?int $fallbackWorkspaceId = null;

    protected function pluginId(): string {
        return TogglPlugin::ID;
    }

    protected function prepareExport(Organization $organization, array $config): ?string {
        if (! (bool) ($config['export_enabled'] ?? false)) {
            return (string) __('Toggl-Übertragung ist deaktiviert (Plugin-Einstellung „Zeit-Übertragung aktivieren").');
        }

        $this->client = new TogglApiClient(
            is_string($config['api_token'] ?? null) ? $config['api_token'] : null,
            is_string($config['base_url'] ?? null) ? $config['base_url'] : 'https://api.track.toggl.com/api/v9',
            is_numeric($config['workspace_id'] ?? null) ? (int) $config['workspace_id'] : null,
        );
        if (! $this->client->isConfigured()) {
            return (string) __('Toggl-API ist nicht konfiguriert (API-Token in den Plugin-Einstellungen hinterlegen).');
        }

        $this->projectMap = $this->togglProjectIdByProjectId($organization);
        if ($this->projectMap === []) {
            return (string) __('Kein Projekt ist einem Toggl-Projekt zugeordnet (zuerst Sync ausführen bzw. Inbox-Gruppen buchen).');
        }

        $this->resolveWorkspaces($config);
        if ($this->fallbackWorkspaceId === null && $this->workspaceByTogglProject === []) {
            return (string) __('Kein Toggl-Workspace auflösbar (Workspace-ID in den Plugin-Einstellungen hinterlegen).');
        }

        return null;
    }

    protected function exportableProjectIds(Organization $organization): array {
        return array_keys($this->projectMap);
    }

    protected function scopeCandidates(Builder $query): Builder {
        return $query
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->where('kind', TimeEntryKind::Work->value)
            // Import-Echo + Push-Idempotenz auf Query-Ebene: JEDE bestehende
            // Toggl-Referenz (importiert ODER gepusht) schließt aus — nötig,
            // weil markPushed() hier exported nicht setzt und die Einträge
            // sonst dauerhaft als „skipped" durch jeden Lauf liefen.
            ->whereNotExists(function ($sub): void {
                $sub->from('external_references')
                    ->whereColumn('external_references.referenceable_id', 'time_entries.id')
                    ->where('external_references.referenceable_type', (new TimeEntry)->getMorphClass())
                    ->where('external_references.plugin_id', TogglPlugin::ID)
                    ->whereIn('external_references.external_type', [
                        MatchingTimeImportService::EXT_TYPE_ENTRY,
                        self::EXT_TYPE_PUSHED,
                    ]);
            });
    }

    protected function shouldSkip(Organization $organization, TimeEntry $entry): bool {
        return ! isset($this->projectMap[(int) $entry->project_id])
            || $this->workspaceFor((int) $entry->project_id) === null
            || $entry->started_at === null
            || $entry->ended_at === null;
    }

    protected function createRemoteEntry(Organization $organization, TimeEntry $entry): string {
        assert($this->client instanceof TogglApiClient);

        $togglProjectId = $this->projectMap[(int) $entry->project_id];
        $workspaceId = (int) $this->workspaceFor((int) $entry->project_id);

        $record = $this->client->createTimeEntry(
            $workspaceId,
            CarbonImmutable::parse((string) $entry->started_at),
            CarbonImmutable::parse((string) $entry->ended_at),
            (string) ($entry->description ?? ''),
            $togglProjectId,
            (bool) $entry->billable,
            array_values($entry->tags->pluck('name')->map(fn ($name): string => (string) $name)->all()),
        );

        $id = is_numeric($record['id'] ?? null) ? (int) $record['id'] : null;
        if ($id === null) {
            throw new TogglApiException('Toggl-Antwort ohne Eintrags-ID');
        }

        $this->recordEntryReference($organization, $entry, $record, $workspaceId, $togglProjectId, $id);

        return (string) $id;
    }

    /** Spiegelung statt Rückbuchung: lokal bleibt der Eintrag abrechenbar. */
    protected function markPushed(TimeEntry $entry): void {
        // bewusst kein exported=true — siehe Klassen-Docblock.
    }

    protected function isExpectedFailure(\Throwable $e): bool {
        return $e instanceof TogglApiException;
    }

    protected function shouldAbort(\Throwable $e): bool {
        return $e instanceof TogglApiException && $e->isRateLimited();
    }

    protected function pushedPayload(TimeEntry $entry): ?array {
        return [
            'workspace_id' => $this->workspaceFor((int) $entry->project_id),
            'minutes' => (int) $entry->minutes,
        ];
    }

    /**
     * `entry`-Reference im Format des Imports (`toggl:<id>` + Fingerabdruck aus
     * dem POST-Response): der nächste stündliche Sync erkennt den gepushten
     * Eintrag als bekannt/unverändert (kein Duplikat, kein Konflikt), und der
     * Writeback kann spätere lokale Korrekturen adressieren.
     *
     * @param  array<string, mixed>  $record
     */
    private function recordEntryReference(Organization $organization, TimeEntry $entry, array $record, int $workspaceId, int $togglProjectId, int $togglId): void {
        $start = is_string($record['start'] ?? null) ? $record['start'] : null;
        $stop = is_string($record['stop'] ?? null) ? $record['stop'] : null;
        $fingerprint = '';
        if ($start !== null && $stop !== null) {
            $fingerprint = RemoteTimeFingerprint::fromParts(
                CarbonImmutable::parse($start),
                CarbonImmutable::parse($stop),
                isset($record['description']) ? (string) $record['description'] : null,
                is_numeric($record['project_id'] ?? null) ? (int) $record['project_id'] : $togglProjectId,
                (bool) ($record['billable'] ?? false),
            );
        }

        ExternalReference::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => TogglPlugin::ID,
                'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
                'referenceable_type' => $entry->getMorphClass(),
                'referenceable_id' => $entry->getKey(),
            ],
            [
                'external_id' => 'toggl:' . $togglId,
                'payload' => [
                    'source' => 'push',
                    'project_id' => $togglProjectId,
                    'workspace_id' => $workspaceId,
                    // '' = Fingerabdruck nicht bestimmbar → syncKnownEntry
                    // behandelt den Eintrag als unverändert (safe).
                    'fingerprint' => $fingerprint,
                ],
                'synced_at' => now(),
            ],
        );
    }

    /**
     * Mapping workDiary-Projekt-ID → Toggl-Projekt-ID aus den
     * `project_id`-References des Imports.
     *
     * @return array<int, int>
     */
    private function togglProjectIdByProjectId(Organization $organization): array {
        return ExternalReference::query()
            ->forPlugin($organization, TogglPlugin::ID, MatchingTimeImportService::EXT_TYPE_PROJECT_ID)
            ->where('referenceable_type', (new Project)->getMorphClass())
            ->pluck('external_id', 'referenceable_id')
            ->mapWithKeys(fn ($externalId, $projectId): array => [(int) $projectId => (int) $externalId])
            ->all();
    }

    /**
     * Workspace-Auflösung: konfigurierte workspace_id gewinnt; sonst über die
     * Workspace-Projektlisten des Tokens (Projekt → Workspace).
     *
     * @param  array<string, mixed>  $config
     */
    private function resolveWorkspaces(array $config): void {
        assert($this->client instanceof TogglApiClient);

        if (is_numeric($config['workspace_id'] ?? null)) {
            $this->fallbackWorkspaceId = (int) $config['workspace_id'];

            return;
        }

        $workspaces = $this->client->workspaces();
        if (count($workspaces) === 1) {
            $this->fallbackWorkspaceId = (int) $workspaces[0]['id'];

            return;
        }

        foreach ($workspaces as $workspace) {
            $workspaceId = (int) $workspace['id'];
            foreach ($this->client->workspaceProjects($workspaceId) as $project) {
                $this->workspaceByTogglProject[(int) $project['id']] = $workspaceId;
            }
        }
    }

    private function workspaceFor(int $projectId): ?int {
        $togglProjectId = $this->projectMap[$projectId] ?? null;
        if ($togglProjectId === null) {
            return null;
        }

        return $this->workspaceByTogglProject[$togglProjectId] ?? $this->fallbackWorkspaceId;
    }
}
