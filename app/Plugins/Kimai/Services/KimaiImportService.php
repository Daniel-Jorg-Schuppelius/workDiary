<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Kimai\Services;

use App\Models\Platform\Organization;
use App\Plugins\Kimai\Api\KimaiApiClient;
use App\Plugins\Kimai\{KimaiConfig, KimaiPlugin};
use App\Plugins\Kimai\Sources\KimaiCsvParser;
use App\Plugins\Support\{ImportedTimeEntry, MatchingTimeImportService, RemoteSyncWindow};
use App\Plugins\Support\TimeTracking\CsvAndApiTimeImporter;
use App\Support\{ErrorText, Tz};
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Kimai-Zeitimport auf der gemeinsamen {@see MatchingTimeImportService}-
 * Pipeline, gespeist aus CSV-Export ({@see KimaiCsvParser}) oder REST-API
 * ({@see KimaiApiClient}). Beim API-Import werden die numerischen Kimai-IDs
 * als `client_id`-/`project_id`-References gemerkt — das Mapping des
 * Export-Rückkanals ({@see KimaiExportService}).
 */
class KimaiImportService extends MatchingTimeImportService implements CsvAndApiTimeImporter {
    public function __construct(private readonly KimaiCsvParser $csvParser = new KimaiCsvParser) {}

    protected function pluginId(): string {
        return KimaiPlugin::ID;
    }

    protected function resolveConfig(int $organizationId): array {
        return KimaiConfig::resolve($organizationId);
    }

    protected function fallbackDescription(): string {
        return (string) __('Kimai-Zeiteintrag');
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int, unresolved_users: int, updated: int, conflicts: int, removed: int}
     */
    public function importFromCsv(Organization $organization, string $csvContent, array $config): array {
        return $this->ingest($organization, $this->csvParser->parse($csvContent, Tz::ofOrganization($organization)), $config);
    }

    /**
     * Import direkt über die Kimai-REST-API (Bearer-Token). Fenster: explizit
     * übergeben oder `sync_window_days` rückwirkend. Laufende Timesheets
     * (end = null) werden übersprungen.
     *
     * @param  array<string, mixed>  $config
     * @return array{created: int, skipped: int, unmatched: int, unresolved_users?: int, updated?: int, conflicts?: int, removed?: int, error?: string}
     */
    public function importFromApi(Organization $organization, array $config, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array {
        $client = new KimaiApiClient(
            is_string($config['api_token'] ?? null) ? $config['api_token'] : null,
            is_string($config['base_url'] ?? null) ? $config['base_url'] : null,
            Tz::ofOrganization($organization),
            (bool) ($config['allow_private_network'] ?? false),
        );
        if (! $client->isConfigured()) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0, 'unresolved_users' => 0, 'error' => (string) __('Kimai-API ist nicht konfiguriert (Basis-URL und API-Token in den Plugin-Einstellungen hinterlegen).')];
        }

        $from ??= CarbonImmutable::now()->subDays((int) ($config['sync_window_days'] ?? 30))->startOfDay();

        $allUsers = (bool) ($config['api_all_users'] ?? true);
        try {
            $rows = $client->getTimesheets($from, $to, $allUsers);
        } catch (RuntimeException $e) {
            return ['created' => 0, 'skipped' => 0, 'unmatched' => 0, 'unresolved_users' => 0, 'error' => ErrorText::for($e)];
        }

        $entries = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $entry = $this->entryFromApiRow($row, $this->userSignal($organization, is_array($row['user'] ?? null) ? $row['user'] : []));
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $this->ingest(
            $organization,
            $entries,
            $config,
            // Ohne `user=all` liefert Kimai nur die Zeiten des Token-Benutzers —
            // fremde Einträge fehlten dann, ohne gelöscht zu sein.
            RemoteSyncWindow::whenComplete($allUsers, $from, $to ?? CarbonImmutable::now()->endOfDay()),
        );
    }

    /**
     * Personensignal eines Kimai-Benutzers: die E-Mail-Adresse (Kimai 2 liefert
     * sie mit `full=true`), sonst der Benutzername. Bereits in der Inbox gemerkte
     * Zuordnungen hängen am Benutzernamen und gelten weiter.
     *
     * @param  array<string, mixed>  $user
     */
    private function userSignal(Organization $organization, array $user): ?string {
        $email = trim((string) ($user['email'] ?? ''));
        $username = trim((string) ($user['username'] ?? ''));

        if ($email !== '' && $username !== ''
            && $this->resolveImportUser($organization, $email) === null
            && $this->resolveImportUser($organization, $username) !== null) {
            return $username;
        }

        return $email !== '' ? $email : ($username !== '' ? $username : null);
    }

    /**
     * Mappt ein `full=true`-serialisiertes Kimai-Timesheet auf das DTO.
     * Laufende Einträge (kein `end`) liefern null.
     *
     * @param  array<string, mixed>  $row
     */
    private function entryFromApiRow(array $row, ?string $userSignal): ?ImportedTimeEntry {
        $id = $row['id'] ?? null;
        $begin = $row['begin'] ?? null;
        $end = $row['end'] ?? null;
        if (! is_numeric($id) || ! is_string($begin) || $begin === '' || ! is_string($end) || $end === '') {
            return null;
        }

        $project = is_array($row['project'] ?? null) ? $row['project'] : [];
        $customer = is_array($project['customer'] ?? null) ? $project['customer'] : [];
        $activity = is_array($row['activity'] ?? null) ? $row['activity'] : [];

        /** @var list<string> $tags */
        $tags = isset($row['tags']) && is_array($row['tags']) ? array_values(array_map('strval', $row['tags'])) : [];

        return new ImportedTimeEntry(
            entryKey: ImportedTimeEntry::apiKey((int) $id),
            clientName: isset($customer['name']) ? (string) $customer['name'] : null,
            projectName: isset($project['name']) ? (string) $project['name'] : null,
            activity: isset($activity['name']) ? (string) $activity['name'] : null,
            description: isset($row['description']) ? (string) $row['description'] : null,
            startedAt: CarbonImmutable::parse($begin),
            endedAt: CarbonImmutable::parse($end),
            billable: (bool) ($row['billable'] ?? false),
            userEmail: $userSignal,
            tags: $tags,
            source: ImportedTimeEntry::SOURCE_API,
            clientId: is_numeric($customer['id'] ?? null) ? (int) $customer['id'] : null,
            projectId: is_numeric($project['id'] ?? null) ? (int) $project['id'] : null,
            activityId: is_numeric($activity['id'] ?? null) ? (int) $activity['id'] : null,
        );
    }
}
