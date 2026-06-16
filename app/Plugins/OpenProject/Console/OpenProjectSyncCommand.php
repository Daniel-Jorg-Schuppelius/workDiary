<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Console;

use App\Models\Organization;
use App\Plugins\OpenProject\OpenProjectConfig;
use App\Plugins\OpenProject\Services\OpenProjectImportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Synchronisiert je Organisation die OpenProject-Struktur (Projekte + Work
 * Packages + Benutzer) und importiert die Zeiteinträge des Zeitfensters. Nicht
 * zuordenbare Einträge landen in der OpenProject-Inbox. Läuft im Scheduler sowie
 * manuell aus der Admin-UI.
 */
class OpenProjectSyncCommand extends Command {
    protected $signature = 'openproject:import
        {--organization= : ID einer einzelnen Organisation, sonst alle}
        {--days= : Zeitfenster rückwirkend in Tagen (überschreibt die Einstellung)}';

    protected $description = 'Synchronisiert OpenProject-Struktur und importiert Zeiteinträge (gemapptes Projekt) bzw. in die Inbox.';

    public function handle(OpenProjectImportService $service): int {
        $orgId = $this->option('organization');
        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        $organizations = $query->get();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            $config = OpenProjectConfig::resolve($org->id);
            if (! $config['enabled']) {
                continue;
            }
            if ($config['api_token'] === null || $config['base_url'] === null) {
                $this->warn("Organisation #{$org->id} ({$org->name}): keine OpenProject-Zugangsdaten — übersprungen.");

                continue;
            }

            $days = (int) ($this->option('days') ?: $config['sync_window_days']);
            $to = CarbonImmutable::now();
            $from = $to->subDays(max(1, $days));

            $this->info("OpenProject-Import für Organisation #{$org->id} ({$org->name}) [{$from->toDateString()} – {$to->toDateString()}]...");
            try {
                $result = $service->importFromApi($org, $config, $from, $to);
                $this->line("  created: {$result['created']}, skipped: {$result['skipped']}, unmatched: {$result['unmatched']}");
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
