<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglBackfillReferencesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Plugins\Toggl\{TogglConfig, TogglImportService};
use Illuminate\Console\Command;

/**
 * Trägt für bestehende, namensbasiert verknüpfte Projekte/Kunden die stabilen
 * Toggl-Projekt-/Client-ID-Referenzen nach (einmaliger Sync gegen den Workspace).
 * Danach matchen Folgeimporte ID-first und überstehen Umbenennungen in Toggl.
 */
class TogglBackfillReferencesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'toggl:backfill-references ' . self::ORGANIZATION_OPTION;

    protected $description = 'Trägt stabile Toggl-ID-Referenzen (project_id/client_id) für bestehende, namensbasiert verknüpfte Projekte/Kunden nach.';

    public function handle(TogglImportService $service): int {
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            $config = TogglConfig::resolve($org->id);
            if (! $config['enabled'] || $config['api_token'] === null) {
                continue;
            }

            $this->info("Toggl-ID-Backfill für Organisation #{$org->id} ({$org->name})...");
            try {
                $result = $service->backfillIdReferences($org);
                $this->line("  Projekt-IDs: {$result['projects']}, Client-IDs: {$result['clients']} nachgetragen.");
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
