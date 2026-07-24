<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyBackfillCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Calendly\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\PluginSetting;
use App\Plugins\Calendly\CalendlyPlugin;
use App\Plugins\Calendly\Services\CalendlyBackfillService;
use App\Support\OrganizationContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Polling-Backfill der Calendly-Termine je Organisation (Feature 095): holt
 * `scheduled_events` + Invitees des Zeitfensters und legt neue/aktualisierte
 * Terminwünsche an bzw. gibt abgesagte frei. Läuft im Scheduler
 * (`calendly.backfill`) sowie manuell aus der Admin-UI.
 */
class CalendlyBackfillCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'calendly:backfill ' . self::ORGANIZATION_OPTION;

    protected $description = 'Gleicht extern gebuchte Calendly-Termine je Organisation ab (Backfill/Reconciliation).';

    public function handle(CalendlyBackfillService $service): int {
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            $enabled = PluginSetting::forOrganization($org->id, CalendlyPlugin::ID)->enabled
                || (bool) config('plugins.calendly.enabled', false);
            if (! $enabled) {
                continue;
            }

            $this->info("Calendly-Backfill für Organisation #{$org->id} ({$org->name})...");
            try {
                $result = OrganizationContext::run($org, fn(): array => $service->sync($org));
                $this->line("  created: {$result['created']}, updated: {$result['updated']}, skipped: {$result['skipped']}, unmatched: {$result['unmatched']}");
            } catch (Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
