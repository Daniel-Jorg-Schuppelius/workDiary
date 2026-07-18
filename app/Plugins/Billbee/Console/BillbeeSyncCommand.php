<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee\Console;

use App\Models\{Organization, PluginSetting};
use App\Plugins\Billbee\BillbeePlugin;
use App\Plugins\Billbee\Services\{BillbeeArticleMappingService, BillbeeOrderImportService};
use Illuminate\Console\Command;
use Throwable;

/**
 * Periodischer Billbee-Sync (MVP-433/434): Bestellimport Inbox-First +
 * SKU-Mapping-Abgleich je Organisation mit aktiviertem Plugin.
 * Scheduler-Eintrag `billbee.sync` (config/scheduler.php); Fehler einer
 * Organisation stoppen die anderen nicht.
 */
class BillbeeSyncCommand extends Command {
    protected $signature = 'billbee:sync {--org= : Nur diese Organisation (ID) synchronisieren}';

    protected $description = 'Importiert Billbee-Bestellungen (Inbox-First) und gleicht das SKU-Mapping ab.';

    public function handle(BillbeeOrderImportService $orders, BillbeeArticleMappingService $mappings): int {
        $query = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('plugin_id', BillbeePlugin::ID)
            ->where('enabled', true);
        if ($this->option('org') !== null) {
            $query->where('organization_id', (int) $this->option('org'));
        }

        $failures = 0;

        foreach ($query->pluck('organization_id') as $organizationId) {
            $organization = Organization::query()->find((int) $organizationId);
            if (! $organization instanceof Organization) {
                continue;
            }

            try {
                $counters = $orders->import($organization) + ['mapping' => $mappings->import($organization)];
                $this->info(sprintf('Org %d: %s', $organization->id, (string) json_encode($counters)));
            } catch (Throwable $e) {
                $failures++;
                $this->error(sprintf('Org %d: %s', $organization->id, class_basename($e)));
                report($e);
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
