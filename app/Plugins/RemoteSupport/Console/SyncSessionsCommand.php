<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncSessionsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Plugins\RemoteSupport\{RemoteSupportConfig, RemoteSupportService};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Holt die AnyDesk-/TeamViewer-Verbindungen je Organisation und legt neue
 * Sitzungen als TimeEntry im Standardprojekt des zugeordneten Kunden an.
 * Läuft sowohl stündlich im Scheduler als auch manuell aus der Admin-UI.
 */
class SyncSessionsCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'remote:sync-sessions ' . self::ORGANIZATION_OPTION . '
        {--days= : Zeitfenster rückwirkend in Tagen (überschreibt die Einstellung)}';

    protected $description = 'Importiert AnyDesk-/TeamViewer-Verbindungen als Zeiteinträge (Standardprojekt des Kunden).';

    public function handle(RemoteSupportService $service): int {
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            $config = RemoteSupportConfig::resolve($org->id);
            if (! $config['enabled']) {
                continue;
            }
            if ($service->providersFor($config) === []) {
                $this->warn("Organisation #{$org->id} ({$org->name}): kein Fernwartungs-Anbieter aktiv — übersprungen.");

                continue;
            }

            $days = (int) ($this->option('days') ?: $config['sync_window_days']);
            $to = CarbonImmutable::now();
            $from = $to->subDays(max(1, $days));

            $this->info("Fernwartungs-Sync für Organisation #{$org->id} ({$org->name}) [{$from->toDateString()} – {$to->toDateString()}]...");
            try {
                $result = $service->import($org, $config, $from, $to);
                $this->line("  created: {$result['created']}, linked: {$result['linked']}, skipped: {$result['skipped']}, unmatched: {$result['unmatched']}");
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
