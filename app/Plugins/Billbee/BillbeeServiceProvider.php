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
use App\Services\Inventory\{ExternalInventoryDispatcherResolver, InventoryLedger, InventoryProviderResolver};
use Illuminate\Support\ServiceProvider;

/**
 * Bootet das Billbee-Plugin (MVP-433/434): Config-Defaults, Admin-Routen/
 * Views, Sync-Command sowie die Bestandsverdrahtung nach JTL-Muster —
 * Provider-Registry (External-Mode je Org wählbar) + Outbox-Dispatcher
 * (Absolut-Stock-Updates).
 */
class BillbeeServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . BillbeePlugin::ID);

        if ($this->app->runningInConsole()) {
            $this->commands([BillbeeSyncCommand::class]);
        }
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', BillbeePlugin::ID);

        app(ExternalInventoryDispatcherResolver::class)->register(new BillbeeStockDispatcher());
        app(InventoryProviderResolver::class)->registerExternal(
            BillbeePlugin::ID,
            static fn(Organization $organization): BillbeeInventoryProvider => new BillbeeInventoryProvider($organization, app(InventoryLedger::class)),
        );
    }
}
