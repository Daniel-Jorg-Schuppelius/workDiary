<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectPushCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Plugins\OpenProject\OpenProjectConfig;
use App\Plugins\OpenProject\Services\OpenProjectExportService;
use Illuminate\Console\Command;

/**
 * Bucht je Organisation die nicht-exportierten Projekt-Zeiteinträge als
 * OpenProject-time_entries zurück (Projekt → gemapptes OpenProject-Projekt,
 * Aufgabe → Work Package). Idempotent über die `pushed_entry`-Reference.
 */
class OpenProjectPushCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'openproject:push ' . self::ORGANIZATION_OPTION;

    protected $description = 'Bucht erfasste workDiary-Zeiten als OpenProject-Zeiteinträge zurück.';

    public function handle(OpenProjectExportService $service): int {
        $organizations = $this->organizationsToProcess();
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

            $this->info("OpenProject-Rückbuchung für Organisation #{$org->id} ({$org->name})...");
            try {
                $result = $service->exportPending($org, $config);
                $this->line("  pushed: {$result['pushed']}, skipped: {$result['skipped']}, failed: {$result['failed']}");
                foreach ($result['errors'] as $error) {
                    $this->warn("  {$error}");
                }
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
