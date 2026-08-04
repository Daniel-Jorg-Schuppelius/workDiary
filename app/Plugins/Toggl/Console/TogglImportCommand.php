<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglImportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Plugins\Toggl\{TogglConfig, TogglImportService};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Holt die Toggl-Zeiteinträge je Organisation per API und legt neue Einträge als
 * TimeEntry im gematchten Projekt an. Nicht zuordenbare Einträge landen in der
 * Toggl-Inbox. Läuft im Scheduler sowie manuell aus der Admin-UI.
 */
class TogglImportCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'toggl:import ' . self::ORGANIZATION_OPTION . '
        {--days= : Zeitfenster rückwirkend in Tagen (überschreibt die Einstellung)}';

    protected $description = 'Importiert Toggl-Zeiteinträge als Zeiteinträge (gematchtes Kundenprojekt) bzw. in die Inbox.';

    public function handle(TogglImportService $service): int {
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            $config = TogglConfig::resolve($org->id);
            if (! $config['enabled']) {
                continue;
            }
            if ($config['api_token'] === null) {
                $this->warn("Organisation #{$org->id} ({$org->name}): kein Toggl API-Token — übersprungen.");

                continue;
            }

            $days = (int) ($this->option('days') ?: $config['sync_window_days']);
            $to = CarbonImmutable::now();
            $from = $to->subDays(max(1, $days));

            $this->info("Toggl-Import für Organisation #{$org->id} ({$org->name}) [{$from->toDateString()} – {$to->toDateString()}]...");
            try {
                $result = $service->importFromApi($org, $config, $from, $to);
                $removed = $result['removed'];
                $this->line("  created: {$result['created']}, skipped: {$result['skipped']}, unmatched: {$result['unmatched']}, updated: {$result['updated']}, conflicts: {$result['conflicts']}, removed: {$removed}");
                if ($removed > 0) {
                    $this->warn("  {$removed} lokale Einträge entfernt (drüben gelöscht).");
                }
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
