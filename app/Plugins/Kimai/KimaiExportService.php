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
use App\Plugins\Support\AbstractTimeEntryPushService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bucht in workDiary erfasste Zeiten als Kimai-Timesheets zurück
 * ({@see AbstractTimeEntryPushService}-Skelett, bewusst kein TimeExport-Profil).
 *
 * Kandidaten sind nicht-exportierte Zeiteinträge mit echten Start-/Endzeiten,
 * deren Projekt über eine `project_id`-Reference einem Kimai-Projekt zugeordnet
 * ist (entsteht beim API-Import bzw. bei der Inbox-Buchung von API-Einträgen).
 * Die Ziel-Activity kommt aus `default_activity_id` (Pflicht — Kimai verlangt
 * eine Activity je Timesheet). Import-Echos sind ausgeschlossen: importierte
 * Einträge tragen eine `entry`-Reference und werden übersprungen.
 */
class KimaiExportService extends AbstractTimeEntryPushService {
    private ?KimaiApiClient $client = null;

    private ?int $activityId = null;

    /** @var array<int, int> workDiary-Projekt-ID → Kimai-Projekt-ID */
    private array $projectMap = [];

    protected function pluginId(): string {
        return KimaiPlugin::ID;
    }

    protected function prepareExport(Organization $organization, array $config): ?string {
        if (! (bool) ($config['export_enabled'] ?? false)) {
            return (string) __('Kimai-Export ist deaktiviert (Plugin-Einstellung „Rückbuchung aktivieren").');
        }

        $this->client = new KimaiApiClient(
            is_string($config['api_token'] ?? null) ? $config['api_token'] : null,
            is_string($config['base_url'] ?? null) ? $config['base_url'] : null,
        );
        if (! $this->client->isConfigured()) {
            return (string) __('Kimai-API ist nicht konfiguriert (Basis-URL und API-Token in den Plugin-Einstellungen hinterlegen).');
        }

        $this->activityId = is_numeric($config['default_activity_id'] ?? null) ? (int) $config['default_activity_id'] : null;
        if ($this->activityId === null) {
            return (string) __('Keine Kimai-Activity-ID hinterlegt — Rückbuchung nicht möglich.');
        }

        $this->projectMap = $this->kimaiProjectIdByProjectId($organization);
        if ($this->projectMap === []) {
            return (string) __('Kein Projekt ist einem Kimai-Projekt zugeordnet (zuerst API-Import ausführen bzw. Inbox-Gruppen buchen).');
        }

        return null;
    }

    protected function exportableProjectIds(Organization $organization): array {
        return array_keys($this->projectMap);
    }

    protected function scopeCandidates(Builder $query): Builder {
        return $query
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at');
    }

    /** Import-Echo-Schutz + Guard gegen fehlendes Mapping/fehlende Zeiten. */
    protected function shouldSkip(Organization $organization, TimeEntry $entry): bool {
        return $this->isImportedFromKimai($organization, $entry)
            || ! isset($this->projectMap[(int) $entry->project_id])
            || $entry->started_at === null
            || $entry->ended_at === null;
    }

    protected function createRemoteEntry(Organization $organization, TimeEntry $entry): string {
        assert($this->client instanceof KimaiApiClient && $this->activityId !== null);

        $created = $this->client->createTimesheet([
            'begin' => $entry->started_at?->format('Y-m-d\TH:i:s'),
            'end' => $entry->ended_at?->format('Y-m-d\TH:i:s'),
            'project' => $this->projectMap[(int) $entry->project_id],
            'activity' => $this->activityId,
            'description' => (string) ($entry->description ?? ''),
            'billable' => (bool) $entry->billable,
        ]);

        $kimaiId = $created['id'] ?? null;

        return is_numeric($kimaiId) ? (string) (int) $kimaiId : 'unknown:' . $entry->getKey();
    }

    protected function isExpectedFailure(\Throwable $e): bool {
        return $e instanceof KimaiApiException;
    }

    /**
     * Mapping workDiary-Projekt-ID → Kimai-Projekt-ID aus den
     * `project_id`-References des API-Imports.
     *
     * @return array<int, int>
     */
    private function kimaiProjectIdByProjectId(Organization $organization): array {
        return ExternalReference::query()
            ->forPlugin($organization, KimaiPlugin::ID, KimaiImportService::EXT_TYPE_PROJECT_ID)
            ->where('referenceable_type', (new Project)->getMorphClass())
            ->pluck('external_id', 'referenceable_id')
            ->mapWithKeys(fn ($externalId, $projectId): array => [(int) $projectId => (int) $externalId])
            ->all();
    }

    /** Import-Echo-Schutz: aus Kimai importierte Einträge nie zurückbuchen. */
    private function isImportedFromKimai(Organization $organization, TimeEntry $entry): bool {
        return ExternalReference::query()
            ->forPlugin($organization, KimaiPlugin::ID, KimaiImportService::EXT_TYPE_ENTRY)
            ->forReferenceable($entry)
            ->exists();
    }
}
