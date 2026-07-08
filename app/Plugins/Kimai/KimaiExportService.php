<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kimai;

use App\Models\{ExternalReference, Organization, Project, TimeEntry};
use App\Plugins\Kimai\Exceptions\KimaiApiException;
use App\Plugins\Kimai\Sources\KimaiApiClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Bucht in workDiary erfasste Zeiten als Kimai-Timesheets zurück (Rückkanal,
 * plugin-intern nach dem OpenProject-Muster — bewusst kein TimeExport-Profil).
 *
 * Kandidaten sind nicht-exportierte Zeiteinträge mit echten Start-/Endzeiten,
 * deren Projekt über eine `project_id`-Reference einem Kimai-Projekt zugeordnet
 * ist (entsteht beim API-Import bzw. bei der Inbox-Buchung von API-Einträgen).
 * Die Ziel-Activity kommt aus `default_activity_id` (Pflicht — Kimai verlangt
 * eine Activity je Timesheet). Idempotent über die `pushed_entry`-Reference;
 * erfolgreich gebuchte Einträge werden als exportiert markiert. Import-Echos
 * sind ausgeschlossen: importierte Einträge tragen eine `entry`-Reference und
 * werden übersprungen.
 */
class KimaiExportService {
    public const EXT_TYPE_PUSHED = 'pushed_entry';

    /**
     * @param  array<string, mixed>  $config
     * @return array{pushed: int, skipped: int, failed: int, errors: list<string>}
     */
    public function exportPending(Organization $organization, array $config, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array {
        if (! (bool) ($config['export_enabled'] ?? false)) {
            return ['pushed' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [(string) __('Kimai-Export ist deaktiviert (Plugin-Einstellung „Rückbuchung aktivieren").')]];
        }

        $client = new KimaiApiClient(
            is_string($config['api_token'] ?? null) ? $config['api_token'] : null,
            is_string($config['base_url'] ?? null) ? $config['base_url'] : null,
        );
        if (! $client->isConfigured()) {
            return ['pushed' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [(string) __('Kimai-API ist nicht konfiguriert (Basis-URL und API-Token in den Plugin-Einstellungen hinterlegen).')]];
        }

        $activityId = is_numeric($config['default_activity_id'] ?? null) ? (int) $config['default_activity_id'] : null;
        if ($activityId === null) {
            return ['pushed' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [(string) __('Keine Kimai-Activity-ID hinterlegt — Rückbuchung nicht möglich.')]];
        }

        $projectMap = $this->kimaiProjectIdByProjectId($organization);
        if ($projectMap === []) {
            return ['pushed' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [(string) __('Kein Projekt ist einem Kimai-Projekt zugeordnet (zuerst API-Import ausführen bzw. Inbox-Gruppen buchen).')]];
        }

        $pushed = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($this->candidates($organization, array_keys($projectMap), $from, $to) as $entry) {
            if ($this->alreadyPushed($organization, $entry) || $this->isImportedFromKimai($organization, $entry)) {
                $skipped++;

                continue;
            }

            $kimaiProjectId = $projectMap[(int) $entry->project_id] ?? null;
            $startedAt = $entry->started_at;
            $endedAt = $entry->ended_at;
            if ($kimaiProjectId === null || $startedAt === null || $endedAt === null) {
                $skipped++;

                continue;
            }

            try {
                $created = $client->createTimesheet([
                    'begin' => $startedAt->format('Y-m-d\TH:i:s'),
                    'end' => $endedAt->format('Y-m-d\TH:i:s'),
                    'project' => $kimaiProjectId,
                    'activity' => $activityId,
                    'description' => (string) ($entry->description ?? ''),
                    'billable' => (bool) $entry->billable,
                ]);

                $this->recordPushed($organization, $entry, $created['id'] ?? null);
                $entry->forceFill(['exported' => true])->save();
                $pushed++;
            } catch (KimaiApiException $e) {
                $errors[] = (string) __('Zeiteintrag #:id: :message', ['id' => $entry->id, 'message' => $e->getMessage()]);
                $failed++;
            }
        }

        return ['pushed' => $pushed, 'skipped' => $skipped, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Mapping workDiary-Projekt-ID → Kimai-Projekt-ID aus den
     * `project_id`-References des API-Imports.
     *
     * @return array<int, int>
     */
    private function kimaiProjectIdByProjectId(Organization $organization): array {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', KimaiPlugin::ID)
            ->where('external_type', KimaiImportService::EXT_TYPE_PROJECT_ID)
            ->where('referenceable_type', (new Project)->getMorphClass())
            ->pluck('external_id', 'referenceable_id')
            ->mapWithKeys(fn ($externalId, $projectId): array => [(int) $projectId => (int) $externalId])
            ->all();
    }

    /**
     * Nicht-exportierte Zeiteinträge mit echten Zeiten in gemappten Projekten,
     * optional im Datumsfenster.
     *
     * @param  list<int>  $projectIds
     * @return Collection<int, TimeEntry>
     */
    private function candidates(Organization $organization, array $projectIds, ?CarbonImmutable $from, ?CarbonImmutable $to): Collection {
        return TimeEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('project_id', $projectIds)
            ->where('exported', false)
            ->where('minutes', '>', 0)
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->when($from !== null, fn ($q) => $q->whereDate('date', '>=', $from?->toDateString()))
            ->when($to !== null, fn ($q) => $q->whereDate('date', '<=', $to?->toDateString()))
            ->orderBy('date')
            ->get();
    }

    private function alreadyPushed(Organization $organization, TimeEntry $entry): bool {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', KimaiPlugin::ID)
            ->where('external_type', self::EXT_TYPE_PUSHED)
            ->where('referenceable_type', $entry->getMorphClass())
            ->where('referenceable_id', $entry->getKey())
            ->exists();
    }

    /** Import-Echo-Schutz: aus Kimai importierte Einträge nie zurückbuchen. */
    private function isImportedFromKimai(Organization $organization, TimeEntry $entry): bool {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', KimaiPlugin::ID)
            ->where('external_type', KimaiImportService::EXT_TYPE_ENTRY)
            ->where('referenceable_type', $entry->getMorphClass())
            ->where('referenceable_id', $entry->getKey())
            ->exists();
    }

    private function recordPushed(Organization $organization, TimeEntry $entry, mixed $kimaiId): void {
        ExternalReference::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => self::EXT_TYPE_PUSHED,
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => is_numeric($kimaiId) ? (string) (int) $kimaiId : 'unknown:' . $entry->getKey(),
            'synced_at' => now(),
        ]);
    }
}
