<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlWawiServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi;

use App\Models\Organization;
use App\Plugins\JtlWawi\Console\JtlSyncCommand;
use App\Plugins\JtlWawi\Services\{JtlStockReader, JtlWawiInventoryProvider, JtlWawiOutboxDispatcher};
use App\Services\Inventory\{ExternalInventoryDispatcherResolver, InventoryLedger, InventoryProviderResolver};
use Illuminate\Support\ServiceProvider;

/**
 * Bootet das JTL-Wawi-Plugin (Feature 078): Routen, Views, Console-Command
 * und die beiden Bestands-Registrierungen — Outbox-Dispatcher
 * (Schreibzustellung) und Provider-Factory (Lese-/Buchungsvertrag) an den
 * Singleton-Resolvern des Lagerkerns.
 */
class JtlWawiServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . JtlWawiPlugin::ID);

        if ($this->app->runningInConsole()) {
            $this->commands([JtlSyncCommand::class]);
        }
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', JtlWawiPlugin::ID);

        $this->app->make(ExternalInventoryDispatcherResolver::class)
            ->register($this->app->make(JtlWawiOutboxDispatcher::class));

        $this->app->make(InventoryProviderResolver::class)->registerExternal(
            JtlWawiPlugin::ID,
            fn (Organization $organization): JtlWawiInventoryProvider => new JtlWawiInventoryProvider(
                $organization,
                $this->app->make(JtlStockReader::class),
                $this->app->make(InventoryLedger::class),
            ),
        );
    }
}
