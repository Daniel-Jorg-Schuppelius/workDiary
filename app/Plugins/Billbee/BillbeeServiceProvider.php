<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee;

use App\Models\Organization;
use App\Plugins\Billbee\Console\BillbeeSyncCommand;
use App\Plugins\Billbee\Services\{BillbeeInventoryProvider, BillbeeStockDispatcher};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Inventory\{ExternalInventoryDispatcherResolver, InventoryLedger, InventoryProviderResolver};

/**
 * Bootet das Billbee-Plugin (MVP-433/434): Config-Defaults, Admin-Routen/
 * Views, Sync-Command sowie die Bestandsverdrahtung nach JTL-Muster —
 * Provider-Registry (External-Mode je Org wählbar) + Outbox-Dispatcher
 * (Absolut-Stock-Updates).
 */
class BillbeeServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return BillbeePlugin::ID;
    }

    protected function registerPlugin(): void {
        if ($this->app->runningInConsole()) {
            $this->commands([BillbeeSyncCommand::class]);
        }
    }

    protected function bootPlugin(): void {
        app(ExternalInventoryDispatcherResolver::class)->register(new BillbeeStockDispatcher());
        app(InventoryProviderResolver::class)->registerExternal(
            BillbeePlugin::ID,
            static fn(Organization $organization): BillbeeInventoryProvider => new BillbeeInventoryProvider($organization, app(InventoryLedger::class)),
        );
    }
}
