<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopOrderSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Plugins\Support\Console\ChecksPluginSwitch;
use Illuminate\Console\Command;
use Throwable;

/**
 * Gemeinsamer Befehl der Shop-Importe (`<plugin>:sync [--full]`): je
 * Organisation, Schalter, Konfiguration, Verbindung und Bestätigung am
 * Ausführungszeitpunkt.
 *
 * @template TOrder of \Illuminate\Database\Eloquent\Model&ShopOrder
 * @template TConnection of \Illuminate\Database\Eloquent\Model&ShopConnection
 */
abstract class ShopOrderSyncCommand extends Command {
    use ChecksPluginSwitch;
    use IteratesOrganizations;

    abstract protected function pluginId(): string;

    /** @return AbstractShopOrderImportService<TOrder, TConnection> */
    abstract protected function importService(): AbstractShopOrderImportService;

    public function handle(): int {
        $service = $this->importService();
        $full = (bool) $this->option('full');

        $failures = $this->forEachOrganization(
            function (Organization $organization) use ($service, $full): void {
                if (! $this->pluginEnabledFor($this->pluginId(), (int) $organization->id) || ! $service->mayRun($organization)) {
                    return;
                }
                try {
                    $counters = $service->run($organization, $full);
                } catch (ShopOrderBusyException) {
                    $this->warn(sprintf('Organisation #%d: Lauf läuft bereits — übersprungen.', $organization->id));

                    return;
                }
                $this->info(sprintf(
                    'Organisation #%d: imported %d, updated %d, unchanged %d, skipped %d, linked %d, staged %d, failed %d, deleted %d',
                    $organization->id, $counters['imported'], $counters['updated'], $counters['unchanged'],
                    $counters['skipped'], $counters['linked'], $counters['staged'], $counters['failed'], $counters['deleted'],
                ));
            },
            onError: function (Organization $organization, Throwable $e): void {
                $this->error(sprintf('Organisation #%d: %s', $organization->id, class_basename($e)));
                report($e);
            },
        );

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
