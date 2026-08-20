<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Clockify;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{ExternalReference, Organization, Project, TimeEntry};
use App\Plugins\Clockify\Exceptions\ClockifyApiException;
use App\Plugins\Clockify\Sources\ClockifyApiClient;
use App\Plugins\Support\{AbstractTimeEntryPushService, ImportedTimeEntry, MatchingTimeImportService, RemoteTimeFingerprint};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Überträgt in workDiary erfasste Zeiten nach Clockify (Konsolidierungs-Audit
 * C5, Audit 2026-08 Welle 1.1) — SPIEGELUNG nach dem Toggl-Muster:
 * {@see markPushed()} ist ein No-op, die Einträge bleiben lokal abrechenbar
 * und über die zusätzliche `entry`-Reference writeback-fähig.
 *
 * Anders als Toggl/Kimai gibt es keine `project_id`-References (Clockify-
 * Projekt-IDs sind Zeichenketten): Das Mapping läuft über die namensbasierten
 * `project`-References der Zuordnungs-Inbox („client|projekt", lowercase)
 * gegen die Projektliste des Workspace (gecacht — Free-Plan: 30 Requests/h).
 * Angelegt wird immer für den Inhaber des API-Keys (Clockify-Limitierung).
 */
class ClockifyExportService extends AbstractTimeEntryPushService {
    private ?ClockifyApiClient $client = null;

    /** @var array<int, string> workDiary-Projekt-ID → Clockify-Projekt-ID */
    private array $projectMap = [];

    protected function pluginId(): string {
        return ClockifyPlugin::ID;
    }

    protected function prepareExport(Organization $organization, array $config): ?string {
        if (! (bool) ($config['export_enabled'] ?? false)) {
            return (string) __('Clockify-Übertragung ist deaktiviert (Plugin-Einstellung „Zeit-Übertragung aktivieren").');
        }

        $this->client = new ClockifyApiClient(
            is_string($config['api_key'] ?? null) ? $config['api_key'] : null,
            is_string($config['base_url'] ?? null) && $config['base_url'] !== '' ? $config['base_url'] : ClockifyConfig::DEFAULT_BASE_URL,
            is_string($config['reports_base_url'] ?? null) && $config['reports_base_url'] !== '' ? $config['reports_base_url'] : ClockifyConfig::DEFAULT_REPORTS_BASE_URL,
            is_string($config['workspace_id'] ?? null) ? $config['workspace_id'] : null,
        );
        if (! $this->client->isConfigured()) {
            return (string) __('Clockify-API ist nicht konfiguriert (API-Key in den Plugin-Einstellungen hinterlegen).');
        }

        $this->projectMap = $this->clockifyProjectIdByProjectId($organization);
        if ($this->projectMap === []) {
            return (string) __('Kein Projekt ist einem Clockify-Projekt zugeordnet (zuerst API-Import ausführen bzw. Inbox-Gruppen buchen).');
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
            // Import-Echo + Push-Idempotenz auf Query-Ebene (wie Toggl): JEDE
            // bestehende Clockify-Referenz schließt aus, weil markPushed()
            // exported nicht setzt.
            ->whereNotExists(function ($sub): void {
                $sub->from('external_references')
                    ->whereColumn('external_references.referenceable_id', 'time_entries.id')
                    ->where('external_references.referenceable_type', (new TimeEntry)->getMorphClass())
                    ->where('external_references.plugin_id', ClockifyPlugin::ID)
                    ->whereIn('external_references.external_type', [
                        MatchingTimeImportService::EXT_TYPE_ENTRY,
                        self::EXT_TYPE_PUSHED,
                    ]);
            });
    }

    protected function shouldSkip(Organization $organization, TimeEntry $entry): bool {
        return ! isset($this->projectMap[(int) $entry->project_id])
            || $entry->started_at === null
            || $entry->ended_at === null;
    }

    protected function createRemoteEntry(Organization $organization, TimeEntry $entry): string {
        assert($this->client instanceof ClockifyApiClient);

        $projectId = $this->projectMap[(int) $entry->project_id];

        $record = $this->client->createTimeEntry(
            CarbonImmutable::parse((string) $entry->started_at),
            CarbonImmutable::parse((string) $entry->ended_at),
            (string) ($entry->description ?? ''),
            $projectId,
            (bool) $entry->billable,
        );

        $id = isset($record['id']) && is_string($record['id']) && $record['id'] !== '' ? $record['id'] : null;
        if ($id === null) {
            throw new ClockifyApiException((string) __('Clockify-Antwort ohne Eintrags-ID'));
        }

        $this->recordEntryReference($organization, $entry, $record, $id);

        return $id;
    }

    /** Spiegelung statt Rückbuchung: lokal bleibt der Eintrag abrechenbar. */
    protected function markPushed(TimeEntry $entry): void {
        // bewusst kein exported=true — siehe Klassen-Docblock.
    }

    protected function isExpectedFailure(\Throwable $e): bool {
        return $e instanceof ClockifyApiException;
    }

    protected function shouldAbort(\Throwable $e): bool {
        // Free-Plan-Drosselung (30 Requests/h): Lauf beenden, Rest bleibt offen.
        return $e instanceof ClockifyApiException && $e->isRateLimited();
    }

    protected function pushedPayload(TimeEntry $entry): ?array {
        return ['minutes' => (int) $entry->minutes];
    }

    /**
     * `entry`-Reference im Format des Imports (`api:<id>` + Fingerabdruck aus
     * dem POST-Response): der nächste Sync erkennt den gepushten Eintrag als
     * bekannt/unverändert, der Writeback kann spätere Korrekturen adressieren.
     * Projekt geht — wie beim Import — NICHT in den Fingerabdruck ein
     * (Clockify-IDs sind Zeichenketten).
     *
     * @param  array<string, mixed>  $record
     */
    private function recordEntryReference(Organization $organization, TimeEntry $entry, array $record, string $clockifyId): void {
        $interval = is_array($record['timeInterval'] ?? null) ? $record['timeInterval'] : [];
        $start = $interval['start'] ?? null;
        $end = $interval['end'] ?? null;
        $fingerprint = '';
        if (is_string($start) && $start !== '' && is_string($end) && $end !== '') {
            $fingerprint = RemoteTimeFingerprint::fromParts(
                CarbonImmutable::parse($start),
                CarbonImmutable::parse($end),
                isset($record['description']) ? (string) $record['description'] : null,
                null,
                (bool) ($record['billable'] ?? false),
            );
        }

        ExternalReference::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => ClockifyPlugin::ID,
                'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
                'referenceable_type' => $entry->getMorphClass(),
                'referenceable_id' => $entry->getKey(),
            ],
            [
                'external_id' => ImportedTimeEntry::apiKey($clockifyId),
                'payload' => [
                    'source' => 'push',
                    // '' = Fingerabdruck nicht bestimmbar → syncKnownEntry
                    // behandelt den Eintrag als unverändert (safe).
                    'fingerprint' => $fingerprint,
                ],
                'synced_at' => now(),
            ],
        );
    }

    /**
     * Mapping workDiary-Projekt-ID → Clockify-Projekt-ID: namensbasierte
     * `project`-References („client|projekt", lowercase) gegen die
     * Workspace-Projektliste. Liste kurz je Org gecacht (Free-Plan-Quota).
     *
     * @return array<int, string>
     */
    private function clockifyProjectIdByProjectId(Organization $organization): array {
        $referenceByKey = ExternalReference::query()
            ->forPlugin($organization, ClockifyPlugin::ID, MatchingTimeImportService::EXT_TYPE_PROJECT)
            ->where('referenceable_type', (new Project)->getMorphClass())
            ->pluck('referenceable_id', 'external_id')
            ->mapWithKeys(fn ($projectId, $key): array => [(string) $key => (int) $projectId])
            ->all();
        if ($referenceByKey === []) {
            return [];
        }

        /** @var array<string, string> $clockifyIdByKey „client|projekt" (lowercase) → Clockify-Projekt-ID */
        $clockifyIdByKey = Cache::remember('clockify:project-map:' . $organization->id, 600, function (): array {
            assert($this->client instanceof ClockifyApiClient);

            $map = [];
            foreach ($this->client->workspaceProjects() as $project) {
                $id = isset($project['id']) && is_string($project['id']) ? $project['id'] : null;
                $name = isset($project['name']) && is_string($project['name']) ? trim($project['name']) : '';
                if ($id === null || $name === '') {
                    continue;
                }
                $client = isset($project['clientName']) && is_string($project['clientName']) ? trim($project['clientName']) : '';
                $map[mb_strtolower($client . '|' . $name)] = $id;
            }

            return $map;
        });

        $result = [];
        foreach ($referenceByKey as $key => $projectId) {
            if (isset($clockifyIdByKey[$key])) {
                $result[$projectId] = $clockifyIdByKey[$key];
            }
        }

        return $result;
    }
}
