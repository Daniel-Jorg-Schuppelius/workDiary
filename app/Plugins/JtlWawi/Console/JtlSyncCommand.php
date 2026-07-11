<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Console;

use App\Models\{JtlConnection, Organization, PluginSetting};
use App\Plugins\JtlWawi\JtlWawiPlugin;
use App\Plugins\JtlWawi\Services\JtlSyncService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Periodischer JTL-Sync (Feature 078, MVP-322): Lager → Artikel →
 * Bestandsdeltas je Organisation mit aktiver Verbindung und aktiviertem
 * Plugin. Scheduler-Eintrag `jtl.sync` (config/scheduler.php); Fehler einer
 * Organisation stoppen die anderen nicht.
 */
class JtlSyncCommand extends Command {
    protected $signature = 'jtl:sync {--org= : Nur diese Organisation (ID) synchronisieren}';

    protected $description = 'Synchronisiert Lager-, Artikel- und Bestandsprojektionen aus JTL-Wawi.';

    public function handle(JtlSyncService $sync): int {
        $query = JtlConnection::withoutGlobalScopes()->where('status', JtlConnection::STATUS_ACTIVE);
        if ($this->option('org') !== null) {
            $query->where('organization_id', (int) $this->option('org'));
        }

        $failures = 0;

        foreach ($query->get() as $connection) {
            $organization = Organization::query()->find($connection->organization_id);
            if (! $organization instanceof Organization) {
                continue;
            }

            $setting = PluginSetting::forOrganization($organization->id, JtlWawiPlugin::ID);
            $enabled = $setting->exists ? (bool) $setting->enabled : (bool) config('plugins.' . JtlWawiPlugin::ID . '.enabled', false);
            if (! $enabled) {
                continue;
            }

            try {
                $counters = $sync->run($connection);
                $this->info(sprintf('Org %d: %s', $organization->id, json_encode($counters)));
            } catch (Throwable $e) {
                $failures++;
                $this->error(sprintf('Org %d: %s', $organization->id, class_basename($e)));
                report($e);
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
