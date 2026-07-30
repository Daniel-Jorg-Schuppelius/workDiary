<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglRepairEntryUsersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{ExternalReference, Organization, TimeEntry};
use App\Plugins\Toggl\Sources\{TogglApiClient, TogglCsvParser};
use App\Plugins\Toggl\{TogglConfig, TogglImportService, TogglPlugin};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Repariert die Benutzer-Zuordnung bereits importierter Toggl-CSV-Zeiten:
 * Vor 2026-07-20 buchte der laufende CSV-Import ALLE Einträge auf den
 * Standard-Benutzer statt auf den Mitarbeiter aus der CSV. Der Befehl liest
 * dieselbe Detailed-Report-CSV erneut, findet jeden Eintrag über seine
 * Import-Referenz (deterministischer csv-Hash) und setzt den Benutzer anhand
 * der E-Mail-Spalte um. Ohne --apply nur Dry-Run.
 */
class TogglRepairEntryUsersCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'toggl:repair-entry-users {csv? : Pfad zur Toggl-Detailed-Report-CSV (ohne Angabe: Reports-API)} '
        . self::ORGANIZATION_OPTION
        . ' {--days=90 : API-Modus — Zeitraum rückwärts in Tagen}'
        . ' {--apply : Änderungen schreiben (sonst Dry-Run)}';

    protected $description = 'Setzt die Benutzer bereits importierter Toggl-Zeiten um — aus einer Detailed-Report-CSV oder (ohne CSV) über die Reports-API.';

    public function handle(TogglImportService $service): int {
        $path = (string) ($this->argument('csv') ?? '');
        $csvEntries = null;
        if ($path !== '') {
            if (! is_file($path) || ! is_readable($path)) {
                $this->error("CSV nicht lesbar: {$path}");

                return self::FAILURE;
            }

            $csvEntries = (new TogglCsvParser)->parse((string) file_get_contents($path));
            if ($csvEntries === []) {
                $this->warn('Keine Einträge in der CSV gefunden.');

                return self::SUCCESS;
            }
        }

        $apply = (bool) $this->option('apply');
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            $entries = $csvEntries ?? $this->apiEntries($org);
            if ($entries === []) {
                $this->warn("Organisation #{$org->id} ({$org->name}): keine Einträge (API nicht konfiguriert oder Zeitraum leer).");

                continue;
            }

            $fixed = 0;
            $ok = 0;
            $noEmail = 0;
            $unknownUser = 0;
            $notImported = 0;
            /** @var array<string, true> $missingEmails */
            $missingEmails = [];

            foreach ($entries as $entry) {
                $email = trim((string) $entry->userEmail);
                if ($email === '') {
                    $noEmail++;

                    continue;
                }

                // Gemerkte Zuordnung (user_email-Referenz) vor E-Mail-Gleichheit.
                $userId = $service->resolveImportUser($org, $email);
                if ($userId === null) {
                    $missingEmails[mb_strtolower($email)] = true;
                    $unknownUser++;

                    continue;
                }

                $ref = ExternalReference::query()
                    ->forPlugin($org->id, TogglPlugin::ID, TogglImportService::EXT_TYPE_ENTRY)
                    ->forExternalId($entry->entryKey)
                    ->first();
                $timeEntry = $ref?->referenceable;
                if (! $timeEntry instanceof TimeEntry) {
                    $notImported++;

                    continue;
                }

                if ((int) $timeEntry->user_id === $userId) {
                    $ok++;

                    continue;
                }

                if ($apply) {
                    $timeEntry->update(['user_id' => $userId]);
                }
                $fixed++;
            }

            $mode = $apply ? 'umgesetzt' : 'würden umgesetzt (Dry-Run, --apply zum Schreiben)';
            $this->info("Organisation #{$org->id} ({$org->name}):");
            $this->line("  {$fixed} Einträge {$mode}, {$ok} bereits korrekt, {$notImported} nicht importiert/gefunden, {$unknownUser} ohne passenden Benutzer, {$noEmail} ohne E-Mail.");

            foreach (array_keys($missingEmails) as $missing) {
                $this->warn("  Kein Benutzer/Zuordnung für E-Mail {$missing} — Einträge bleiben unverändert (Zuordnung unter Toggl-Zuordnungen anlegen).");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Einträge über die Reports-API (alle Workspaces des Tokens) — trägt die
     * Benutzer-E-Mails über die Workspace-Benutzerliste.
     *
     * @return array<int, \App\Plugins\Toggl\Sources\TogglEntry>
     */
    private function apiEntries(Organization $organization): array {
        $config = TogglConfig::resolve($organization->id);
        if (! $config['enabled'] || $config['api_token'] === null) {
            return [];
        }

        $client = new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id']);
        $to = CarbonImmutable::now();
        $from = $to->subDays(max(1, (int) $this->option('days')));

        $entries = [];
        foreach ($client->workspaces() as $workspace) {
            foreach ($client->workspaceEntries((int) $workspace['id'], $from, $to) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
