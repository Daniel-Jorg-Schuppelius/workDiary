<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyImportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Clockify\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Plugins\Clockify\{ClockifyConfig, ClockifyPlugin};
use App\Plugins\Clockify\Services\ClockifyImportService;
use App\Plugins\Support\Console\ChecksPluginSwitch;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Geplanter API-Import (Phase 137, E21): holt je Organisation mit hinterlegtem
 * API-Key die Clockify-Zeiteinträge des Sync-Zeitfensters — wie Toggl
 * stündlich, Löschabgleich inklusive. Er ist auch der verlässliche Weg hinter
 * dem Webhook. Ohne API-Key bleibt CSV der Weg; die Organisation wird dann
 * still übersprungen.
 */
class ClockifyImportCommand extends Command {
    use ChecksPluginSwitch;
    use IteratesOrganizations;

    protected $signature = 'clockify:import ' . self::ORGANIZATION_OPTION . '
        {--days= : Zeitfenster rückwirkend in Tagen (überschreibt die Einstellung)}';

    protected $description = 'Importiert Clockify-Zeiteinträge per API als Zeiteinträge (gematchtes Projekt) bzw. in die Zuordnungs-Inbox.';

    public function handle(ClockifyImportService $service): int {
        $this->forEachOrganization(function (Organization $org) use ($service): void {
            if (! $this->pluginEnabledFor(ClockifyPlugin::ID, (int) $org->id)) {
                return;
            }
            $config = ClockifyConfig::resolve((int) $org->id);
            if ($config['api_key'] === null) {
                return;
            }

            // Ohne --days dasselbe Fenster wie „Jetzt importieren“ (Sync-Zeitfenster ab Tagesbeginn).
            $days = max(1, (int) ($this->option('days') ?: $config['sync_window_days']));
            $from = CarbonImmutable::now()->subDays($days)->startOfDay();

            $this->info("Clockify-Import für Organisation #{$org->id} ({$org->name}), letzte {$days} Tage...");
            $result = $service->importFromApi($org, $config, $from);
            if (isset($result['error'])) {
                $this->error('  Fehler: ' . $result['error']);

                return;
            }
            $this->line(sprintf(
                '  created: %d, skipped: %d, unmatched: %d, unresolved_users: %d, updated: %d, conflicts: %d, removed: %d',
                $result['created'], $result['skipped'], $result['unmatched'], $result['unresolved_users'] ?? 0,
                $result['updated'] ?? 0, $result['conflicts'] ?? 0, $result['removed'] ?? 0,
            ));
        });

        return self::SUCCESS;
    }
}
