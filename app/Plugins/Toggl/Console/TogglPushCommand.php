<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglPushCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Plugins\Toggl\{TogglConfig, TogglExportService};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Überträgt je Organisation lokal erfasste Zeiten gemappter Projekte nach
 * Toggl (Spiegelung — `exported` bleibt false, siehe {@see TogglExportService}).
 * Idempotent über `pushed_entry`-/`entry`-References. Ohne --all begrenzt das
 * Sync-Zeitfenster den Lauf (kein Historien-Blast beim ersten Aktivieren).
 */
class TogglPushCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'toggl:push '
        . self::ORGANIZATION_OPTION
        . ' {--days= : Zeitfenster rückwirkend in Tagen (Standard: Sync-Zeitfenster der Plugin-Einstellungen)}'
        . ' {--all : Ohne Zeitfenster (gesamte Historie übertragen)}';

    protected $description = 'Überträgt in workDiary erfasste Zeiten gemappter Projekte nach Toggl (z. B. Fernwartungssitzungen).';

    public function handle(TogglExportService $service): int {
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            $config = TogglConfig::resolve($org->id);
            if (! $config['enabled'] || ! $config['export_enabled']) {
                continue;
            }
            if ($config['api_token'] === null) {
                $this->warn("Organisation #{$org->id} ({$org->name}): kein Toggl-API-Token — übersprungen.");

                continue;
            }

            $from = null;
            if (! $this->option('all')) {
                $days = is_numeric($this->option('days'))
                    ? max(1, (int) $this->option('days'))
                    : max(1, (int) $config['sync_window_days']);
                $from = CarbonImmutable::now()->subDays($days);
            }

            $this->info("Toggl-Übertragung für Organisation #{$org->id} ({$org->name})...");
            try {
                $result = $service->exportPending($org, $config, $from);
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
