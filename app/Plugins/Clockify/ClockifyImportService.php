<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Clockify;

use App\Models\Organization;
use App\Plugins\Clockify\Exceptions\ClockifyApiException;
use App\Plugins\Clockify\Sources\{ClockifyApiClient, ClockifyCsvParser};
use App\Plugins\Support\{ImportedTimeEntry, MatchingTimeImportService, RemoteSyncWindow};
use Carbon\CarbonImmutable;

/**
 * Clockify-Zeitimport auf der gemeinsamen {@see MatchingTimeImportService}-
 * Pipeline, gespeist aus Detailed-Report-CSV ({@see ClockifyCsvParser}) oder
 * Reports-API ({@see ClockifyApiClient}). Clockify-IDs sind Hex-Strings —
 * es gibt kein numerisches ID-Mapping (und keinen Export-Rückkanal);
 * Idempotenz läuft über die stabile `_id` der Reports-API bzw. den
 * CSV-Zeilen-Hash.
 */
class ClockifyImportService extends MatchingTimeImportService {
    public function __construct(private readonly ClockifyCsvParser $csvParser = new ClockifyCsvParser) {}

    protected function pluginId(): string {
        return ClockifyPlugin::ID;
    }

    protected function resolveConfig(int $organizationId): array {
        return ClockifyConfig::resolve($organizationId);
    }

    protected function fallbackDescription(): string {
        return (string) __('Clockify-Zeiteintrag');
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int, unresolved_users: int, updated: int, conflicts: int, removed: int}
     */
    public function importFromCsv(Organization $organization, string $csvContent, array $config): array {
        return $this->ingest($organization, $this->csvParser->parse($csvContent), $config);
    }

    /**
     * Import über die Clockify-Reports-API (`X-Api-Key`). Fenster: explizit
     * übergeben oder `sync_window_days` rückwirkend. Laufende Einträge
     * (end = null) werden übersprungen. 429-Fehler (Free-Plan) kommen als
     * lesbare Fehlermeldung mit CSV-Hinweis zurück.
     *
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int, unresolved_users?: int, updated?: int, conflicts?: int, removed?: int, error?: string}
     */
    public function importFromApi(Organization $organization, array $config, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array {
        $client = new ClockifyApiClient(
            is_string($config['api_key'] ?? null) ? $config['api_key'] : null,
            is_string($config['base_url'] ?? null) && $config['base_url'] !== '' ? $config['base_url'] : ClockifyConfig::DEFAULT_BASE_URL,
            is_string($config['reports_base_url'] ?? null) && $config['reports_base_url'] !== '' ? $config['reports_base_url'] : ClockifyConfig::DEFAULT_REPORTS_BASE_URL,
            is_string($config['workspace_id'] ?? null) ? $config['workspace_id'] : null,
        );
        if (! $client->isConfigured()) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0, 'unresolved_users' => 0, 'error' => (string) __('Clockify-API ist nicht konfiguriert (API-Key in den Plugin-Einstellungen hinterlegen).')];
        }

        $from ??= CarbonImmutable::now()->subDays((int) ($config['sync_window_days'] ?? 30))->startOfDay();
        $to ??= CarbonImmutable::now()->endOfDay();

        try {
            $rows = $client->getTimeEntries($from, $to);
        } catch (ClockifyApiException $e) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0, 'unresolved_users' => 0, 'error' => $e->getMessage()];
        }

        $entries = [];
        foreach ($rows as $row) {
            $entry = $this->entryFromApiRow((array) $row);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        // Der Detailed-Report umfasst alle Benutzer des Workspace — fehlende
        // Einträge sind drüben gelöscht.
        return $this->ingest($organization, $entries, $config, new RemoteSyncWindow($from, $to));
    }

    /**
     * Mappt einen Detailed-Report-Eintrag auf das DTO. Die Reports-API liefert
     * (mit `timeZone: UTC`) UTC-Zeiten — Konvertierung in die App-Zeitzone,
     * damit Datum/Uhrzeiten der Zeiteinträge stimmen. Laufende Einträge
     * (kein `end`) liefern null.
     *
     * @param  array<string, mixed>  $row
     */
    private function entryFromApiRow(array $row): ?ImportedTimeEntry {
        $id = $row['_id'] ?? ($row['id'] ?? null);
        $interval = is_array($row['timeInterval'] ?? null) ? $row['timeInterval'] : [];
        $start = $interval['start'] ?? null;
        $end = $interval['end'] ?? null;
        if (! is_string($id) && ! is_numeric($id)) {
            return null;
        }
        if (! is_string($start) || $start === '' || ! is_string($end) || $end === '') {
            return null;
        }

        $timezone = (string) config('app.timezone', 'UTC');

        /** @var list<string> $tags */
        $tags = [];
        if (isset($row['tags']) && is_array($row['tags'])) {
            foreach ($row['tags'] as $tag) {
                if (is_array($tag) && isset($tag['name'])) {
                    $tags[] = (string) $tag['name'];
                } elseif (is_string($tag)) {
                    $tags[] = $tag;
                }
            }
        }

        $task = $row['taskName'] ?? (is_array($row['task'] ?? null) ? ($row['task']['name'] ?? null) : null);

        return new ImportedTimeEntry(
            entryKey: ImportedTimeEntry::apiKey((string) $id),
            clientName: isset($row['clientName']) && is_string($row['clientName']) && $row['clientName'] !== '' ? $row['clientName'] : null,
            projectName: isset($row['projectName']) && is_string($row['projectName']) && $row['projectName'] !== '' ? $row['projectName'] : null,
            activity: is_string($task) && $task !== '' ? $task : null,
            description: isset($row['description']) && is_string($row['description']) && $row['description'] !== '' ? $row['description'] : null,
            startedAt: CarbonImmutable::parse($start)->setTimezone($timezone),
            endedAt: CarbonImmutable::parse($end)->setTimezone($timezone),
            billable: (bool) ($row['billable'] ?? false),
            userEmail: isset($row['userEmail']) && is_string($row['userEmail']) && $row['userEmail'] !== '' ? $row['userEmail'] : null,
            tags: $tags,
            source: ImportedTimeEntry::SOURCE_API,
        );
    }
}
