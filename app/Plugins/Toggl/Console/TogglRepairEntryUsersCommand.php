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
use App\Models\{ExternalReference, TimeEntry};
use App\Plugins\Toggl\Sources\TogglCsvParser;
use App\Plugins\Toggl\{TogglImportService, TogglPlugin};
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

    protected $signature = 'toggl:repair-entry-users {csv : Pfad zur Toggl-Detailed-Report-CSV} '
        . self::ORGANIZATION_OPTION
        . ' {--apply : Änderungen schreiben (sonst Dry-Run)}';

    protected $description = 'Setzt die Benutzer bereits importierter Toggl-CSV-Zeiten anhand der E-Mail-Spalte der CSV um.';

    public function handle(TogglImportService $service): int {
        $path = (string) $this->argument('csv');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("CSV nicht lesbar: {$path}");

            return self::FAILURE;
        }

        $entries = (new TogglCsvParser)->parse((string) file_get_contents($path));
        if ($entries === []) {
            $this->warn('Keine Einträge in der CSV gefunden.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
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
                    ->withoutGlobalScopes()
                    ->where('organization_id', $org->id)
                    ->where('plugin_id', TogglPlugin::ID)
                    ->where('external_type', TogglImportService::EXT_TYPE_ENTRY)
                    ->where('external_id', $entry->entryKey)
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
}
