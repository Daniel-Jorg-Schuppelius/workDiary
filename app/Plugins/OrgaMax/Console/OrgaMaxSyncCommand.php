<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Console;

use App\Models\{OrgaMaxConnection, Organization, PluginSetting};
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\OrgaMax\Services\OrgaMaxSyncService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Budgetierter orgaMAX-Abgleich (Feature 077, MVP-313): je aktiver
 * Verbindung ein Lauf mit Checkpoints und Laufbudget; Fehler einer
 * Organisation stoppen die anderen nicht.
 */
class OrgaMaxSyncCommand extends Command {
    protected $signature = 'orgamax:sync {--org= : Nur diese Organisations-ID abgleichen}';

    protected $description = 'Gleicht orgaMAX-Buchhaltung ab (Projektionen, Checkpoints, Laufbudget).';

    public function handle(OrgaMaxSyncService $sync): int {
        $query = OrgaMaxConnection::query()
            ->withoutGlobalScopes()
            ->where('status', OrgaMaxConnection::STATUS_ACTIVE);
        if ($this->option('org') !== null) {
            $query->where('organization_id', (int) $this->option('org'));
        }

        $failures = 0;
        foreach ($query->orderBy('organization_id')->get() as $connection) {
            $setting = PluginSetting::forOrganization($connection->organization_id, OrgaMaxPlugin::ID);
            if ($setting->exists && ! $setting->enabled) {
                continue;
            }

            $organization = Organization::query()->withoutGlobalScopes()->find($connection->organization_id);
            if ($organization === null) {
                continue;
            }
            app()->instance('currentOrganization', $organization);

            try {
                $counters = $sync->run($connection);
                $this->info(sprintf('Org %d: %s', $connection->organization_id, json_encode($counters)));
            } catch (Throwable $e) {
                $failures++;
                $connection->forceFill(['last_error' => mb_substr($e::class, 0, 200)])->save();
                $this->error(sprintf('Org %d: Abgleich fehlgeschlagen (%s).', $connection->organization_id, $e::class));
            } finally {
                app()->forgetInstance('currentOrganization');
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
